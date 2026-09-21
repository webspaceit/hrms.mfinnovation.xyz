<?php
// ============================================================
// Payments (Rent Collection) - payment history with unpaid
// invoices merged in, add/edit/collect via modal (ajax). Former
// payments.php, now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class PaymentsController extends Controller {

    public function index(): void {
        \Auth::requireLogin();

        $paymentModel = new \Payment();
        $leaseModel = new \Lease();
        $invoiceModel = new \Invoice();

        $payments = $paymentModel->allWithDetails();
        $activeLeases = $leaseModel->activeLeases();

        $paymentsJson = json_encode(array_map(function ($p) {
            return [
                'id' => (int)$p['id'],
                'lease_id' => (int)$p['lease_id'],
                'month' => (int)$p['month'],
                'year' => (int)$p['year'],
                'amount' => $p['amount'],
                'utility_fee' => $p['utility_fee'],
                'parking_amount' => $p['parking_amount'],
                'gas_amount' => $p['gas_amount'],
                'water_fee' => $p['water_fee'],
                'waste_fee' => $p['waste_fee'],
                'arrears' => (float)($p['arrears'] ?? 0),
                'payment_method' => $p['payment_method'],
                'receipt_type' => $p['receipt_type'],
                'payment_date' => date('d-m-Y', strtotime($p['payment_date'])),
                'note' => $p['note'],
                'unit_type' => $p['unit_type'] ?? 'flat',
                'tenant_name' => $p['tenant_name'],
                'building_name' => $p['building_name'],
                'flat_no' => $p['flat_no'],
                'inv_due' => (float)($p['total_due'] ?? 0),
                'inv_paid' => (float)($p['inv_paid'] ?? 0),
                'inv_payment_status' => $p['inv_status_effective'] ?? $p['payment_status'] ?? null,
            ];
        }, $payments));

        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;

        // Outstanding invoices (unpaid, no payment recorded yet) are merged into the
        // payments list below, so every due bill from invoices.php automatically
        // shows up here ready for collection.
        $unpaidInvoices = $invoiceModel->listUnpaid($month ?: null, $year);
        $covered = [];
        foreach ($payments as $p) {
            $covered[$p['lease_id'] . '-' . $p['month'] . '-' . $p['year']] = true;
        }
        $unpaidRows = [];
        $unpaidTotal = 0.0;
        foreach ($unpaidInvoices as $inv) {
            $key = (int)$inv['lease_id'] . '-' . (int)$inv['month'] . '-' . (int)$inv['year'];
            if (isset($covered[$key])) {
                continue; // a payment row already covers this lease/month/year
            }
            $remaining = max(0, (float)$inv['total_due'] - (float)$inv['paid_amount']);
            $unpaidRows[] = [
                'id' => null,
                'invoice_id' => (int)$inv['id'],
                'is_due' => true,
                'lease_id' => (int)$inv['lease_id'],
                'tenant_id' => (int)$inv['tenant_id'],
                'tenant_name' => $inv['tenant_name'],
                'building_name' => $inv['building_name'],
                'flat_no' => $inv['flat_no'],
                'unit_type' => $inv['unit_type'],
                'month' => (int)$inv['month'],
                'year' => (int)$inv['year'],
                'payment_method' => null,
                'total_amount' => (float)$inv['total_due'],
                'total_due' => (float)$inv['total_due'],
                'inv_paid' => (float)$inv['paid_amount'],
                'remaining' => $remaining,
                'inv_status_effective' => 'unpaid',
                'rent_amount' => (float)$inv['rent_amount'],
                'utility_fee' => (float)$inv['utility_fee'],
                'parking_amount' => (float)$inv['parking_amount'],
                'gas_amount' => (float)$inv['gas_amount'],
                'water_fee' => (float)$inv['water_fee'],
                'waste_fee' => (float)$inv['waste_fee'],
                'arrears' => (float)($inv['arrears'] ?? 0),
            ];
            $unpaidTotal += (float)$inv['total_due'];
        }

        $unpaidJson = json_encode(array_map(function ($r) {
            return [
                'id' => (int)$r['invoice_id'],
                'lease_id' => (int)$r['lease_id'],
                'tenant_name' => localizeName($r['tenant_name']),
                'building_name' => localizeText($r['building_name']),
                'flat_no' => (string)$r['flat_no'],
                'unit_type' => $r['unit_type'],
                'month' => (int)$r['month'],
                'year' => (int)$r['year'],
                'rent_amount' => (float)$r['rent_amount'],
                'utility_fee' => (float)$r['utility_fee'],
                'parking_amount' => (float)$r['parking_amount'],
                'gas_amount' => (float)$r['gas_amount'],
                'water_fee' => (float)$r['water_fee'],
                'waste_fee' => (float)$r['waste_fee'],
                'arrears' => (float)$r['arrears'],
                'remaining' => (float)$r['remaining'],
                'total_due' => (float)$r['total_due'],
            ];
        }, $unpaidRows));

        // Every invoice's billed breakdown indexed by "lease-month-year" so the Add
        // Payment form can pre-fill the exact amounts the invoice carries (the invoice
        // is the single source of truth; the receipt then always matches it).
        $invoiceMap = [];
        foreach ($invoiceModel->allWithBreakdown() as $row) {
            $invoiceMap[(int)$row['lease_id'] . '-' . (int)$row['month'] . '-' . (int)$row['year']] = [
                'total_due' => (float)$row['total_due'],
                'paid' => (float)$row['paid_amount'],
                'rent_amount' => (float)$row['rent_amount'],
                'utility_fee' => (float)$row['utility_fee'],
                'parking_amount' => (float)$row['parking_amount'],
                'gas_amount' => (float)$row['gas_amount'],
                'water_fee' => (float)$row['water_fee'],
                'waste_fee' => (float)$row['waste_fee'],
                'arrears' => (float)($row['arrears'] ?? 0),
                'status' => (string)($row['payment_status_override'] ?: ($row['payment_status'] ?: 'unpaid')),
            ];
        }
        $invoiceMapJson = json_encode($invoiceMap);

        $this->view('payments/index', [
            'payments'       => $payments,
            'activeLeases'   => $activeLeases,
            'paymentsJson'   => $paymentsJson,
            'unpaidRows'     => $unpaidRows,
            'unpaidTotal'    => $unpaidTotal,
            'unpaidJson'     => $unpaidJson,
            'invoiceMapJson' => $invoiceMapJson,
            'paymentModel'   => $paymentModel,
            'year'           => $year,
            'month'          => $month,
        ]);
    }
}