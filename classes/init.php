<?php
// ============================================================
// Initialize application - loaded at the start of every page
// ============================================================

// Never cache per-user pages (avoids stale language / stale data)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Load configuration
require_once __DIR__ . '/../config/config.php';

// ============================================================
// Application defaults
// ------------------------------------------------------------
// Everything the app needs beyond the three server-specific values
// (DB_HOST, DB_USER, DB_PASS, DB_NAME) and BASE_URL.
//
// These live here, in a file that already existed and is therefore
// reliably overwritten on every update, rather than in a new file or
// in config/config.php. Both of those have caused a full-site outage:
// config.php is the one file a site owner keeps, so constants added
// later never reached an existing install (the payments page died with
// "Undefined constant NOTE_FONT_CHOICES"), and a brand new file was
// silently skipped by an upload that only replaced existing ones.
//
// Every define() is guarded, so a value in config/config.php always
// wins and an older config keeps working untouched.
// ============================================================

// Database table prefix. MUST match the table names created by
// database.sql / install.php.
if (!defined('DB_PREFIX')) {
    define('DB_PREFIX', 'wsit_');
}

// Session cookie name
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'rms_session');
}

// Application settings
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Rent Management System');
}
if (!defined('CURRENCY')) {
    define('CURRENCY', '৳');
}

// Default language ('bn' or 'en')
if (!defined('DEFAULT_LANG')) {
    define('DEFAULT_LANG', 'bn');
}

// Security settings
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}
if (!defined('CSRF_TOKEN_EXPIRY')) {
    define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour
}

// Upload settings
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 20971520); // 20 MB
}
if (!defined('MAX_IMAGE_DIM')) {
    define('MAX_IMAGE_DIM', 1600); // compress images taller/wider than this
}

// Ghostscript path for PDF compression. '' = disabled: uploads keep
// their original size, which is the right default on shared hosting
// where exec() is usually off. Set a real path in config.php if your
// server has Ghostscript installed.
if (!defined('GS_PATH')) {
    define('GS_PATH', '');
}

// Note font choices for the payment receipt note block, as
// [css font-family => label shown in the dropdown]. The value is
// written into a style attribute, so the list is fixed and validated
// on save (ajax/payment_save.php, ajax/payment_update.php).
// Do not add user-supplied values here.
if (!defined('NOTE_FONT_CHOICES')) {
    define('NOTE_FONT_CHOICES', [
        "'Noto Serif Bengali', serif" => 'Noto Serif Bengali',
        'serif'      => 'Serif',
        'sans-serif' => 'Sans-serif',
        'monospace'  => 'Monospace',
        'cursive'    => 'Cursive',
    ]);
}

// Load all classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Lang.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Building.php';
require_once __DIR__ . '/Flat.php';
require_once __DIR__ . '/Tenant.php';
require_once __DIR__ . '/Lease.php';
require_once __DIR__ . '/Payment.php';
require_once __DIR__ . '/Expense.php';
require_once __DIR__ . '/Invoice.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/SmsSender.php';
require_once __DIR__ . '/WhatsAppSender.php';

// Start session
Session::start();

// Initialize language
Lang::init();

// Per-request language override from URL (?lang=bn) - shareable links
if (isset($_GET['lang'])) {
    Lang::override($_GET['lang']);
}

// Auto-expire leases
$leaseModel = new Lease();
$leaseModel->checkExpired();
