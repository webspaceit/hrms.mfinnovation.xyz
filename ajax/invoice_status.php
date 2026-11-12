<?php
// ============================================================
// Invoice AJAX - Manual payment status override
// (status 'auto' clears the override and returns to computed)
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$paymentId = (int)post('payment_id', 0);
if (!$id && !$paymentId) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$status = post('status', 'auto');
if (!in_array($status, ['paid', 'partial', 'unpaid', 'auto'], true)) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$invoiceModel = new Invoice();
$paymentModel = new Payment();

// A receipt can exist for a month with no invoice - rent collected before the
// lease started, which generateForMonth() deliberately skips. The status is
// stored on the invoice, so back-fill one from the payment before editing it.
if (!$id && $paymentId) {
    $payment = $paymentModel->find($paymentId);
    if (!$payment || !tenantAccessible((int)$payment['tenant_id'])) {
        jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
    }
    $created = $invoiceModel->ensureForPayment($payment);
    if (!$created) {
        jsonResponse(['success' => false, 'message' => t('error_occurred')]);
    }
    $id = (int)$created['id'];
}

$inv = $invoiceModel->find($id);
if (!$inv) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}
// Landlords may only change the status of invoices of tenants they entered.
if (!tenantAccessible((int)$inv['tenant_id'])) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}

$payment_date_raw = trim((string)post('payment_date'));
$payment_date = $payment_date_raw === '' ? null : parseAppDate($payment_date_raw);
if ($payment_date_raw !== '' && $payment_date === null) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$payment = null;
if ($status === 'paid' && $inv['lease_id'] && $paymentId) {
    $payment = $paymentModel->find($paymentId);
    if (!$payment || !tenantAccessible((int)$payment['tenant_id'])) {
        jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
    }
    if ((int)$payment['lease_id'] !== (int)$inv['lease_id']
        || (int)$payment['month'] !== (int)$inv['month']
        || (int)$payment['year'] !== (int)$inv['year']) {
        jsonResponse(['success' => false, 'message' => t('error_occurred')]);
    }
}

$invoiceModel->update($id, [
    'payment_status_override' => $status === 'auto' ? null : $status,
]);

if ($status === 'paid' && $inv['lease_id']) {
    $leaseId = (int)$inv['lease_id'];
    $month = (int)$inv['month'];
    $year = (int)$inv['year'];
    $remaining = max(0, (float)$inv['total_due'] - $paymentModel->sumForInvoice($leaseId, $month, $year));
    if ($payment && $payment_date !== null && $remaining <= 0.009) {
        // When the invoice is already fully paid, edit the specific payment
        // row that the user selected instead of guessing which installment is latest.
        $paymentModel->update((int)$payment['id'], ['payment_date' => $payment_date]);
        $invoiceModel->update($id, ['paid_at' => $payment_date . ' 12:00:00']);
    } else {
        $paymentModel->markFullyPaid($leaseId, $month, $year, 'cash', $payment_date);
    }
}

jsonResponse(['success' => true, 'message' => t('saved_success')]);
