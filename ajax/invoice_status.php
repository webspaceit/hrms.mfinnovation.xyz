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
if (!$id) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$status = post('status', 'auto');
if (!in_array($status, ['paid', 'partial', 'unpaid', 'auto'], true)) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$invoiceModel = new Invoice();
$inv = $invoiceModel->find($id);
if (!$inv) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$payment_date_raw = trim((string)bnToEnDigits(post('payment_date')));
$payment_date_ts = $payment_date_raw !== '' ? strtotime($payment_date_raw) : false;
$payment_date = $payment_date_ts !== false ? date('Y-m-d', $payment_date_ts) : null;

$invoiceModel->update($id, [
    'payment_status_override' => $status === 'auto' ? null : $status,
]);

if ($status === 'paid' && $inv['lease_id']) {
    $paymentModel = new Payment();
    $paymentModel->markFullyPaid(
        (int)$inv['lease_id'],
        (int)$inv['month'],
        (int)$inv['year'],
        'cash',
        $payment_date
    );
}

jsonResponse(['success' => true, 'message' => t('saved_success')]);