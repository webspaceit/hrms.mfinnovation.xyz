<?php
// ============================================================
// Lease AJAX - Save (create/update)
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$tenant_id = (int)post('tenant_id', 0);
$flat_id = (int)post('flat_id', 0);
$start_date = bnToEnDigits(post('start_date'));
$end_date = bnToEnDigits(post('end_date'));
$start_date = date('Y-m-d', strtotime($start_date));
$end_date = $end_date ? date('Y-m-d', strtotime($end_date)) : '';
$rent_amount = (float)bnToEnDigits(post('rent_amount', 0));
$utility_fee = (float)bnToEnDigits(post('utility_fee', 0));
$advance_amount = (float)bnToEnDigits(post('advance_amount', 0));

if (empty($tenant_id) || empty($flat_id) || empty($start_date)) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$leaseModel = new Lease();
$flatModel = new Flat();

// Landlords may only make / edit leases for tenants they entered.
if ($id > 0) {
    $existing = $leaseModel->find($id);
    if (!$existing || !tenantAccessible((int)$existing['tenant_id']) || !tenantAccessible($tenant_id)) {
        jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
    }
} elseif (!tenantAccessible($tenant_id)) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}

// Check flat not already leased (when creating new)
if ($id == 0) {
    if ($leaseModel->hasActiveLeaseForFlat($flat_id)) {
        jsonResponse(['success' => false, 'message' => t('error_occurred') . ': ' . t('occupied')]);
    }
} else {
    if ($leaseModel->hasActiveLeaseForFlat($flat_id, $id)) {
        jsonResponse(['success' => false, 'message' => t('error_occurred') . ': ' . t('occupied')]);
    }
}

$data = [
    'tenant_id' => $tenant_id,
    'flat_id' => $flat_id,
    'start_date' => $start_date,
    'end_date' => $end_date ?: null,
    'rent_amount' => $rent_amount,
    'utility_fee' => $utility_fee,
    'advance_amount' => $advance_amount,
    'status' => 'active'
];

if ($id > 0) {
    $leaseModel->update($id, $data);
    $flatModel->refreshStatus($flat_id);
    jsonResponse(['success' => true, 'message' => t('updated_success')]);
} else {
    $leaseModel->create($data);
    $flatModel->refreshStatus($flat_id);
    jsonResponse(['success' => true, 'message' => t('added_success')]);
}
