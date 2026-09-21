<?php
// ============================================================
// Bootstrap - loaded once per request by the front controller.
// Loads the legacy init (config, classes, session, language),
// registers an autoloader for the App\ namespace, and exposes the
// current route name to the shared views.
// ============================================================

require_once __DIR__ . '/../classes/init.php';

// Autoload classes in the App\ namespace (app/Core, app/Controllers, ...).
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Current route name - used by the shared header for nav highlighting.
if (!defined('CURRENT_ROUTE')) {
    define('CURRENT_ROUTE', trim((string)($_GET['r'] ?? ''), '/'));
}