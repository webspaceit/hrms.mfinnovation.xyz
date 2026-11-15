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

// Landlords may only add/edit the tenants they entered; admins manage everyone.
if ($id > 0 && !tenantAccessible($id)) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}

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
    // Admins may reassign the tenant's owner from the edit modal.
    if (Auth::role() === 'admin') {
        $ownerId = (int)post('created_by', 0);
        if ($ownerId > 0 && (new User())->find($ownerId)) {
            $data['created_by'] = $ownerId;
        }
    }
    $tenantModel->update($id, $data);
    $tenantId = $id;
    $created = false;
} else {
    // A new tenant is owned by whoever enters it (landlords can then see it).
    $data['created_by'] = Auth::id();
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

// deed / nid accept images or PDF; the tenant photo is image-only.
// $column matters: the form field is photo_file but the DB column is `photo`
// (writing $field straight through threw "Unknown column 'photo_file'").
$uploads = [
    'deed_file'  => ['deed',  false, 'deed_file'],
    'nid_file'   => ['nid',   false, 'nid_file'],
    'photo_file' => ['photo', true,  'photo'],
];
foreach ($uploads as $field => $spec) {
    list($kind, $imagesOnly, $column) = $spec;
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    $path = uploadDocument($_FILES[$field], $kind, $tenantId, $imagesOnly);
    if ($path === false) {
        if ($created) {
            $tenantModel->delete($tenantId);
        }
        // UPLOAD_ERR_INI_SIZE / FORM_SIZE mean php.ini's upload_max_filesize or
        // post_max_size rejected it before our 20 MB check ever ran.
        $code = (int)($_FILES[$field]['error'] ?? 0);
        $msg  = in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? t('file_too_large')
            : ($kind === 'photo' ? t('invalid_photo') : t('invalid_file'));
        jsonResponse(['success' => false, 'message' => $msg]);
    }
    $current = $tenantModel->find($tenantId);
    if ($current && !empty($current[$column])) {
        deleteUploadedDocument($current[$column]);
    }
    $tenantModel->update($tenantId, [$column => $path]);
}

jsonResponse(['success' => true, 'message' => $id > 0 ? t('updated_success') : t('added_success')]);
