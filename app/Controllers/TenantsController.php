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

        $isAdmin = \Auth::role() === 'admin';
        $tenantModel = new \Tenant();
        $tenants = $tenantModel->allWithLease();
        // Only admins can reassign a tenant's owner (landlords are fixed to
        // the tenants they entered, so they never need the list).
        $users = $isAdmin ? (new \User())->allUsers('full_name ASC') : [];

        $this->view('tenants/index', [
            'tenants' => $tenants,
            'isAdmin' => $isAdmin,
            'users'   => $users,
        ]);
    }
}