<?php
// ============================================================
// Tenant Portal - Logout
// Leaves an open admin session untouched (portal keys only).
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class TenantLogoutController extends Controller {

    public function index(): void {
        \Auth::logoutTenant();
        redirect('home');
    }
}