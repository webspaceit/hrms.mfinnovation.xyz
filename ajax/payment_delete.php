<?php
// ============================================================
// Payment AJAX - Delete
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(post('id'))) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$paymentModel = new Payment();
$payment = $paymentModel->find((int)post('id'));

$paymentModel->delete((int)post('id'));

// Recompute the month's invoice status after the installment is removed.
if ($payment) {
    $paymentModel->reconcileInvoice((int)$payment['lease_id'], (int)$payment['month'], (int)$payment['year']);
}

jsonResponse(['success' => true, 'message' => t('deleted_success')]);