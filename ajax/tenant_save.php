<?php
// ============================================================
// Tenant AJAX - Save (create/update)
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$name = storeName(post('name'));
$phone = bnToEnDigits(post('phone'));
$email = post('email');
$nid = bnToEnDigits(post('nid'));
$address = storeText(post('address'));
$status = post('status', 'active');

if (empty($name) || empty($phone)) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$tenantModel = new Tenant();
$data = [
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'nid' => $nid,
    'address' => $address,
    'status' => $status,
    'portal_username' => ($pu = trim(post('portal_username'))) !== '' ? $pu : null,
    'portal_enabled' => isset($_POST['portal_enabled']) && $_POST['portal_enabled'] ? 1 : 0,
];

if (trim(post('portal_username')) !== '' && $tenantModel->portalUsernameTaken(trim(post('portal_username')), $id)) {
    jsonResponse(['success' => false, 'message' => t('portal_username_taken', 'This portal username is already used by another tenant')]);
}

$newPortalPassword = (string)($_POST['portal_password'] ?? '');
if ($newPortalPassword !== '') {
    if (mb_strlen($newPortalPassword) < 6) {
        jsonResponse(['success' => false, 'message' => t('password_min_length')]);
    }
    $data['portal_password'] = password_hash($newPortalPassword, PASSWORD_DEFAULT);
} elseif ($id <= 0 && trim(post('portal_username')) !== '') {
    // New portal account without a password yet: keep the hash empty so
    // login stays impossible until the office sets a password.
    $data['portal_password'] = null;
}

if ($id > 0) {
    $tenantModel->update($id, $data);
    $tenantId = $id;
    $created = false;
} else {
    $tenantId = (int)$tenantModel->create($data);
    $created = true;
}

// Keep the unit's Advance (flats.advance_amount) in sync with the Edit
// Tenant form. The value lives on the unit (Flats page) and is fetched
// back into the modal via tenant_get.php. Skip when the tenant has no unit.
$flatModel = new Flat();
$unit = $tenantModel->currentUnit($tenantId);
if ($unit) {
    $advanceRaw = trim((string)post('advance_amount', ''));
    $flatModel->update((int)$unit['flat_id'], [
        'advance_amount' => $advanceRaw !== '' ? (float)bnToEnDigits($advanceRaw) : null
    ]);
}

foreach (['deed_file' => 'deed', 'nid_file' => 'nid'] as $field => $kind) {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    $path = uploadDocument($_FILES[$field], $kind, $tenantId);
    if ($path === false) {
        if ($created) {
            $tenantModel->delete($tenantId);
        }
        jsonResponse(['success' => false, 'message' => t('invalid_file')]);
    }
    $current = $tenantModel->find($tenantId);
    if ($current && !empty($current[$field])) {
        deleteUploadedDocument($current[$field]);
    }
    $tenantModel->update($tenantId, [$field => $path]);
}

jsonResponse(['success' => true, 'message' => $id > 0 ? t('updated_success') : t('added_success')]);
