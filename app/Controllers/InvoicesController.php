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

        $monthlyInvoices = $invoiceModel->listForMonth($month, $year);
        // Invoices whose payment status is "Paid" move to Rent Collection
        // automatically (they are listed on payments.php) and are removed from
        // this page, so the invoice list only shows what still needs collecting.
        $paidCount = 0;
        $invoices = [];
        foreach ($monthlyInvoices as $row) {
            $eff = (string)($row['payment_status_override'] ?: ($row['payment_status'] ?: 'unpaid'));
            if ($eff === 'paid') {
                $paidCount++;
            } else {
                $invoices[] = $row;
            }
        }
        $totalDue = array_sum(array_map(function ($i) {
            return (float)$i['total_due'];
        }, $invoices));
        $invIdsJson = json_encode(array_map(function ($i) {
            return (int)$i['id'];
        }, $invoices));

        $invoiceDataJson = json_encode(array_combine(
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

        $this->view('invoices/index', [
            'invoices'        => $invoices,
            'paidCount'       => $paidCount,
            'totalDue'        => $totalDue,
            'invIdsJson'      => $invIdsJson,
            'invoiceDataJson' => $invoiceDataJson,
            'sendsIcons'      => $sendsIcons,
            'year'            => $year,
            'month'           => $month,
        ]);
    }
}