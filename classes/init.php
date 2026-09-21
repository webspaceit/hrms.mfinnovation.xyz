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
