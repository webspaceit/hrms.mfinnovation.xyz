<?php
// ============================================================
// Front Controller - single entry point for the whole app.
// Pretty URLs are rewritten here by .htaccess, and ?r=... works
// directly too. Legacy page files (flats.php, payments.php, ...)
// still serve themselves until they are converted to controllers
// (see app/routes.php).
// ============================================================

require_once __DIR__ . '/app/bootstrap.php';

/** @var App\Core\Router $router */
$router = require __DIR__ . '/app/routes.php';

$route = (string)($_GET['r'] ?? '');
$router->dispatch($route);