<?php
// ============================================================
// Invoice AJAX - Delete an invoice
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
// Landlords may only delete invoices of tenants they entered.
if (!tenantAccessible((int)$inv['tenant_id'])) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}
$invoiceModel->delete($id);

jsonResponse(['success' => true, 'message' => t('deleted_success')]);