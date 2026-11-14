<?php
// ============================================================
// Remove the browser push subscription of the logged-in tenant.
// POST { endpoint } + csrf_token. Scoped to the tenant, so one
// tenant can never delete another tenant's device.
// ============================================================

require_once __DIR__ . '/../classes/init.php';

if (!Auth::checkTenant()) {
    jsonResponse(['success' => false, 'message' => 'Not signed in'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'POST required'], 405);
}

csrf_require();

$endpoint = trim((string)($_POST['endpoint'] ?? ''));
if ($endpoint === '') {
    jsonResponse(['success' => false, 'message' => 'Missing endpoint'], 422);
}

$tenantId = (int)Auth::tenantId();
$model = new PushSubscription();
$model->removeByEndpoint($endpoint, $tenantId);

jsonResponse([
    'success' => true,
    'message' => t('reminder_unsubscribed'),
    'devices' => $model->countForTenant($tenantId),
]);
