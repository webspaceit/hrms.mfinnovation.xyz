<?php
// ============================================================
// Lease AJAX - Delete
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(post('id'))) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$leaseId = (int)post('id');
$leaseModel = new Lease();
$flatModel = new Flat();

$lease = $leaseModel->find($leaseId);
if (!$lease) {
    jsonResponse(['success' => false, 'message' => 'Not found']);
}
// Landlords may only delete leases of tenants they entered.
if (!tenantAccessible((int)$lease['tenant_id'])) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}
if ($lease) {
    $leaseModel->delete($leaseId);
    $flatModel->refreshStatus($lease['flat_id']);
}

jsonResponse(['success' => true, 'message' => t('deleted_success')]);
