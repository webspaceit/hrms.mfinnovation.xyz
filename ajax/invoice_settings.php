<?php
// ============================================================
// Invoice AJAX - Read / save delivery settings (SMTP + SMS + cron)
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();

$keys = [
    'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from',
    'smtp_from_name', 'smtp_encryption',
    'sms_api_url', 'sms_api_key', 'sms_sender_id',
    'wa_api_url', 'wa_api_key', 'wa_sender_id',
    'invoice_cron_token',
    'invoice_auto_enabled', 'invoice_send_before_day', 'invoice_auto_channels'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    foreach ($keys as $k) {
        $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
        setSetting($k, $v);
    }
    jsonResponse(['success' => true, 'message' => t('saved_success')]);
}

$out = [];
foreach ($keys as $k) {
    $out[$k] = (string)getSetting($k, '');
}
jsonResponse(['success' => true, 'data' => $out]);