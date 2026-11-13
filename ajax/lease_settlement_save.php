<?php
// Confirm and apply lease-end settlement.
require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$leaseId = (int)post('id', 0);
$lease = (new Lease())->findWithDetails($leaseId);
if (!$lease) {
    jsonResponse(['success' => false, 'message' => t('lease_not_found')], 404);
}
if (!tenantAccessible((int)$lease['tenant_id'])) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}

try {
    (new LeaseSettlement())->settle($leaseId, Auth::id());
    jsonResponse(['success' => true, 'message' => t('settlement_saved')]);
} catch (DomainException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Lease settlement failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => t('error_occurred')], 500);
}
