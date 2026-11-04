<?php
// ============================================================
// Invoices (Monthly Due / Demand Notes) - generation, edit,
// send (email/whatsapp/sms), status overrides. Former invoices.php,
// now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class InvoicesController extends Controller {

    public function index(): void {
        \Auth::requireLogin();

        $invoiceModel = new \Invoice();

        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

        // Invoices whose payment status is "Paid" move to Rent Collection
        // automatically (they are listed on payments.php) and are removed from
        // this page, so the invoice list only shows what still needs collecting.
        //
        // That hiding is not always a move: a manual "Paid" override also keeps
        // an invoice off Rent Collection, because listUnpaid() skips it too. Such
        // a row exists on neither page, and deleting its receipt does not clear
        // the override (reconcileInvoice deliberately leaves overrides alone), so
        // it can be stranded with no way back. show_paid reveals the hidden rows
        // so the status can be put back to Auto.
        $showPaid = !empty($_GET['show_paid']);

        $monthlyInvoices = $invoiceModel->listForMonth($month, $year);
        $paidCount = 0;
        $invoices = [];
        $totalDue = 0.0;
        foreach ($monthlyInvoices as $row) {
            $eff = (string)($row['payment_status_override'] ?: ($row['payment_status'] ?: 'unpaid'));
            if ($eff === 'paid') {
                $paidCount++;
                if ($showPaid) {
                    $invoices[] = $row;
                }
            } else {
                $invoices[] = $row;
                // Only what still needs collecting counts towards the total, so
                // revealing paid rows must not inflate it.
                $totalDue += (float)$row['total_due'];
            }
        }
        $invIdsJson = json_encode(array_map(function ($i) {
            return (int)$i['id'];
        }, $invoices));

        $invoiceDataJson = jsonForScript(array_combine(
            array_map(function ($i) {
                return (int)$i['id'];
            }, $invoices),
            array_map(function ($i) {
                return [
                    'id' => (int)$i['id'],
                    'tenant_name' => localizeName($i['tenant_name']),
                    'flat_no' => (string)$i['flat_no'],
                    'month' => (int)$i['month'],
                    'year' => (int)$i['year'],
                    'due_date' => !empty($i['due_date'])
                        ? date('d-m-Y', strtotime($i['due_date']))
                        : date('d-m-Y', strtotime(sprintf('%04d-%02d-01', $i['year'], $i['month']) . ' +1 month -1 day')),
                    'rent_amount' => (float)($i['rent_amount'] ?? 0),
                    'utility_fee' => (float)($i['utility_fee'] ?? 0),
                    'parking_amount' => (float)($i['parking_amount'] ?? 0),
                    'gas_amount' => (float)($i['gas_amount'] ?? 0),
                    'water_fee' => (float)($i['water_fee'] ?? 0),
                    'waste_fee' => (float)($i['waste_fee'] ?? 0),
                    'arrears' => (float)($i['arrears'] ?? 0),
                    'arrears_months' => (int)($i['arrears_months'] ?? 0),
                    'total_due' => (float)$i['total_due'],
                    'note' => (string)$i['note'],
                    'is_manual' => (int)$i['is_manual']
                ];
            }, $invoices)
        ));

        $sendsIcons = function ($inv) {
            $channels = ['email' => 'bi-envelope', 'whatsapp' => 'bi-whatsapp', 'sms' => 'bi-chat-dots'];
            $out = [];
            foreach ($channels as $ch => $icon) {
                if (preg_match('/(^|\\|)' . $ch . ':(sent|failed)/', (string)$inv['sends_log'], $m)) {
                    $cls = $m[2] === 'sent' ? ' text-success' : ' text-danger';
                    $out[] = '<i class="bi ' . $icon . $cls . '" title="' . $ch . ': ' . $m[2] . '"></i>';
                } else {
                    $out[] = '<i class="bi ' . $icon . ' text-muted opacity-25"></i>';
                }
            }
            return implode(' ', $out);
        };

        // Months that have at least one active lease, newest last. The backfill
        // dialog defaults to the newest of these so it opens on a range that
        // actually bills somebody rather than one that predates every lease.
        $billableMonths = [];
        for ($m = 1; $m <= 12; $m++) {
            $firstDay = sprintf('%04d-%02d-01', $year, $m);
            $lastDay = date('Y-m-t', strtotime($firstDay));
            $st = \Database::getInstance()->getConnection()->prepare(
                \Database::prefix(
                    "SELECT COUNT(*) FROM leases l
                     JOIN tenants t ON t.id = l.tenant_id
                     WHERE l.status = 'active' AND t.status = 'active'
                       AND l.start_date <= :last
                       AND (l.end_date IS NULL OR l.end_date >= :first)"
                )
            );
            $st->execute([':last' => $lastDay, ':first' => sprintf('%04d-%02d-01', $year, $m)]);
            if ((int)$st->fetchColumn() > 0) {
                $billableMonths[] = [$year, $m];
            }
        }

        $this->view('invoices/index', [
            'invoices'        => $invoices,
            'paidCount'       => $paidCount,
            'showPaid'        => $showPaid,
            'totalDue'        => $totalDue,
            'invIdsJson'      => $invIdsJson,
            'invoiceDataJson' => $invoiceDataJson,
            'sendsIcons'      => $sendsIcons,
            'year'            => $year,
            'month'           => $month,
            'billableMonths'  => $billableMonths,
        ]);
    }
}