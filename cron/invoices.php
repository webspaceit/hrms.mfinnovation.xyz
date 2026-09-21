<?php
// ============================================================
// Due Invoice Cron - generate + auto-send monthly invoices
//
// Schedule it to run DAILY at the start of each month (e.g. every
// day at 9:00 via Windows Task Scheduler or a cron service). Auto
// sends only happen on the first days of the month, before the
// configured deadline day (settings -> Send before day of month).
//
// Invocation (the token is the only guard, set in Invoice settings):
//
//   CLI:   php cron/invoices.php <token>
//   HTTP:  cron/invoices.php?token=<token>
//
// Optional overrides:
//   ?month=3&year=2026 (or CLI $argv[2]/[3])
//   ?dry=1            print the delivery plan without sending
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (PHP_SAPI === 'cli') {
    $token = trim((string)($argv[1] ?? ''));
    $month = isset($argv[2]) ? (int)$argv[2] : (int)date('n');
    $year = isset($argv[3]) ? (int)$argv[3] : (int)date('Y');
    $dry = in_array('--dry', $argv, true);
    $dayOverride = 0;
    foreach ($argv as $a) {
        if (strpos($a, '--day=') === 0) {
            $dayOverride = (int)substr($a, 6);
        }
    }
} else {
    $token = trim((string)($_GET['token'] ?? ''));
    $month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)date('n');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    $dry = !empty($_GET['dry']);
    $dayOverride = isset($_GET['day']) ? (int)$_GET['day'] : 0;
}

// Boot without web session/headers (init.php sends headers + starts session).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Session.php';
require_once __DIR__ . '/../classes/Lang.php';
require_once __DIR__ . '/../classes/Helpers.php';
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../classes/Lease.php';
require_once __DIR__ . '/../classes/Payment.php';
require_once __DIR__ . '/../classes/Invoice.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/SmsSender.php';
require_once __DIR__ . '/../classes/WhatsAppSender.php';

// Default UI language for generated messages (Bengali, per app default).
Lang::override(DEFAULT_LANG);

function cronOut($msg) {
    echo $msg . (PHP_SAPI === 'cli' ? "\n" : "<br>\n");
}

$storedToken = (string)getSetting('invoice_cron_token', '');
if ($token === '' || $storedToken === '' || !hash_equals($storedToken, $token)) {
    header('HTTP/1.1 403 Forbidden');
    cronOut('Forbidden: invalid or unset token. Set invoice_cron_token in Invoice settings.');
    exit(1);
}

$invModel = new Invoice();
[$created, $updated] = $invModel->generateForMonth($month, $year);

$ref = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);

// Auto-send policy ----------------------------------------------------------
$autoEnabled = (string)getSetting('invoice_auto_enabled', '1') === '1';
$beforeDay = (int)getSetting('invoice_send_before_day', 5);
$rawChannels = (string)getSetting('invoice_auto_channels', 'whatsapp,email');
$channelOrder = array_values(array_intersect(
    ['whatsapp', 'email', 'sms'],
    array_map('trim', explode(',', $rawChannels))
));
if (!$channelOrder) {
    $channelOrder = ['whatsapp', 'email'];
}

$day = $dayOverride > 0 ? $dayOverride : (int)date('j');
$dayNote = $dayOverride > 0 ? " (day override)" : "";
$inWindow = $beforeDay <= 0 || $day < $beforeDay;
$today = date('d-m-Y');

$emailConfigured = trim((string)getSetting('smtp_host', '')) !== '';
$waConfigured = trim((string)getSetting('wa_api_url', '')) !== '' && trim((string)getSetting('wa_api_key', '')) !== '';
$smsConfigured = trim((string)getSetting('sms_api_url', '')) !== '' && trim((string)getSetting('sms_api_key', '')) !== '';

cronOut("Invoice run $ref on $today$dayNote: created=$created updated=$updated"
    . " auto=" . ($autoEnabled ? 'on' : 'off')
    . " window=" . ($beforeDay > 0 ? "day<$beforeDay" : 'any')
    . " channels=" . implode(',', $channelOrder));

$sent = 0;
$failed = 0;
$skipped = 0;
$attempts = 0;

if (!$autoEnabled) {
    cronOut('Auto-send disabled (invoice_auto_enabled). Invoices were only (re)generated.');
} elseif (!$inWindow) {
    cronOut("Not in the send window (today is day $day$dayNote, send before day $beforeDay). No sends attempted.");
} else {
    foreach ($invModel->listForMonth($month, $year) as $inv) {
        $hasEmail = $emailConfigured && trim((string)$inv['tenant_email']) !== '';
        $phone = preg_replace('/[^0-9]/', '', enDigits($inv['tenant_phone']));
        $hasPhone = $phone !== '';
        $hasWa = $waConfigured && $hasPhone;
        $hasSms = $smsConfigured && $hasPhone;

        // Pick the first eligible and not-yet-sent channel in priority order.
        $chosen = null;
        foreach ($channelOrder as $ch) {
            $eligible = $ch === 'email' ? $hasEmail : ($ch === 'whatsapp' ? $hasWa : $hasSms);
            if ($eligible && !$invModel->hasSent($inv['id'], $ch)) {
                $chosen = $ch;
                break;
            }
        }

        $who = $inv['tenant_name'] . ' / ' . $inv['building_name'] . ' ' . $inv['flat_no'];
        if (!$chosen) {
            $skipped++;
            cronOut("  - $who: no available channel (already sent or not configured)");
            continue;
        }

        $attempts++;
        if ($dry) {
            cronOut("  - $who -> $chosen (dry run)");
            continue;
        }

        $r = $invModel->deliver($inv['id'], $chosen, true);
        if ($r['success']) {
            $sent++;
            cronOut("  - $who -> $chosen: sent");
        } else {
            $failed++;
            cronOut("  - $who -> $chosen: FAILED - " . $r['message']);
        }
    }
}

if (!$dry) {
    setSetting('invoice_last_run', $ref);
}

cronOut("Result: attempts=$attempts sent=$sent failed=$failed skipped=$skipped");
if ($dry) {
    cronOut('Dry run - nothing was sent.');
}