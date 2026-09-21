<?php
// ============================================================
// Invoice AJAX - Deliver a single invoice via a channel
// channel: email | whatsapp | sms
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$channel = post('channel', 'email');
if (!in_array($channel, ['email', 'whatsapp', 'sms'])) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}
if (!$id) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$invoiceModel = new Invoice();
$inv = $invoiceModel->getWithDetails($id);
if (!$inv) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$result = $invoiceModel->deliver($id, $channel);
jsonResponse([
    'success' => $result['success'],
    'message' => $result['message'],
    'link' => $result['link']
], $result['success'] ? 200 : 400);