<?php
// ============================================================
// Invoice AJAX - Manual edit (amounts/note) or reset to calculated
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
if (!$id) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$invoiceModel = new Invoice();
$inv = $invoiceModel->find($id);
if (!$inv) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

// Reset: revert to calculated values on next generate
if (post('reset', '') !== '') {
    $invoiceModel->update($id, ['is_manual' => 0]);
    jsonResponse(['success' => true, 'message' => t('reset_calc') . ' - ' . t('generate_invoices')]);
}

$rent = (float)bnToEnDigits(post('rent_amount', 0));
$utility = (float)bnToEnDigits(post('utility_fee', 0));
$parking = (float)bnToEnDigits(post('parking_amount', 0));
$gas = (float)bnToEnDigits(post('gas_amount', 0));
$water = (float)bnToEnDigits(post('water_fee', 0));
$waste = (float)bnToEnDigits(post('waste_fee', 0));
$arrears = (float)bnToEnDigits(post('arrears', 0));
$arrearsMonths = max(0, (int)bnToEnDigits(post('arrears_months', 0)));
$note = mb_substr(trim(post('note')), 0, 255);

// Utility fee is a screen-only reference line (shown on the invoice view,
// never billed) — the invoice total is rent + service bills + arrears.
$totalDue = $rent + $parking + $gas + $water + $waste + $arrears;

$invoiceModel->update($id, [
    'rent_amount' => $rent,
    'utility_fee' => $utility,
    'parking_amount' => $parking,
    'gas_amount' => $gas,
    'water_fee' => $water,
    'waste_fee' => $waste,
    'arrears' => $arrears,
    'arrears_months' => $arrearsMonths,
    'total_due' => $totalDue,
    'note' => $note !== '' ? $note : null,
    'is_manual' => 1,
]);

jsonResponse(['success' => true, 'message' => t('saved_success')]);