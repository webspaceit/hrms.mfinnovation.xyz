<?php
// ============================================================
// Tenant Portal - My Rent (paid rent collection, read-only)
// Every row is scoped to the logged-in tenant id. Former
// tenant_payments.php, now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class TenantPaymentsController extends Controller {

    public function index(): void {
        \Auth::requireTenantLogin();

        $tenantId = \Auth::tenantId();

        $paymentModel = new \Payment();

        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $years = $paymentModel->yearsForTenant($tenantId);
        if ($years && !in_array($year, $years, true)) {
            $year = $years[0];
        }

        $payments = $paymentModel->listForTenant($tenantId, $years ? $year : null);
        $totalPaid = array_sum(array_map(function ($p) { return (float)$p['total_amount']; }, $payments));

        $this->view('tenant-payments/index', [
            'tenantId'  => $tenantId,
            'years'     => $years,
            'year'      => $year,
            'payments'  => $payments,
            'totalPaid' => $totalPaid,
        ]);
    }
}