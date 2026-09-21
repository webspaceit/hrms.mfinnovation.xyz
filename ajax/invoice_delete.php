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
$invoiceModel->delete($id);

jsonResponse(['success' => true, 'message' => t('deleted_success')]);