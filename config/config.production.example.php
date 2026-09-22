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

// Application settings
define('SITE_NAME', 'Rent Management System');
define('CURRENCY', '৳');

// Session settings
define('SESSION_NAME', 'rms_session');

// Database table prefix. MUST match the table names created by
// database.sql / install.php — change both together, then
// rename existing tables (RENAME TABLE users TO wsit_users, ...).
define('DB_PREFIX', 'wsit_');

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// Error reporting — logged, never shown to visitors
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Default language ('bn' or 'en')
define('DEFAULT_LANG', 'bn');

// Upload settings
define('MAX_UPLOAD_SIZE', 20971520); // 20 MB
define('MAX_IMAGE_DIM', 1600);       // compress images taller/wider than this

// ------------------------------------------------------------
// Ghostscript (OPTIONAL — PDF compression on upload)
// ------------------------------------------------------------
// Only used when exec() is allowed AND Ghostscript is installed.
// Leave as '' on cheap hosting that disables exec() — uploads
// simply keep their original size. Otherwise set a full path,
// e.g. '/usr/bin/gs' (Linux) or 'C:\Program Files\gs\gs10.00.0\bin\gswin64c'.
define('GS_PATH', '');