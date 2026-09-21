<?php
// ============================================================
// Tenant Portal - My Dues (monthly demand notes, read-only)
// Every row is scoped to the logged-in tenant id.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class TenantDuesController extends Controller {

    public function index(): void {
        \Auth::requireTenantLogin();

        $tenantId = \Auth::tenantId();

        $invoiceModel = new \Invoice();

        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $years = $invoiceModel->yearsForTenant($tenantId);
        if ($years && !in_array($year, $years, true)) {
            $year = $years[0];
        }

        $invoices = $invoiceModel->listForTenant($tenantId, $years ? $year : null);
        $totals = $invoiceModel->totalsForTenant($tenantId, $years ? $year : null);

        $this->view('tenant-dues/index', [
            'year'     => $year,
            'years'    => $years,
            'invoices' => $invoices,
            'totals'   => $totals,
        ]);
    }
}