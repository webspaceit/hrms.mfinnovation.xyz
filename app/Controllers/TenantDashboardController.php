<?php
// ============================================================
// Tenant Portal - My Home
// Every query is scoped to the tenant id stored in the portal
// session, so a tenant can only ever see his/her own data.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class TenantDashboardController extends Controller {

    public function index(): void {
        \Auth::requireTenantLogin();

        $tenantId = \Auth::tenantId();

        $tenantModel  = new \Tenant();
        $leaseModel   = new \Lease();
        $paymentModel = new \Payment();
        $invoiceModel = new \Invoice();

        $tenant = $tenantModel->find($tenantId);
        $lease  = $leaseModel->currentForTenant($tenantId);

        $year  = (int)date('Y');
        $month = (int)date('n');

        $currentInvoice = $invoiceModel->forMonthForTenant($tenantId, $month, $year);
        $allTotals      = $invoiceModel->totalsForTenant($tenantId);
        $paidThisYear   = $paymentModel->totalForTenant($tenantId, $year);
        $recentPayments = array_slice($paymentModel->listForTenant($tenantId), 0, 6);

        $monthDue   = $currentInvoice ? max(0, (float)$currentInvoice['total_due'] - (float)$currentInvoice['paid_amount']) : 0.0;
        $monthlyRent = $lease ? ((float)$lease['rent_amount'] + (float)$lease['utility_fee']) : 0.0;
        $payable    = $lease ? ((float)$lease['rent_amount']) : 0.0;
        $utility    = $lease ? ((float)$lease['utility_fee']) : 0.0;

        $this->view('tenant-dashboard/index', [
            'monthlyRent'    => $monthlyRent,
            'monthDue'       => $monthDue,
            'month'          => $month,
            'year'           => $year,
            'allTotals'      => $allTotals,
            'paidThisYear'   => $paidThisYear,
            'currentInvoice' => $currentInvoice,
            'recentPayments' => $recentPayments,
        ]);
    }
}