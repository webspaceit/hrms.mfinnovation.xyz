<?php
// ============================================================
// Reminder settings (admin only).
//
//   GET                        -> current settings + key state
//   POST save                   -> reminder_enabled, days, repeat, max,
//                                  channels, cron token
//   POST action=generate_keys  -> create the VAPID key pair (once)
//   POST action=test_push      -> send a test notification to every
//                                  subscribed device and report counts
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();

if (Auth::role() !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}

$keys = [
    'reminder_enabled', 'reminder_days_before', 'reminder_repeat_days',
    'reminder_max', 'reminder_channels', 'reminder_cron_token', 'push_subject',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)post('action', 'save');

    if ($action === 'generate_keys') {
        $pair = WebPush::generateKeys();
        setSetting('push_vapid_public', $pair['public']);
        setSetting('push_vapid_private', $pair['private']);
        if (trim((string)post('push_subject', '')) !== '') {
            setSetting('push_subject', post('push_subject'));
        }
        jsonResponse([
            'success' => true,
            'message' => t('keys_generated'),
            'data'    => ['has_keys' => true],
        ]);
    }

    if ($action === 'test_push') {
        if (trim((string)getSetting('push_vapid_private', '')) === '') {
            jsonResponse(['success' => false, 'message' => t('push_not_configured')]);
        }
        $payload = json_encode([
            'title' => t('reminder_test_title'),
            'body'  => t('reminder_test_body'),
            'url'   => BASE_URL . 'dashboard',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $result = (new WebPush())->sendToAllSubscriptions($payload);
        jsonResponse([
            'success' => true,
            'message' => sprintf(
                t('push_test_result'),
                $result['sent'],
                $result['failed'],
                $result['pruned'],
                $result['total']
            ),
            'data' => $result,
        ]);
    }

    // ---- save ----
    foreach ($keys as $k) {
        if (array_key_exists($k, $_POST)) {
            setSetting($k, trim((string)$_POST[$k]));
        }
    }
    // Normalise the numbers so a blank field cannot disable the schedule.
    if ((int)getSetting('reminder_days_before', 3) < 0) {
        setSetting('reminder_days_before', '3');
    }
    if ((int)getSetting('reminder_max', 3) < 1) {
        setSetting('reminder_max', '3');
    }

    jsonResponse([
        'success' => true,
        'message' => t('reminders_saved'),
        'data'    => reminderSettingsPayload(),
    ]);
}

jsonResponse(['success' => true, 'data' => reminderSettingsPayload()]);

/**
 * Read straight from $_POST-independent state after a save, so the caller
 * always gets what is really stored (getSetting caches per request).
 */
function reminderSettingsPayload(): array {
    $stored = function (string $key, string $default = ''): string {
        $v = Database::getInstance()->fetchColumn(
            "SELECT setting_value FROM settings WHERE setting_key = :k",
            ['k' => $key]
        );
        return $v === null ? $default : (string)$v;
    };

    $reminder = new Reminder();
    $settings = $reminder->settings();
    $labels = [];
    foreach ($reminder->slots($settings) as $offset => $stage) {
        $labels[] = $reminder->stageLabel($stage, (int)$offset, $settings);
    }

    return [
        'reminder_enabled'      => $stored('reminder_enabled', '0'),
        'reminder_days_before'  => $stored('reminder_days_before', '3'),
        'reminder_repeat_days'  => $stored('reminder_repeat_days', '7'),
        'reminder_max'          => $stored('reminder_max', '3'),
        'reminder_channels'     => $stored('reminder_channels', 'push,whatsapp,email'),
        'reminder_cron_token'   => $stored('reminder_cron_token', ''),
        'push_subject'          => $stored('push_subject', ''),
        'has_keys'              => $stored('push_vapid_private', '') !== '',
        'schedule'              => implode(' · ', $labels),
        'last_run'              => $stored('reminder_last_run', ''),
    ];
}
