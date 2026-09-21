<?php
// ============================================================
// Payment AJAX - Mark a month's bill fully paid
// Records the remaining due as an installment and sets invoice status 'paid'
// (mirrors the reference app's markPaid action).
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$method = post('payment_method', 'cash');
if (!in_array($method, ['cash', 'bank', 'bKash', 'nogod', 'rocket', 'other'])) {
    $method = 'cash';
}

$payment_date_raw = trim((string)bnToEnDigits(post('payment_date')));
$payment_date_ts = $payment_date_raw !== '' ? strtotime($payment_date_raw) : false;
$payment_date = $payment_date_ts !== false ? date('Y-m-d', $payment_date_ts) : null;

$paymentModel = new Payment();
$payment = $paymentModel->find($id);
if (!$payment) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$remaining = $paymentModel->markFullyPaid(
    (int)$payment['lease_id'],
    (int)$payment['month'],
    (int)$payment['year'],
    $method,
    $payment_date
);

jsonResponse(['success' => true, 'message' => t('marked_fully_paid')]);