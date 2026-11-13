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
        // Signed-in users can open the public landing page from the sidebar
        // through preview=1; normal visits still go straight to their dashboard.
        $preview = isset($_GET['preview']) && $_GET['preview'] === '1';
        if (!$preview && \Auth::check()) {
            redirect('dashboard');
        }
        if (!$preview && \Auth::checkTenant()) {
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
