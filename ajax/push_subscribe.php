<?php
// ============================================================
// Save the browser push subscription of the logged-in tenant.
//
// POST (form field `subscription`, the JSON from PushManager) +
// csrf_token. One tenant may register several devices.
// ============================================================

require_once __DIR__ . '/../classes/init.php';

if (!Auth::checkTenant()) {
    jsonResponse(['success' => false, 'message' => 'Not signed in'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'POST required'], 405);
}

csrf_require();

$raw = (string)($_POST['subscription'] ?? '');
$sub = json_decode($raw, true);
if (!is_array($sub)) {
    jsonResponse(['success' => false, 'message' => 'Invalid subscription'], 422);
}

$endpoint = (string)($sub['endpoint'] ?? '');
$p256dh = (string)($sub['keys']['p256dh'] ?? '');
$auth = (string)($sub['keys']['auth'] ?? '');

// Push endpoints are always HTTPS. Plain HTTP is only tolerated for a
// loopback address so the local fake push service used by the tests works.
$isHttps = strpos($endpoint, 'https://') === 0;
$isLoopback = preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?/#', $endpoint) === 1;
if (!$isHttps && !$isLoopback) {
    jsonResponse(['success' => false, 'message' => 'Invalid endpoint'], 422);
}
if (strlen(WebPush::b64uDecode($p256dh)) !== 65 || strlen(WebPush::b64uDecode($auth)) !== 16) {
    jsonResponse(['success' => false, 'message' => 'Invalid subscription keys'], 422);
}

// A tenant may only own the devices they are signed in on.
$tenantId = (int)Auth::tenantId();
$model = new PushSubscription();

// Stop this endpoint from leaking between accounts if a previous session
// on the same browser was a different tenant: re-registering rebinds it.
$model->register(
    $tenantId,
    $endpoint,
    $p256dh,
    $auth,
    $_SERVER['HTTP_USER_AGENT'] ?? ''
);

jsonResponse([
    'success' => true,
    'message' => t('reminder_subscribed'),
    'devices' => $model->countForTenant($tenantId),
]);
