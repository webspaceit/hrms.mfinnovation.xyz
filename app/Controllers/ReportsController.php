<?php
// ============================================================
// Reports - yearly income/expense comparison. Former reports.php,
// now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class ReportsController extends Controller {

    public function index(): void {
        \Auth::requireLogin();

        $paymentModel = new \Payment();
        $expenseModel = new \Expense();

        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

        // Monthly data
        $incomeData = $paymentModel->monthlySummary($year);
        $expenseData = $expenseModel->monthlySummary($year);

        $monthlyIncome = array_fill(1, 12, 0);
        foreach ($incomeData as $d) { $monthlyIncome[$d['month']] = (float)$d['total']; }
        $monthlyExpense = array_fill(1, 12, 0);
        foreach ($expenseData as $d) { $monthlyExpense[$d['month']] = (float)$d['total']; }

        // Totals
        $yearlyIncome = array_sum($monthlyIncome);
        $yearlyExpense = array_sum($monthlyExpense);
        $yearlyProfit = $yearlyIncome - $yearlyExpense;

        // Per building income
        $perBuilding = $paymentModel->perBuildingSummary($year);

        $this->view('reports/index', [
            'year'           => $year,
            'monthlyIncome'  => $monthlyIncome,
            'monthlyExpense' => $monthlyExpense,
            'yearlyIncome'   => $yearlyIncome,
            'yearlyExpense'  => $yearlyExpense,
            'yearlyProfit'   => $yearlyProfit,
            'perBuilding'    => $perBuilding,
        ]);
    }
}