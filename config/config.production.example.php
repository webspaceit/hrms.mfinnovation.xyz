<?php
// ============================================================
// PRODUCTION CONFIGURATION TEMPLATE
// ------------------------------------------------------------
// Copy this file over config/config.php on your live server and
// fill in the real values. Do NOT edit the example file itself.
//
//   cp config/config.production.example.php config/config.php
//
// Everything below is pre-tuned for a live environment
// (errors are logged, never displayed). Only the sections marked
// <<< FILL IN >>> need to match your hosting account.
// ============================================================

// ------------------------------------------------------------
// <<< FILL IN >>> Database
// ------------------------------------------------------------
// On shared hosting these come from the MySQL databases panel
// (e.g. cPanel -> MySQL Databases). Localhost DBs use root / ''.
define('DB_HOST', 'localhost');      // usually "localhost" on shared hosting
define('DB_USER', 'YOUR_DB_USER');
define('DB_PASS', 'YOUR_DB_PASSWORD');
define('DB_NAME', 'YOUR_DB_NAME');

// ------------------------------------------------------------
// <<< FILL IN >>> Base URL (IMPORTANT — trailing slash required)
// ------------------------------------------------------------
// '/'            -> installed at the domain root  (example.com/)
// '/rms/'        -> installed in a sub-folder     (example.com/rms/)
// '/oop-rms/'    -> local WAMP/XAMPP dev path
//
// Every link, form action and AJAX call is built from this, so a
// wrong value renders nothing. Keep the leading and trailing slash.
define('BASE_URL', '/');

// Error reporting — logged, never shown to visitors
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// ------------------------------------------------------------
// Application defaults (SITE_NAME, CURRENCY, DEFAULT_LANG, DB_PREFIX,
// CSRF_*, MAX_UPLOAD_SIZE, MAX_IMAGE_DIM, GS_PATH, NOTE_FONT_CHOICES)
// are defined as guarded defaults in classes/init.php, which ships with
// every update. This file only needs the values that are specific to one
// server, and anything defined here overrides the default.
// ============================================================
