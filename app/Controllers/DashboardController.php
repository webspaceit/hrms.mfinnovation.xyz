<?php
// ============================================================
// Admin dashboard - stat cards, charts, recent payments and
// available units. Former dashboard.php, now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class DashboardController extends Controller {

    public function index(): void {
        \Auth::requireLogin();

        $buildingModel = new \Building();
        $flatModel = new \Flat();
        $tenantModel = new \Tenant();
        $leaseModel = new \Lease();
        $paymentModel = new \Payment();
        $expenseModel = new \Expense();

        $year = date('Y');
        $month = date('n');

        // Stats
        $totalBuildings = $buildingModel->count();
        $totalFlats = $flatModel->count();
        $totalShops = $flatModel->countByType('shop');
        $occupiedFlats = $flatModel->count("status = 'occupied'");
        $availableFlats = $flatModel->count("status = 'available'");
        $totalTenants = $tenantModel->count("status = 'active'" . tenantScope(''));
        $activeLeases = $leaseModel->activeCountScoped();

        // Financial stats
        $totalCollected = $paymentModel->totalCollected();
        $monthlyIncome = $paymentModel->totalCollected($year, $month);
        $totalExpenses = $expenseModel->totalExpense();
        $monthlyExpenses = $expenseModel->totalExpense($year, $month);
        $netIncome = $totalCollected - $totalExpenses;

        // Expected monthly income from active leases
        $expectedMonthly = $leaseModel->expectedMonthlyIncome();

        // Monthly chart data (last 12 months)
        $chartData = [];
        for ($i = 11; $i >= 0; $i--) {
            $ts = strtotime("-$i months");
            $y = date('Y', $ts);
            $m = date('n', $ts);
            $chartData['labels'][] = monthName($m);
            $chartData['income'][] = $paymentModel->totalCollected($y, $m);
            $chartData['expense'][] = $expenseModel->totalExpense($y, $m);
        }

        // Recent payments + available units
        $recentPayments = $paymentModel->lastN(8);
        $availableFlatsList = $flatModel->availableFlats();
        $maintenanceCount = $flatModel->count("status = 'maintenance'");

        $this->view('dashboard/index', [
            'monthlyIncome'      => $monthlyIncome,
            'totalTenants'       => $totalTenants,
            'totalBuildings'     => $totalBuildings,
            'activeLeases'       => $activeLeases,
            'totalFlats'         => $totalFlats,
            'totalShops'         => $totalShops,
            'occupiedFlats'      => $occupiedFlats,
            'availableFlats'     => $availableFlats,
            'monthlyExpenses'    => $monthlyExpenses,
            'netIncome'          => $netIncome,
            'totalCollected'     => $totalCollected,
            'totalExpenses'      => $totalExpenses,
            'expectedMonthly'    => $expectedMonthly,
            'chartData'          => $chartData,
            'recentPayments'     => $recentPayments,
            'availableFlatsList' => $availableFlatsList,
            'maintenanceCount'   => $maintenanceCount,
            'year'               => $year,
            'month'              => $month,
        ]);
    }
}