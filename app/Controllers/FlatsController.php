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

        // Landlords: the building filter may only offer buildings that contain
        // one of their units (derived from the already-scoped flats list).
        if (\Auth::check() && \Auth::role() !== 'admin') {
            $myBuildingIds = array_map(function ($f) { return (int)$f['building_id']; }, $flats);
            $buildings = array_values(array_filter($buildings, function ($b) use ($myBuildingIds) {
                return in_array((int)$b['id'], $myBuildingIds, true);
            }));
        }

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