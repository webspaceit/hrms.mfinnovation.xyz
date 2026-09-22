<?php
// ============================================================
// Leases - list with stats, add/edit via modal (ajax), delete.
// Former leases.php, now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class LeasesController extends Controller {

    public function index(): void {
        \Auth::requireLogin();

        $leaseModel = new \Lease();
        $tenantModel = new \Tenant();
        $flatModel = new \Flat();

        $leases = $leaseModel->allWithDetails();
        $tenants = $tenantModel->activeVisible(); // scoped to current user
        $allFlats = $flatModel->allWithBuilding(); // landlord: own units only
        if (\Auth::check() && \Auth::role() !== 'admin') {
            // Free units are public data (landing page lists them) and a
            // landlord needs one to lease a newly entered tenant - add them
            // back for the dropdown while keeping other landlords' occupied
            // units hidden.
            $allFlats = array_merge($allFlats, $flatModel->availableFlats());
        }

        $this->view('leases/index', [
            'leases'   => $leases,
            'tenants'  => $tenants,
            'allFlats' => $allFlats,
        ]);
    }
}