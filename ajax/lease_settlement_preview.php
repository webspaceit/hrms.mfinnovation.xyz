<?php
// Read-only lease-end settlement preview.
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
    $data = (new LeaseSettlement())->preview($leaseId);
    $data['lease']['tenant_name'] = localizeName($data['lease']['tenant_name'] ?? '');
    $data['lease']['building_name'] = localizeText($data['lease']['building_name'] ?? '');
    $data['lease']['flat_no'] = bnFlatCode($data['lease']['flat_no'] ?? '');
    unset($data['rows'], $data['final_invoice'], $data['payment_credit_applied']);
    jsonResponse(['success' => true, 'data' => $data]);
} catch (DomainException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Lease settlement preview failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => t('error_occurred')], 500);
}
