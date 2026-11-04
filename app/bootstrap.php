<?php
// ============================================================
// Bootstrap - loaded once per request by the front controller.
// Sets up error handling, loads the legacy init (config, classes,
// session, language), registers an autoloader for the App\
// namespace, and exposes the current route name to shared views.
// ============================================================

// ------------------------------------------------------------
// Error handling - FIRST, before anything else is loaded
// ------------------------------------------------------------
// This block must come before the require below. Anything that
// goes wrong while loading init.php (a file that did not make it
// in the deploy, a parse error) is fatal, and without a handler
// already installed PHP produces a blank HTTP 500 that says
// nothing at all. With the handler in place the page names the
// file and line that failed.
//
// config.php also turns display_errors off for production, which
// is right for visitors but made bugs invisible: a failing query
// or a fatal in a view simply truncated the response, so the page
// rendered up to the failure and the rest (footer + all page JS)
// was silently missing - every button on it then did nothing, with
// no error shown.
//
// Everything is still logged. Now additionally: every error
// records file/line, uncaught throwables are caught and reported
// instead of truncating, and ?debug=1 on any URL renders full
// details in the page.
// ------------------------------------------------------------
$appDebug = isset($_GET['debug']) && $_GET['debug'] === '1';
error_reporting(E_ALL);
ini_set('display_errors', $appDebug ? '1' : '0');

set_error_handler(function (int $no, string $str, string $file = '', int $line = 0) use ($appDebug): bool {
    if (!(error_reporting() & $no)) {
        return false; // silenced with @
    }
    error_log(sprintf(
        '[%s] %s in %s on line %d',
        date('Y-m-d H:i:s'),
        $str,
        $file,
        $line
    ));
    if ($appDebug) {
        echo '<pre style="background:#fee;border:1px solid #c00;padding:8px;'
            . 'color:#900;white-space:pre-wrap;font-size:12px">'
            . htmlspecialchars("$str in $file:$line", ENT_QUOTES)
            . '</pre>';
    }
    return true;
});

set_exception_handler(function (Throwable $e) use ($appDebug): void {
    error_log(sprintf(
        '[%s] UNCAUGHT %s: %s in %s on line %d',
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($appDebug) {
        echo '<pre style="background:#fee;border:1px solid #c00;padding:12px;'
            . 'color:#900;white-space:pre-wrap;font-size:12px">'
            . htmlspecialchars(
                get_class($e) . ': ' . $e->getMessage() . "\n"
                . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString(),
                ENT_QUOTES
            )
            . '</pre>';
    } else {
        echo '<div style="background:#fee;border:1px solid #c00;padding:12px;color:#900">'
            . '<b>Server error:</b> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '<br>'
            . '<small>' . htmlspecialchars($e->getFile() . ' line ' . $e->getLine(), ENT_QUOTES) . '</small><br>'
            . 'Add <code>?debug=1</code> to the URL to see the details, or check <code>logs/error.log</code>.'
            . '</div>';
    }
    exit;
});

// Load configuration, constants, classes and the session. A failure here
// is caught by the handler above and reported on the page.
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
