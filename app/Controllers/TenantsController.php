<?php
// ============================================================
// Tenants - list with add/edit via modal (ajax), delete.
// Former tenants.php, now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class TenantsController extends Controller {

    public function index(): void {
        \Auth::requireLogin();

        $tenantModel = new \Tenant();
        $tenants = $tenantModel->allWithLease();

        $this->view('tenants/index', [
            'tenants' => $tenants,
        ]);
    }
}