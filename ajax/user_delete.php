<?php
// ============================================================
// User AJAX - Delete account
// Admin only. Cannot delete yourself; at least one admin stays.
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireAdmin();
csrf_require();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')], 400);
}
if ($id === (int)Auth::id()) {
    jsonResponse(['success' => false, 'message' => t('cannot_delete_self')]);
}

$userModel = new User();
$target = $userModel->find($id);
if (!$target) {
    jsonResponse(['success' => false, 'message' => 'User not found'], 404);
}
if ((string)$target['role'] === 'admin' && $userModel->countAdmins() <= 1) {
    jsonResponse(['success' => false, 'message' => t('last_admin_required')]);
}

$userModel->delete($id);
jsonResponse(['success' => true, 'message' => t('deleted_success', 'Deleted successfully')]);