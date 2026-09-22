<?php
// ============================================================
// User AJAX - Reset password (admin resets another account)
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireAdmin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$password = (string)($_POST['password'] ?? '');
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')], 400);
}
if (mb_strlen($password) < 6) {
    jsonResponse(['success' => false, 'message' => t('password_min_length')]);
}

$userModel = new User();
if (!$userModel->find($id)) {
    jsonResponse(['success' => false, 'message' => 'User not found'], 404);
}

$userModel->update($id, ['password' => password_hash($password, PASSWORD_DEFAULT)]);
jsonResponse(['success' => true, 'message' => t('password_reset_success')]);