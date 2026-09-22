<?php
// ============================================================
// Payment AJAX - Update
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
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
$arrears = (float)bnToEnDigits(post('arrears', 0));

if (!$id || empty($lease_id) || $month < 1 || $month > 12 || $year < 2000) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$paymentModel = new Payment();
$existing = $paymentModel->find($id);

// Landlords may only edit payments of tenants they entered.
$targetLease = (new Lease())->find($lease_id);
if (!$existing || !$targetLease
    || !tenantAccessible((int)$existing['tenant_id'])
    || !tenantAccessible((int)$targetLease['tenant_id'])) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}

$total_amount = $amount + $parking_amount + $gas_amount + $water_fee + $waste_fee + $arrears;

// The invoice is the single source of truth for the month's bill: after this
// edit the whole collected amount (rent + service bills + arrears) may not
// exceed the invoice total due (this payment's previous value excluded).
$invoice = Database::getInstance()->fetch(
    "SELECT total_due FROM invoices WHERE lease_id = :l AND month = :m AND year = :y LIMIT 1",
    ['l' => $lease_id, 'm' => $month, 'y' => $year]
);
if ($invoice) {
    $otherPaid = $paymentModel->sumForInvoice($lease_id, $month, $year) - (float)$existing['total_amount'];
    if ($total_amount + $otherPaid > (float)$invoice['total_due'] + 0.009) {
        jsonResponse(['success' => false, 'message' => t('overpay_not_allowed') . ': ' . t('remaining_due') . ' ' . money(max(0, (float)$invoice['total_due'] - $otherPaid))]);
    }
}

$paymentModel->update($id, [
    'lease_id' => $lease_id,
    'tenant_id' => $existing['tenant_id'],
    'flat_id' => $existing['flat_id'],
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
    'payment_date' => $payment_date,
    'note' => $note
]);

$paymentModel->reconcileInvoice($lease_id, $month, $year);

jsonResponse(['success' => true, 'message' => t('updated_success')]);