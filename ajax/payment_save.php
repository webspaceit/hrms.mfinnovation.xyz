<?php
// ============================================================
// Payment AJAX - Save
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$lease_id = (int)post('lease_id', 0);
$month = (int)post('month', 0);
$year = (int)post('year', 0);
$amount = (float)bnToEnDigits(post('amount', 0));
$parking_amount = (float)bnToEnDigits(post('parking_amount', 0));
$gas_amount = (float)bnToEnDigits(post('gas_amount', 0));
$water_fee = (float)bnToEnDigits(post('water_fee', 0));
$waste_fee = (float)bnToEnDigits(post('waste_fee', 0));
$payment_method = post('payment_method', 'cash');
$receipt_type = post('receipt_type', 'rent');
if (!in_array($receipt_type, ['rent', 'parking', 'gas', 'water', 'waste'])) {
    $receipt_type = 'rent';
}
$payment_date = bnToEnDigits(post('payment_date'));
$payment_date = date('Y-m-d', strtotime($payment_date));
$note = post('note');
// Note block styling: only a plain hex colour and a known font family are
// stored, because both end up in a style attribute on the receipt.
$note_color = trim((string)post('note_color', ''));
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $note_color)) {
    $note_color = '';
}
$note_font = trim((string)post('note_font', ''));
if (!array_key_exists($note_font, NOTE_FONT_CHOICES)) {
    $note_font = '';
}
// The service charge month chips post as service_charge_month[] (several
// months can share one scan), so read the field as a list.
$service_charge_month = monthList(postArray('service_charge_month'));
$service_charge_year = (int)post('service_charge_year', 0);
$arrears = (float)bnToEnDigits(post('arrears', 0));

if (empty($lease_id) || $month < 1 || $month > 12 || $year < 2000) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$leaseModel = new Lease();
$lease = $leaseModel->find($lease_id);

// Landlords may only collect payments for tenants they entered.
if (!$lease || !tenantAccessible((int)$lease['tenant_id'])) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}

$paymentModel = new Payment();

// The invoice is the single source of truth for the month's bill: the whole
// payment (rent + service bills + arrears) may not exceed what the invoice
// still has due. Mirrors "invoice drives payment" — the receipt then always
// equals the invoice.
$total_amount = $amount + $parking_amount + $gas_amount + $water_fee + $waste_fee + $arrears;
$remaining = $paymentModel->remainingDue($lease_id, $month, $year);
if ($remaining !== null && $total_amount > $remaining + 0.009) {
    jsonResponse(['success' => false, 'message' => t('overpay_not_allowed') . ': ' . t('remaining_due') . ' ' . money($remaining)]);
}

// Whether this payment can carry a monthly service charge receipt scan:
// only flat-unit rent receipts show/need one.
$isFlatRent = $receipt_type === 'rent' && !empty($lease['flat_id'])
    && ((new Flat())->find((int)$lease['flat_id'])['unit_type'] ?? 'flat') === 'flat';

$paymentId = (int)$paymentModel->create([
    'lease_id' => $lease_id,
    'tenant_id' => $lease['tenant_id'],
    'flat_id' => $lease['flat_id'],
    'month' => $month,
    'year' => $year,
    'amount' => $amount,
    'parking_amount' => $parking_amount,
    'gas_amount' => $gas_amount,
    'water_fee' => $water_fee,
    'waste_fee' => $waste_fee,
    'arrears' => $arrears,
    'total_amount' => $total_amount,
    'payment_method' => $payment_method,
    'receipt_type' => $receipt_type,
    'received_by' => Auth::id(),
    'payment_date' => $payment_date,
    'note' => $note,
    // Note block styling (color + font chosen for this payment's receipt).
    'note_color' => $note_color !== '' ? $note_color : null,
    'note_font' => $note_font !== '' ? $note_font : null,
    // The months the attached service charge receipt scan belongs to (flat
    // rent only, several months may share one scan); the receipt shows these
    // months instead of auto-prev-month.
    'service_charge_month' => $isFlatRent && !empty($service_charge_month) && $service_charge_year >= 2000 ? monthListToDb($service_charge_month) : null,
    'service_charge_year' => $isFlatRent && $service_charge_year >= 2000 ? $service_charge_year : null
]);

// Service charge receipt scan — attached to the payment itself so every
// month's rent keeps its own scan. The month the scan covers is chosen in
// the form (see service_charge_month/year above). FLAT units booked as rent
// only; an invalid file rejects the whole payment.
if ($receipt_type === 'rent' && !empty($lease['flat_id'])) {
    $unitType = (new Flat())->find((int)$lease['flat_id'])['unit_type'] ?? 'flat';
    if ($unitType === 'flat' && !empty($_FILES['service_charge_file'])
        && ($_FILES['service_charge_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $path = uploadDocument($_FILES['service_charge_file'], 'service_charge', $paymentId);
        if ($path === false) {
            $paymentModel->delete($paymentId);
            $paymentModel->reconcileInvoice($lease_id, $month, $year);
            jsonResponse(['success' => false, 'message' => t('invalid_file')]);
        }
        $paymentModel->update($paymentId, ['service_charge_file' => $path]);
    }
}

$paymentModel->reconcileInvoice($lease_id, $month, $year);

jsonResponse(['success' => true, 'message' => t('saved_success')]);
