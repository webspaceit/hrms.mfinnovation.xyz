<?php
// ============================================================
// Landing page - bilingual welcome gate and public "Available for
// Rent" showcase (flats + shops). Former index.php content, now a
// controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class LandingController extends Controller {

    public function index(): void {
        // Already signed in? Skip the landing and go to the dashboard.
        if (\Auth::check()) {
            redirect('dashboard');
        }
        if (\Auth::checkTenant()) {
            redirect('tenant-dashboard');
        }

        // Public showcase: available flats & shops.
        $flatModel = new \Flat();
        $availableUnits = $flatModel->availableFlats();

        $this->view('landing/index', [
            'availableUnits' => $availableUnits,
        ]);
    }
}