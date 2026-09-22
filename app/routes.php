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

// ---- Admin (converted) ----
$router->controller('flats', App\Controllers\FlatsController::class);
$router->controller('tenants', App\Controllers\TenantsController::class);
$router->controller('leases', App\Controllers\LeasesController::class);
$router->controller('payments', App\Controllers\PaymentsController::class);
$router->controller('invoices', App\Controllers\InvoicesController::class);
$router->controller('invoice-view', App\Controllers\InvoiceViewController::class);
$router->controller('receipt', App\Controllers\ReceiptController::class);
$router->controller('expenses', App\Controllers\ExpensesController::class);
$router->controller('reports', App\Controllers\ReportsController::class);

// ---- Admin: user accounts & access levels ----
$router->controller('users', App\Controllers\UsersController::class);

// ---- Tenant portal (converted) ----
$router->controller('tenant-login', App\Controllers\TenantLoginController::class);
$router->controller('tenant-logout', App\Controllers\TenantLogoutController::class);
$router->controller('tenant-dashboard', App\Controllers\TenantDashboardController::class);
$router->controller('tenant-dues', App\Controllers\TenantDuesController::class);
$router->controller('tenant-invoice-view', App\Controllers\TenantInvoiceViewController::class);
$router->controller('tenant-payments', App\Controllers\TenantPaymentsController::class);
$router->controller('tenant-profile', App\Controllers\TenantProfileController::class);
$router->controller('tenant-receipt', App\Controllers\TenantReceiptController::class);

return $router;