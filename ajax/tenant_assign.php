<?php
// ============================================================
// Tenant AJAX - Assign / reassign a tenant's owner (admin only)
// Used by the dashboard "Assign tenants to landlords" widget.
// created_by = 0 removes the owner (tenant becomes admin-only).
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if (!Auth::check() || Auth::role() !== 'admin') {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$ownerId = (int)post('created_by', 0);

if (!$id) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$tenantModel = new Tenant();
$tenant = $tenantModel->find($id);
if (!$tenant) {
    jsonResponse(['success' => false, 'message' => 'Not found']);
}

$newOwner = null;
if ($ownerId > 0) {
    $user = (new User())->find($ownerId);
    if (!$user) {
        jsonResponse(['success' => false, 'message' => t('error_occurred')]);
    }
    $newOwner = $ownerId;
}

$tenantModel->update($id, ['created_by' => $newOwner]);

jsonResponse([
    'success' => true,
    'message' => t('saved_success'),
    'created_by' => $newOwner,
]);