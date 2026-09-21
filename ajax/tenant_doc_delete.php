<?php
// ============================================================
// Tenant AJAX - Delete single document (deed / nid)
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$type = post('type', '');
$col = $type === 'deed' ? 'deed_file' : ($type === 'nid' ? 'nid_file' : null);

if (!$id || !$col) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$tenantModel = new Tenant();
$tenant = $tenantModel->find($id);
if (!$tenant) {
    jsonResponse(['success' => false, 'message' => 'Not found']);
}

deleteUploadedDocument($tenant[$col] ?? '');
$tenantModel->update($id, [$col => null]);

jsonResponse(['success' => true, 'message' => t('deleted_success')]);