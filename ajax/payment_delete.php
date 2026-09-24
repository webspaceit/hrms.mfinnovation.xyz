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

// Landlords may only delete payments of tenants they entered.
if (!$payment || !tenantAccessible((int)$payment['tenant_id'])) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}

// Remove the attached service-charge receipt scan (if any) from disk.
if (!empty($payment['service_charge_file'])) {
    deleteUploadedDocument($payment['service_charge_file']);
}

$paymentModel->delete((int)post('id'));

// Recompute the month's invoice status after the installment is removed.
if ($payment) {
    $paymentModel->reconcileInvoice((int)$payment['lease_id'], (int)$payment['month'], (int)$payment['year']);
}

jsonResponse(['success' => true, 'message' => t('deleted_success')]);