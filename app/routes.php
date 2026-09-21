<?php
// ============================================================
// Route table - the single place that maps URLs to controllers.
// Converted pages register a controller; pages that still exist
// as legacy *.php files register a legacy fallback so nothing
// 404s while the conversion is in progress.
// ============================================================

use App\Core\Router;

$router = new Router();

// ---- Public (converted) ----
foreach (['', 'home', 'landing', 'welcome'] as $alias) {
    $router->controller($alias, App\Controllers\LandingController::class);
}
$router->controller('login', App\Controllers\LoginController::class);
$router->controller('logout', App\Controllers\LogoutController::class);

// ---- Admin (converted) ----
$router->controller('dashboard', App\Controllers\DashboardController::class);
$router->controller('buildings', App\Controllers\BuildingsController::class);

// ---- Admin (legacy - conversion pending) ----
$router->legacy('flats', 'flats.php');
$router->controller('tenants', App\Controllers\TenantsController::class);
$router->controller('leases', App\Controllers\LeasesController::class);
$router->controller('payments', App\Controllers\PaymentsController::class);
$router->legacy('invoices', 'invoices.php');
$router->legacy('invoice-view', 'invoice_view.php');
$router->legacy('receipt', 'receipt.php');
$router->controller('expenses', App\Controllers\ExpensesController::class);
$router->controller('reports', App\Controllers\ReportsController::class);

// ---- Tenant portal (legacy - conversion pending) ----
$router->legacy('tenant-login', 'tenant_login.php');
$router->legacy('tenant-logout', 'tenant_logout.php');
$router->legacy('tenant-dashboard', 'tenant_dashboard.php');
$router->legacy('tenant-dues', 'tenant_dues.php');
$router->legacy('tenant-invoice-view', 'tenant_invoice_view.php');
$router->legacy('tenant-payments', 'tenant_payments.php');
$router->legacy('tenant-profile', 'tenant_profile.php');
$router->legacy('tenant-receipt', 'tenant_receipt.php');

return $router;