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

        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
        $filterBuilding = isset($_GET['building']) ? (int)$_GET['building'] : 0;
        $filterTypes = [];
        $typeValues = [];
        if (isset($_GET['type'])) {
            $types = $_GET['type'];
            $typeValues = is_array($types) ? $types : [$types];
        }
        // PHP only keeps the LAST value for a repeated plain key, so a URL such
        // as ?type=flat&type=shop loses "flat". Read the raw query string to
        // collect every type= / type[]= value as well.
        if (!empty($_SERVER['QUERY_STRING'])) {
            foreach (explode('&', (string)$_SERVER['QUERY_STRING']) as $pair) {
                $kv = explode('=', $pair, 2);
                $key = urldecode($kv[0]);
                if ($key === 'type' || $key === 'type[]') {
                    $typeValues[] = urldecode($kv[1] ?? '');
                }
            }
        }
        foreach ($typeValues as $t) {
            // Also accept comma-separated lists such as ?type=flat,shop.
            foreach (explode(',', (string)$t) as $piece) {
                $piece = strtolower(trim($piece));
                if (in_array($piece, ['flat', 'shop', 'commercial_floor'], true) && !in_array($piece, $filterTypes, true)) {
                    $filterTypes[] = $piece;
                }
            }
        }
        $filterType = $filterTypes[0] ?? '';
        $filterTypeAll = empty($filterTypes) || count($filterTypes) > 1;

        // Apply filters to payments
        if ($filterBuilding || !empty($filterTypes)) {
            $payments = array_filter($payments, function ($p) use ($filterBuilding, $filterTypes) {
                if ($filterBuilding && (int)($p['building_id'] ?? 0) != $filterBuilding) {
                    return false;
                }
                if (!empty($filterTypes)) {
                    $unitType = strtolower($p['unit_type'] ?? '');
                    if (!in_array($unitType, $filterTypes, true)) {
                        return false;
                    }
                }
                return true;
            });
        }
        // Mark dues separately
        foreach ($payments as &$p) {
            // Check if this is actually a due row? No, payments from Payment model are actual payments
            // The is_due comes from unpaid rows, so payments array doesn't have is_due
            if (!isset($p['is_due'])) $p['is_due'] = false;
        }
        unset($p);

        // Get unique buildings from all payments/leases for filter dropdown
        $buildingOptions = [];
        foreach ($payments as $p) {
            $bid = (int)($p['building_id'] ?? 0);
            if ($bid && !isset($buildingOptions[$bid])) {
                $buildingOptions[$bid] = localizeText($p['building_name'] ?? '');
            }
        }
        foreach ($activeLeases as $l) {
            $bid = (int)($l['building_id'] ?? 0);
            if ($bid && !isset($buildingOptions[$bid])) {
                $buildingOptions[$bid] = localizeText($l['building_name'] ?? '');
            }
        }
        ksort($buildingOptions);

        // array_filter() above keeps the original numeric keys, so once a
        // building/type filter is active the mapped result is a sparse array and
        // json_encode() would emit an OBJECT instead of an array. The view does
        // (window.PAYMENTS || []).find(...), which throws on a plain object and
        // silently kills the row Edit / Mark-paid buttons. Reindex first.
        $paymentsJson = jsonForScript(array_values(array_map(function ($p) {
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
                'note_color' => $p['note_color'] ?? null,
                'note_font' => $p['note_font'] ?? null,
                'service_charge_month' => monthList($p['service_charge_month'] ?? ''),
                'service_charge_year' => (int)($p['service_charge_year'] ?? 0),
                'unit_type' => $p['unit_type'] ?? 'flat',
                'tenant_name' => $p['tenant_name'],
                'building_name' => $p['building_name'],
                'flat_no' => $p['flat_no'],
                'inv_due' => (float)($p['total_due'] ?? 0),
                'inv_paid' => (float)($p['inv_paid'] ?? 0),
                'inv_payment_status' => $p['inv_status_effective'] ?? $p['payment_status'] ?? null,
            ];
        }, $payments)));

        // Outstanding invoices (unpaid, no payment recorded yet) are merged into the
        // payments list below, so every due bill from invoices.php automatically
        // shows up here ready for collection.
        $unpaidInvoices = $invoiceModel->listUnpaid($month ?: null, $year);
        $covered = [];
        foreach ($payments as $p) {
            $covered[$p['lease_id'] . '-' . $p['month'] . '-' . $p['year']] = true;
        }
        // Remove dues from payments when we merge? No - dues come from unpaidInvoices
        // But we need to separate properly - paid are in $payments, dues are in unpaidRows
        // The issue is we filtered payments but didn't mark them correctly; let's recalc from original filtered paid set
        $unpaidRows = [];
        $unpaidTotal = 0.0;
        foreach ($unpaidInvoices as $inv) {
            $key = (int)$inv['lease_id'] . '-' . (int)$inv['month'] . '-' . (int)$inv['year'];
            if (isset($covered[$key])) {
                continue; // a payment row already covers this lease/month/year
            }
            // Apply filters to unpaid rows
            if ($filterBuilding && (int)$inv['building_id'] != $filterBuilding) {
                continue;
            }
            if (!empty($filterTypes)) {
                $unitType = strtolower($inv['unit_type'] ?? '');
                if (!in_array($unitType, $filterTypes, true)) {
                    continue;
                }
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
                'service_charge_month' => '',
                'service_charge_year' => 0,
            ];
            // Use remaining balance (not total_due) so partial invoices
            // without a payment row are counted correctly
            $unpaidTotal += $remaining;
        }

        // Also add remaining balance from PARTIAL payments (payment exists but
        // balance still owed) — these were skipped above via $covered but their
        // balance still counts as due
        foreach ($payments as $p) {
            $status = strtolower($p['inv_status_effective'] ?? $p['payment_status'] ?? '');
            if ($status !== 'partial') continue;
            if ($month && (int)$p['month'] != $month) continue;
            if ($year && (int)$p['year'] != $year) continue;
            if ($filterBuilding && (int)($p['building_id'] ?? 0) != $filterBuilding) continue;
            if (!empty($filterTypes)) {
                $unitType = strtolower($p['unit_type'] ?? '');
                if (!in_array($unitType, $filterTypes, true)) continue;
            }
            $remaining = max(0, (float)($p['total_due'] ?? 0) - (float)($p['inv_paid'] ?? 0));
            if ($remaining > 0.009) {
                $unpaidTotal += $remaining;
            }
        }

        $unpaidJson = jsonForScript(array_values(array_map(function ($r) {
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
        }, $unpaidRows)));

        // Calculate individual unit totals - include both paid payments and dues? We need PAID collected
        $collectedFlatTotal = 0.0;
        $collectedShopTotal = 0.0;
        $collectedCommercialFloorTotal = 0.0;
        $collectedFilteredTotal = 0.0;
        // Count from paid payments only
        foreach ($payments as $p) {
            if (!empty($p['is_due'])) continue;
            if ($month && $p['month'] != $month) continue;
            if ($year && $p['year'] != $year) continue;
            if ($filterBuilding && (int)($p['building_id'] ?? 0) != $filterBuilding) continue;
            if (!empty($filterTypes)) {
                $unitType = strtolower($p['unit_type'] ?? '');
                if (!in_array($unitType, $filterTypes, true)) continue;
            }
            $amt = (float)($p['total_amount'] ?? 0);
            $collectedFilteredTotal += $amt;
            $unitType = strtolower($p['unit_type'] ?? 'flat');
            if ($unitType === 'shop') {
                $collectedShopTotal += $amt;
            } elseif ($unitType === 'commercial_floor') {
                $collectedCommercialFloorTotal += $amt;
            } else {
                $collectedFlatTotal += $amt;
            }
        }

        $unpaidFlatTotal = 0.0;
        $unpaidShopTotal = 0.0;
        $unpaidCommercialFloorTotal = 0.0;
        $unpaidFilteredTotal = 0.0;
        // Fully unpaid rows (no payment recorded)
        foreach ($unpaidRows as $r) {
            $unitType = strtolower($r['unit_type'] ?? 'flat');
            $rem = (float)$r['remaining'];
            $unpaidFilteredTotal += $rem;
            if ($unitType === 'shop') {
                $unpaidShopTotal += $rem;
            } elseif ($unitType === 'commercial_floor') {
                $unpaidCommercialFloorTotal += $rem;
            } else {
                $unpaidFlatTotal += $rem;
            }
        }
        // Partial payment rows (payment exists but balance remains)
        foreach ($payments as $p) {
            $status = strtolower($p['inv_status_effective'] ?? $p['payment_status'] ?? '');
            if ($status !== 'partial') continue;
            if ($month && (int)$p['month'] != $month) continue;
            if ($year && (int)$p['year'] != $year) continue;
            if ($filterBuilding && (int)($p['building_id'] ?? 0) != $filterBuilding) continue;
            if (!empty($filterTypes)) {
                $unitType = strtolower($p['unit_type'] ?? '');
                if (!in_array($unitType, $filterTypes, true)) continue;
            }
            $rem = max(0, (float)($p['total_due'] ?? 0) - (float)($p['inv_paid'] ?? 0));
            if ($rem <= 0.009) continue;
            $unpaidFilteredTotal += $rem;
            $unitType = strtolower($p['unit_type'] ?? 'flat');
            if ($unitType === 'shop') {
                $unpaidShopTotal += $rem;
            } elseif ($unitType === 'commercial_floor') {
                $unpaidCommercialFloorTotal += $rem;
            } else {
                $unpaidFlatTotal += $rem;
            }
        }

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
        $invoiceMapJson = jsonForScript($invoiceMap);

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
            'filterBuilding' => $filterBuilding,
            'filterType'     => $filterType,
            'filterTypes'    => $filterTypes,
            'filterTypeAll'  => $filterTypeAll,
            'buildingOptions' => $buildingOptions,
            'collectedFlatTotal' => $collectedFlatTotal,
            'collectedShopTotal' => $collectedShopTotal,
            'collectedCommercialFloorTotal' => $collectedCommercialFloorTotal,
            'collectedFilteredTotal' => $collectedFilteredTotal,
            'unpaidFlatTotal' => $unpaidFlatTotal,
            'unpaidShopTotal' => $unpaidShopTotal,
            'unpaidCommercialFloorTotal' => $unpaidCommercialFloorTotal,
            'unpaidFilteredTotal' => $unpaidFilteredTotal,
        ]);
    }
}
