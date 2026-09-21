<?php
// ============================================================
// Flats - list with filters, add/edit via modal (ajax), delete.
// Former flats.php, now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class FlatsController extends Controller {

    public function index(): void {
        \Auth::requireLogin();

        $pageTitle = t('flats');

        $buildingModel = new \Building();
        $flatModel = new \Flat();

        $buildings = $buildingModel->all();

        // Filters
        $filterBuilding = isset($_GET['building']) ? (int)$_GET['building'] : 0;
        $filterType = isset($_GET['type']) ? $_GET['type'] : '';
        if ($filterType !== 'flat' && $filterType !== 'shop') {
            $filterType = '';
        }

        $flats = $flatModel->allWithBuilding($filterType);
        if ($filterBuilding) {
            $flats = array_filter($flats, function($f) use ($filterBuilding) {
                return $f['building_id'] == $filterBuilding;
            });
        }

        // Filter URL helper (was a global function flatFilterUrl() in flats.php;
        // precomputed here and consumed by the view's filter links).
        $filterUrlAll  = url('flats') . ($filterBuilding ? '?building=' . $filterBuilding : '');
        $filterUrlFlat = url('flats') . '?type=flat' . ($filterBuilding ? '&building=' . $filterBuilding : '');
        $filterUrlShop = url('flats') . '?type=shop' . ($filterBuilding ? '&building=' . $filterBuilding : '');

        $this->view('flats/index', [
            'flats'          => $flats,
            'buildings'      => $buildings,
            'filterBuilding' => $filterBuilding,
            'filterType'     => $filterType,
            'flatModel'      => $flatModel,
            'filterUrlAll'   => $filterUrlAll,
            'filterUrlFlat'  => $filterUrlFlat,
            'filterUrlShop'  => $filterUrlShop,
        ]);
    }
}