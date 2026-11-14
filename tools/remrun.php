<?php
// Dev helper: run the reminder engine once and print the plan/result.
//   php tools/remrun.php --dry
//   php tools/remrun.php
//   php tools/remrun.php --today=2026-11-17
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

$dry = in_array('--dry', $argv, true);
$today = date('Y-m-d');
foreach ($argv as $a) {
    if (strpos($a, '--today=') === 0) {
        $today = date('Y-m-d', strtotime(substr($a, 8)));
    }
}

$out = (new Reminder())->run($today, $dry);
foreach ($out['lines'] as $line) {
    echo $line . PHP_EOL;
}
echo sprintf("attempts=%d sent=%d failed=%d skipped=%d%s\n",
    $out['attempts'], $out['sent'], $out['failed'], $out['skipped'], $dry ? ' (dry)' : '');
