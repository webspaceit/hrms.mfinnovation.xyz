<?php
// ============================================================
// Buildings - list with stats, add/edit via modal (ajax), delete.
// Former buildings.php, now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class BuildingsController extends Controller {

    public function index(): void {
        \Auth::requireLogin();

        $buildingModel = new \Building();
        $buildings = $buildingModel->allWithStats();

        $editing = null;
        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editing = $buildingModel->find($_GET['edit']);
        }

        $this->view('buildings/index', [
            'buildings' => $buildings,
            'editing'   => $editing,
        ]);
    }
}