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
// Landlords may only edit invoices of tenants they entered.
if (!tenantAccessible((int)$inv['tenant_id'])) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
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

// Optional per-invoice due date. Store as Y-m-d; empty clears it and the
// invoice view falls back to the last day of the invoice month.
$dueDateInput = trim((string)post('due_date', ''));
$dueDate = null;
if ($dueDateInput !== '') {
    $dueTs = strtotime(bnToEnDigits($dueDateInput));
    if ($dueTs === false) {
        jsonResponse(['success' => false, 'message' => t('invalid_date')]);
    }
    $dueDate = date('Y-m-d', $dueTs);
}

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
    'due_date' => $dueDate,
    'is_manual' => 1,
]);

// Changing the total changes what is owed, so the stored status has to be
// re-derived from the payments. Without this, raising a bill that was already
// settled leaves it flagged "Paid" while paid_amount sits below total_due - the
// dashboard then understates what is outstanding. A manual status override is
// left untouched, since it is meant to win over the computed value.
(new Payment())->reconcileInvoice((int)$inv['lease_id'], (int)$inv['month'], (int)$inv['year']);

jsonResponse(['success' => true, 'message' => t('saved_success')]);