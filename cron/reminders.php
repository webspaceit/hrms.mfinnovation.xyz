<?php
// ============================================================
// Due-date reminder cron - run ONCE A DAY
//
//   CLI:   php cron/reminders.php <token>
//   HTTP:  cron/reminders.php?token=<token>
//
// The token is reminder_cron_token (Settings -> Rent reminders);
// if that is empty the invoice_cron_token is accepted instead.
//
// Optional:
//   ?today=2026-11-15   pretend it is another day (testing)
//   ?dry=1 / --dry      print the plan, send nothing
//   ?push-test=1        send a test notification to every stored
//                       push subscription and exit
//
// What it does: for every unpaid invoice whose due date falls on a
// reminder slot (3 days before, on the day, then weekly), it sends a
// short reminder through the first channel that works - push,
// whatsapp, email or sms. invoice_reminders makes it idempotent, so
// running it twice in one day never double-notifies a tenant.
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (PHP_SAPI === 'cli') {
    $token = trim((string)($argv[1] ?? ''));
    $dry = in_array('--dry', $argv, true);
    $pushTest = in_array('--push-test', $argv, true);
    $todayOverride = '';
    foreach ($argv as $a) {
        if (strpos($a, '--today=') === 0) {
            $todayOverride = substr($a, 8);
        }
    }
} else {
    $token = trim((string)($_GET['token'] ?? ''));
    $dry = !empty($_GET['dry']);
    $pushTest = !empty($_GET['push-test']);
    $todayOverride = trim((string)($_GET['today'] ?? ''));
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Session.php';
require_once __DIR__ . '/../classes/Lang.php';
require_once __DIR__ . '/../classes/Helpers.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../classes/Invoice.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/SmsSender.php';
require_once __DIR__ . '/../classes/WhatsAppSender.php';
require_once __DIR__ . '/../classes/WebPush.php';
require_once __DIR__ . '/../classes/PushSubscription.php';
require_once __DIR__ . '/../classes/Reminder.php';

Lang::override(DEFAULT_LANG);

function cronOut($msg) {
    echo $msg . (PHP_SAPI === 'cli' ? "\n" : "<br>\n");
}

$storedToken = (string)getSetting('reminder_cron_token', '');
if ($storedToken === '') {
    $storedToken = (string)getSetting('invoice_cron_token', '');
}
if ($token === '' || $storedToken === '' || !hash_equals($storedToken, $token)) {
    if (PHP_SAPI !== 'cli') {
        header('HTTP/1.1 403 Forbidden');
    }
    cronOut('Forbidden: invalid or unset token. Set reminder_cron_token in Settings -> Rent reminders.');
    exit(1);
}

$today = $todayOverride !== ''
    ? date('Y-m-d', strtotime($todayOverride))
    : date('Y-m-d');

if ($pushTest) {
    cronOut("Push test on $today");
    $result = (new WebPush())->sendToAllSubscriptions(json_encode([
        'title' => t('reminder_test_title'),
        'body'  => t('reminder_test_body'),
        'url'   => BASE_URL,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    cronOut("  subscriptions={$result['total']} sent={$result['sent']} failed={$result['failed']} pruned={$result['pruned']}");
    if ($result['total'] === 0) {
        cronOut('  No device is subscribed yet. Open the tenant portal, sign in and tap "Turn on reminders".');
        cronOut('  (Push also needs HTTPS, or localhost.)');
    }
    exit(0);
}

$reminder = new Reminder();
$out = $reminder->run($today, $dry);

foreach ($out['lines'] as $line) {
    cronOut($line);
}

cronOut(sprintf(
    'Result: attempts=%d sent=%d failed=%d skipped=%d%s',
    $out['attempts'],
    $out['sent'],
    $out['failed'],
    $out['skipped'],
    $dry ? ' (dry run - nothing was sent)' : ''
));

if (!$dry) {
    setSetting('reminder_last_run', $today);
}
