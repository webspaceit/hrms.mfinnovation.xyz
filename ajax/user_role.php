<?php
// ============================================================
// User AJAX - Change role from the inline dropdown
// Admin only. Own role cannot be changed; last admin protected.
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireAdmin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$role = post('role', 'landlord');
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')], 400);
}
if ($role !== 'admin' && $role !== 'landlord') {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}
if ($id === (int)Auth::id()) {
    jsonResponse(['success' => false, 'message' => t('cannot_change_own_role')]);
}

$userModel = new User();
$result = $userModel->updateRole($id, $role);
if (!$result['success']) {
    jsonResponse(['success' => false, 'message' => $result['message']]);
}

jsonResponse(['success' => true, 'message' => t('updated_success')]);