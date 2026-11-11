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

$payment_date = parseAppDate(post('payment_date'));
if ($payment_date === null) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$paymentModel = new Payment();
$payment = $paymentModel->find($id);
if (!$payment) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}
// Landlords may only mark paid payments of tenants they entered.
if (!tenantAccessible((int)$payment['tenant_id'])) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}

$remaining = $paymentModel->markFullyPaid(
    (int)$payment['lease_id'],
    (int)$payment['month'],
    (int)$payment['year'],
    $method,
    $payment_date
);

jsonResponse(['success' => true, 'message' => t('saved_success')]);
