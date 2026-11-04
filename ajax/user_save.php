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
// Store the name exactly as typed — users are system accounts (admin/landlord),
// not tenant records that need bilingual display. Storing as-is means Bengali
// names stay Bengali and English names stay English without any API round-trip.
$full_name = trim(post('full_name'));
$email = trim(post('email'));
$password = (string)($_POST['password'] ?? '');

// A disabled <select> is not posted by the browser, so "role" can be absent
// when an admin edits their own account. Absent means "leave the role alone",
// not "demote to landlord" - otherwise editing yourself always failed and no
// other field (username, name, email) was saved either.
$hasRole = array_key_exists('role', $_POST) && (string)post('role') !== '';
$role = $hasRole ? post('role') : '';

if (empty($username) || empty($full_name) || (string)$username === '') {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}
if ($role !== '' && $role !== 'admin' && $role !== 'landlord') {
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
if ($id > 0 && $hasRole && $id === (int)Auth::id()) {
    $current = $userModel->find($id);
    if ($current && (string)($current['role'] ?? '') !== $role) {
        jsonResponse(['success' => false, 'message' => t('cannot_change_own_role')]);
    }
}

$data = [
    'username'  => $username,
    'full_name' => $full_name,
    'email'     => $email, // '' is stored, not NULL (column is NOT NULL)
];

if ($id > 0) {
    if ($password !== '') {
        if (mb_strlen($password) < 6) {
            jsonResponse(['success' => false, 'message' => t('password_min_length')]);
        }
        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
    }
    // Demotion of the last admin is blocked in the model. Skip the role
    // round-trip entirely when the field was not posted (own account).
    if ($hasRole) {
        $result = $userModel->updateRole($id, $role);
        if (!$result['success']) {
            jsonResponse(['success' => false, 'message' => $result['message']]);
        }
    }
    if ($data) {
        $userModel->update($id, $data);
    }
    jsonResponse(['success' => true, 'message' => t('updated_success')]);
}

// ---- Create ----
if (mb_strlen($password) < 6) {
    jsonResponse(['success' => false, 'message' => t('password_min_length')]);
}
if (!$hasRole) {
    $role = 'landlord';
}
$data['role'] = $role;
$data['password'] = password_hash($password, PASSWORD_DEFAULT);
$userModel->create($data);
jsonResponse(['success' => true, 'message' => t('added_success')]);