<?php
// ============================================================
// Dashboard
// ============================================================

require_once __DIR__ . '/classes/init.php';
Auth::requireLogin();

$pageTitle = t('dashboard');

$buildingModel = new Building();
$flatModel = new Flat();
$tenantModel = new Tenant();
$leaseModel = new Lease();
$paymentModel = new Payment();
$expenseModel = new Expense();

$year = date('Y');
$month = date('n');

// Stats
$totalBuildings = $buildingModel->count();
$totalFlats = $flatModel->count();
$totalShops = $flatModel->countByType('shop');
$occupiedFlats = $flatModel->count("status = 'occupied'");
$availableFlats = $flatModel->count("status = 'available'");
$totalTenants = $tenantModel->count("status = 'active'");
$activeLeases = $leaseModel->count("status = 'active'");

// Financial stats
$totalCollected = $paymentModel->totalCollected();
$monthlyIncome = $paymentModel->totalCollected($year, $month);
$totalExpenses = $expenseModel->totalExpense();
$monthlyExpenses = $expenseModel->totalExpense($year, $month);
$netIncome = $totalCollected - $totalExpenses;

// Expected monthly income from active leases
$expectedMonthly = $leaseModel->expectedMonthlyIncome();

// Monthly data for chart (last 12 months)
$chartData = [];
for ($i = 11; $i >= 0; $i--) {
    $ts = strtotime("-$i months");
    $y = date('Y', $ts);
    $m = date('n', $ts);
    $inc = $paymentModel->totalCollected($y, $m);
    $exp = $expenseModel->totalExpense($y, $m);
    $chartData['labels'][] = monthName($m);
    $chartData['income'][] = $inc;
    $chartData['expense'][] = $exp;
}

// Recent payments
$recentPayments = $paymentModel->lastN(8);

// Available flats
$availableFlatsList = $flatModel->availableFlats();

require_once __DIR__ . '/inc/header.php';
?>

<!-- Stat Cards Row 1 -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 fade-in">
    <div>
        <div class="stat-card bg-primary-c">
            <i class="bi bi-cash-stack stat-icon"></i>
            <div class="stat-value"><?php echo money($monthlyIncome); ?></div>
            <div class="stat-label"><?php echo t('this_month'); ?> · <?php echo t('rent_collection'); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card bg-success-c">
            <i class="bi bi-people stat-icon"></i>
            <div class="stat-value"><?php echo bnNumeral($totalTenants); ?></div>
            <div class="stat-label"><?php echo t('total_tenants'); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card bg-info-c">
            <i class="bi bi-buildings stat-icon"></i>
            <div class="stat-value"><?php echo bnNumeral($totalBuildings); ?></div>
            <div class="stat-label"><?php echo t('total_buildings'); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card bg-purple-c">
            <i class="bi bi-file-earmark-text stat-icon"></i>
            <div class="stat-value"><?php echo bnNumeral($activeLeases); ?></div>
            <div class="stat-label"><?php echo t('active_leases'); ?></div>
        </div>
    </div>
</div>

<!-- Stat Cards Row 2 -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 fade-in">
    <div>
        <div class="stat-card bg-warning-c">
            <i class="bi bi-door-open stat-icon"></i>
            <div class="stat-value"><?php echo bnNumeral($totalFlats + $totalShops); ?> <small class="fs-6">/ <?php echo bnNumeral($occupiedFlats); ?></small>
                <?php if ($totalShops > 0): ?><small class="d-block fs-6"><?php echo bnNumeral($totalShops); ?> <?php echo plural($totalShops, t('shop'), t('shops')); ?></small><?php endif; ?>
            </div>
            <div class="stat-label"><?php echo t('total_flats'); ?> · <?php echo t('occupied_flats'); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card bg-danger-c">
            <i class="bi bi-door-closed stat-icon"></i>
            <div class="stat-value"><?php echo bnNumeral($availableFlats); ?></div>
            <div class="stat-label"><?php echo t('available_flats'); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card bg-indigo-c">
            <i class="bi bi-receipt stat-icon"></i>
            <div class="stat-value"><?php echo money($monthlyExpenses); ?></div>
            <div class="stat-label"><?php echo t('total_expenses'); ?> · <?php echo t('this_month'); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card" style="background: linear-gradient(135deg,#14b8a6,#0d9488);">
            <i class="bi bi-graph-up-arrow stat-icon"></i>
            <div class="stat-value"><?php echo money($netIncome); ?></div>
            <div class="stat-label"><?php echo t('net_income'); ?></div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-3 mb-4">
    <div class="lg:col-span-8">
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <span><i class="bi bi-bar-chart mr-1"></i> <?php echo t('monthly_stats'); ?></span>
                <span class="badge bg-light text-muted"><?php echo bnNumeral($year - 1); ?> - <?php echo bnNumeral($year); ?></span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="lg:col-span-4">
        <div class="card h-full">
            <div class="card-header"><i class="bi bi-pie-chart mr-1"></i> <?php echo t('overview'); ?></div>
            <div class="card-body">
                <div class="chart-container-sm">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="mt-3 text-center">
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div>
                            <div class="p-2 bg-light rounded">
                                <div class="small text-muted"><?php echo t('occupied_flats'); ?></div>
                                <div class="fs-5 fw-bold text-info"><?php echo bnNumeral($occupiedFlats); ?></div>
                            </div>
                        </div>
                        <div>
                            <div class="p-2 bg-light rounded">
                                <div class="small text-muted"><?php echo t('available_flats'); ?></div>
                                <div class="fs-5 fw-bold text-success"><?php echo bnNumeral($availableFlats); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Payments + Available Flats -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
    <div class="lg:col-span-7">
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <span><i class="bi bi-clock-history mr-1"></i> <?php echo t('recent_payments'); ?></span>
                <a href="payments.php" class="btn btn-sm btn-outline-primary"><?php echo t('view'); ?> <i class="bi bi-arrow-right ml-1"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th><?php echo t('tenant_name'); ?></th>
                                <th><?php echo t('flat_no'); ?></th>
                                <th><?php echo t('payment_month'); ?></th>
                                <th><?php echo t('amount'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentPayments)): ?>
                                <tr><td colspan="4" class="text-muted py-3"><?php echo t('no_data'); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($recentPayments as $p): ?>
                                    <tr>
                                        <td><?php echo e(localizeName($p['tenant_name'])); ?></td>
                                        <td><span class="badge bg-info-subtle text-info-emphasis"><?php echo e(bnFlatCode($p['flat_no'])); ?></span></td>
                                        <td><?php echo monthName($p['month']) . ' ' . bnNumeral($p['year']); ?></td>
                                        <td class="fw-semibold text-success"><?php echo money($p['total_amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="lg:col-span-5">
        <div class="card h-full">
            <div class="card-header flex items-center justify-between">
                <span><i class="bi bi-door-open mr-1"></i> <?php echo t('available_flats'); ?></span>
                <a href="flats.php" class="btn btn-sm btn-outline-success"><?php echo t('view'); ?> <i class="bi bi-arrow-right ml-1"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th><?php echo t('flat_no'); ?></th>
                                <th><?php echo t('building_name'); ?></th>
                                <th><?php echo t('rent_amount'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($availableFlatsList)): ?>
                                <tr><td colspan="3" class="text-muted py-3"><?php echo t('no_data'); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($availableFlatsList as $f): ?>
                                    <tr>
                                        <td><span class="badge bg-success-subtle text-success-emphasis"><?php echo e(bnFlatCode($f['flat_no'])); ?></span></td>
                                        <td><?php echo e(localizeText($f['building_name'])); ?></td>
                                        <td><?php echo money($f['rent_amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraJs = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Monthly Chart
    const labels = ' . json_encode($chartData['labels']) . ';
    const income = ' . json_encode($chartData['income']) . ';
    const expense = ' . json_encode($chartData['expense']) . ';
    const incomeLabel = ' . json_encode(t('income')) . ';
    const expenseLabel = ' . json_encode(t('expense')) . ';
    const statusLabels = ' . json_encode([t('occupied_flats'), t('available_flats'), t('maintenance')]) . ';

    const monthlyCtx = document.getElementById("monthlyChart");
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: incomeLabel,
                        data: income,
                        backgroundColor: "rgba(1, 124, 71, 0.7)",
                        borderColor: "#005a33",
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: expenseLabel,
                        data: expense,
                        backgroundColor: "rgba(239, 68, 68, 0.7)",
                        borderColor: "#dc2626",
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: "top" } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => formatMoney(v) } }
                }
            }
        });
    }

    // Status Pie Chart
    const statusCtx = document.getElementById("statusChart");
    if (statusCtx) {
        new Chart(statusCtx, {
            type: "doughnut",
            data: {
                labels: statusLabels,
                datasets: [{
                    data: [' . $occupiedFlats . ', ' . $availableFlats . ', ' . (int)$flatModel->count("status = 'maintenance'") . '],
                    backgroundColor: ["#007c47", "#2ea06a", "#f59e0b"],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: "bottom" } }
            }
        });
    }
});
</script>';
require __DIR__ . '/inc/footer.php';
?>