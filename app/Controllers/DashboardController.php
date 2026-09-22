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

        // Stats - units/buildings are scoped for landlords to the units their
        // tenants occupy (admins / anonymous see the global numbers).
        $totalBuildings = $buildingModel->count('1=1' . buildingScope(''));
        $totalFlats = $flatModel->count('1=1' . unitScope(''));
        $totalShops = $flatModel->count("unit_type = 'shop'" . unitScope(''));
        $occupiedFlats = $flatModel->count("status = 'occupied'" . unitScope(''));
        $availableFlats = $flatModel->count("status = 'available'" . unitScope(''));
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

        // Recent payments + available units. A landlord's units are all leased
        // to their tenants, so they never have "free" units to advertise -
        // keep their list empty instead of showing other buildings' stock.
        $recentPayments = $paymentModel->lastN(8);
        $availableFlatsList = (\Auth::check() && \Auth::role() !== 'admin')
            ? [] : $flatModel->availableFlats();
        $maintenanceCount = $flatModel->count("status = 'maintenance'" . unitScope(''));

        // Admin-only: quick tenant -> landlord assignment widget.
        $isAdmin = \Auth::role() === 'admin';
        $assignTenants = [];
        $assignUsers = [];
        if ($isAdmin) {
            $assignTenants = $tenantModel->allWithLease();
            // Unassigned tenants (no owner) first - they are the ones that
            // usually still need a landlord.
            usort($assignTenants, function ($a, $b) {
                return ((int)($a['created_by'] ?? 0) ? 1 : 0) <=> ((int)($b['created_by'] ?? 0) ? 1 : 0);
            });
            $assignUsers = (new \User())->allUsers('full_name ASC');
        }

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
            'isAdmin'            => $isAdmin,
            'assignTenants'      => $assignTenants,
            'assignUsers'        => $assignUsers,
        ]);
    }
}