<?php
// ============================================================
// Tenant AJAX - Delete
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(post('id'))) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$tenantModel = new Tenant();
$tenant = $tenantModel->find((int)post('id'));
if ($tenant) {
    deleteUploadedDocument($tenant['deed_file'] ?? '');
    deleteUploadedDocument($tenant['nid_file'] ?? '');
}
$tenantModel->delete((int)post('id'));

jsonResponse(['success' => true, 'message' => t('deleted_success')]);
