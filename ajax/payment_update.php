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

// Handle service charge upload from quick upload modal
if (post('action') === 'upload_service_charge') {
    $paymentId = (int)post('payment_id', 0);
    if (!$paymentId) {
        jsonResponse(['success' => false, 'message' => t('error_occurred')]);
    }
    
    $paymentModel = new Payment();
    $existing = $paymentModel->find($paymentId);
    if (!$existing || !tenantAccessible((int)$existing['tenant_id'])) {
        jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
    }
    
    // Check if flat rent
    $isFlatRent = $existing['receipt_type'] === 'rent' && !empty($existing['flat_id'])
        && ((new Flat())->find((int)$existing['flat_id'])['unit_type'] ?? 'flat') === 'flat';
    
    if (!$isFlatRent) {
        jsonResponse(['success' => false, 'message' => t('service_charge_flat_only')]);
    }
    
    $service_charge_month = monthList(postArray('service_charge_month'));
    $service_charge_year = (int)post('service_charge_year', 0);
    
    if (empty($service_charge_month) || $service_charge_year < 2000) {
        jsonResponse(['success' => false, 'message' => t('service_charge_month_year_required')]);
    }
    
    if (empty($_FILES['service_charge_file'])
        || ($_FILES['service_charge_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        jsonResponse(['success' => false, 'message' => t('invalid_file')]);
    }
    
    $path = uploadDocument($_FILES['service_charge_file'], 'service_charge', $paymentId);
    if ($path === false) {
        jsonResponse(['success' => false, 'message' => t('invalid_file')]);
    }
    
    // Delete old file if exists
    if (!empty($existing['service_charge_file'])) {
        deleteUploadedDocument($existing['service_charge_file']);
    }
    
    $data = [
        'service_charge_file' => $path,
        'service_charge_month' => monthListToDb($service_charge_month),
        'service_charge_year' => $service_charge_year
    ];
    
    $paymentModel->update($paymentId, $data);
    
    jsonResponse(['success' => true, 'message' => t('saved_success')]);
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
$payment_date = parseAppDate(post('payment_date'));
$note = post('note');
if ($payment_date === null) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}
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

$existingUnitType = !empty($existing['flat_id'])
    ? ((new Flat())->find((int)$existing['flat_id'])['unit_type'] ?? 'flat')
    : 'flat';
if (isShopLikeUnit($existingUnitType)) {
    $receipt_type = 'rent';
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
    // Only exclude this payment's old value when it still belongs to the
    // month being edited. If the edit moves it to a different lease/month,
    // it is not part of that month's sum, and subtracting it anyway drove
    // $otherPaid negative - which understated the cap and let the invoice be
    // overpaid.
    $staysInMonth = (int)$existing['lease_id'] === (int)$lease_id
        && (int)$existing['month'] === (int)$month
        && (int)$existing['year'] === (int)$year;
    $otherPaid = $paymentModel->sumForInvoice($lease_id, $month, $year)
        - ($staysInMonth ? (float)$existing['total_amount'] : 0);
    $otherPaid = max(0, $otherPaid);
    $totalDue = (float)$invoice['total_due'];
    if ($total_amount + $otherPaid > $totalDue + 0.009) {
        // Report both figures, because the old wording showed only the invoice
        // total labelled "remaining due". With a single receipt in the month
        // that number is the whole invoice, so it read as 40,000 of spare
        // headroom at the exact moment the real headroom was zero. Stating the
        // limit and what this edit would push the month to makes the refusal
        // self-explanatory.
        $collected = $total_amount + $otherPaid;
        jsonResponse([
            'success' => false,
            'message' => strtr(t('over_invoice_limit'), [
                '{total}'     => money($totalDue),
                '{collected}' => money($collected),
            ]),
        ]);
    }
}

// Service charge receipt scan (flat rent only): a newly uploaded file
// replaces the old one; remove_sc=1 deletes it.
$scValue = null; // null = leave untouched
if ($receipt_type === 'rent' && !empty($existing['flat_id'])) {
    $unitType = (new Flat())->find((int)$existing['flat_id'])['unit_type'] ?? 'flat';
    if ($unitType === 'flat') {
        if (post('remove_sc') === '1') {
            if (!empty($existing['service_charge_file'])) {
                deleteUploadedDocument($existing['service_charge_file']);
            }
            $scValue = '';
        } elseif (!empty($_FILES['service_charge_file'])
            && ($_FILES['service_charge_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $path = uploadDocument($_FILES['service_charge_file'], 'service_charge', $id);
            if ($path === false) {
                jsonResponse(['success' => false, 'message' => t('invalid_file')]);
            }
            if (!empty($existing['service_charge_file'])) {
                deleteUploadedDocument($existing['service_charge_file']);
            }
            $scValue = $path;
        }
    }
}

// Whether this payment carries a service charge receipt scan (flat rent only):
// the month/year the scan covers are only meaningful for such payments.
$isFlatRent = $receipt_type === 'rent' && !empty($existing['flat_id'])
    && ((new Flat())->find((int)$existing['flat_id'])['unit_type'] ?? 'flat') === 'flat';

$data = [
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
    'note' => $note,
    // Note block styling (color + font chosen for this payment's receipt).
    'note_color' => $note_color !== '' ? $note_color : null,
    'note_font' => $note_font !== '' ? $note_font : null,
    // Months the service charge receipt scan covers; the receipt label shows
    // these instead of auto-computing the previous month when they are set.
    'service_charge_month' => $isFlatRent && !empty($service_charge_month) && $service_charge_year >= 2000 ? monthListToDb($service_charge_month) : null,
    'service_charge_year' => $isFlatRent && $service_charge_year >= 2000 ? $service_charge_year : null
];
if ($scValue !== null) {
    $data['service_charge_file'] = $scValue === '' ? null : $scValue;
}

$paymentModel->update($id, $data);

$paymentModel->reconcileInvoice($lease_id, $month, $year);

jsonResponse(['success' => true, 'message' => t('saved_success')]);
