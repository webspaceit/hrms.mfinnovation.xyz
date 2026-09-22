<?php
// ============================================================
// User AJAX - Save (create/update account)
// Admin only. Own role cannot be changed; last admin protected.
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireAdmin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$username = trim(post('username'));
$full_name = trim(post('full_name'));
$email = trim(post('email'));
$role = post('role', 'landlord');
$password = (string)($_POST['password'] ?? '');

if (empty($username) || empty($full_name) || (string)$username === '') {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}
if ($role !== 'admin' && $role !== 'landlord') {
    $role = 'landlord';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => t('invalid_email')]);
}

$userModel = new User();

// Username / email must not be used by another account.
if ($userModel->usernameTaken($username, $id)) {
    jsonResponse(['success' => false, 'message' => t('username_taken')]);
}

// Changing your own role is blocked to avoid locking yourself out.
if ($id > 0 && $id === (int)Auth::id()) {
    $current = $userModel->find($id);
    if ($current && (string)($current['role'] ?? '') !== $role) {
        jsonResponse(['success' => false, 'message' => t('cannot_change_own_role')]);
    }
}

$data = [
    'username'  => $username,
    'full_name' => $full_name,
    'email'     => $email, // '' is stored, not NULL (column is NOT NULL)
    'role'      => $role,
];

if ($id > 0) {
    if ($password !== '') {
        if (mb_strlen($password) < 6) {
            jsonResponse(['success' => false, 'message' => t('password_min_length')]);
        }
        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
    }
    // Demotion of the last admin is blocked in the model.
    $result = $userModel->updateRole($id, $role);
    if (!$result['success']) {
        jsonResponse(['success' => false, 'message' => $result['message']]);
    }
    unset($data['role']);
    if ($data) {
        $userModel->update($id, $data);
    }
    jsonResponse(['success' => true, 'message' => t('updated_success')]);
}

// ---- Create ----
if (mb_strlen($password) < 6) {
    jsonResponse(['success' => false, 'message' => t('password_min_length')]);
}
$data['password'] = password_hash($password, PASSWORD_DEFAULT);
$userModel->create($data);
jsonResponse(['success' => true, 'message' => t('added_success')]);