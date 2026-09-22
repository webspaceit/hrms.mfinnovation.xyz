<?php
// ============================================================
// Configuration File
// ============================================================

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'oop_rms');

// Application settings
define('BASE_URL', '/oop-rms/');
define('SITE_NAME', 'Rent Management System');
define('CURRENCY', '৳');

// Session settings
define('SESSION_NAME', 'rms_session');

// Database table prefix. MUST match the table names created by
// database.sql / install.php (e.g. 'users' -> '<prefix>users').
// Set to '' to disable prefixed tables entirely.
define('DB_PREFIX', 'wsit_');

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// Error reporting (disable in production)
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Default language
define('DEFAULT_LANG', 'bn');

// Upload settings
define('MAX_UPLOAD_SIZE', 20971520); // 20 MB
define('MAX_IMAGE_DIM', 1600);       // compress images taller/wider than this

// Ghostscript for PDF compression. 'gs' resolves from PATH after installing
// Ghostscript (https://ghostscript.com). Set a full path if not on PATH.
define('GS_PATH', 'gs');
