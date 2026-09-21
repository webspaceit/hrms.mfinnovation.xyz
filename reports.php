<?php
// ============================================================
// Reports Page - Yearly income/expense comparison
// ============================================================

require_once __DIR__ . '/classes/init.php';
Auth::requireLogin();

$pageTitle = t('reports');

$paymentModel = new Payment();
$expenseModel = new Expense();
$buildingModel = new Building();
$leaseModel = new Lease();

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

// Average
$avgIncome = $yearlyIncome / 12;

// Per building income
$perBuilding = $paymentModel->perBuildingSummary($year);

require_once __DIR__ . '/inc/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-graph-up mr-2"></i><?php echo t('reports'); ?></h4>
    <div>
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
            <select class="form-select form-select-sm" onchange="location='reports.php?lang=<?php echo Lang::current(); ?>&year='+this.value">
                <?php for ($y = date('Y') - 3; $y <= date('Y'); $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo bnNumeral($y); ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
</div>

<!-- Yearly Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4 fade-in">
    <div>
        <div class="stat-card bg-success-c">
            <i class="bi bi-cash-stack stat-icon"></i>
            <div class="stat-value"><?php echo money($yearlyIncome); ?></div>
            <div class="stat-label"><?php echo t('income'); ?> · <?php echo bnNumeral($year); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card bg-danger-c">
            <i class="bi bi-receipt stat-icon"></i>
            <div class="stat-value"><?php echo money($yearlyExpense); ?></div>
            <div class="stat-label"><?php echo t('expense'); ?> · <?php echo bnNumeral($year); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card" style="background: linear-gradient(135deg,#14b8a6,#0d9488);">
            <i class="bi bi-graph-up-arrow stat-icon"></i>
            <div class="stat-value"><?php echo money($yearlyProfit); ?></div>
            <div class="stat-label"><?php echo t('net_income'); ?> · <?php echo bnNumeral($year); ?></div>
        </div>
    </div>
</div>

<!-- Income vs Expense Chart -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-3 mb-4">
    <div class="lg:col-span-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart-line mr-1"></i> <?php echo t('monthly_stats'); ?> - <?php echo bnNumeral($year); ?></div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="reportChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="lg:col-span-4">
        <div class="card h-full">
            <div class="card-header"><i class="bi bi-building mr-1"></i> <?php echo t('income'); ?> / <?php echo t('building_name'); ?></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($perBuilding)): ?>
                        <li class="list-group-item text-muted text-center py-4"><?php echo t('no_data'); ?></li>
                    <?php else: ?>
                        <?php
                        $max = 0;
                        foreach ($perBuilding as $pb) { if ($pb['total'] > $max) $max = $pb['total']; }
                        foreach ($perBuilding as $pb): ?>
                            <li class="list-group-item">
                                <div class="flex justify-between mb-1">
                                    <span><?php echo e(localizeText($pb['name'])); ?></span>
                                    <span class="fw-semibold text-success"><?php echo money($pb['total']); ?></span>
                                </div>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $max > 0 ? ($pb['total'] / $max * 100) : 0; ?>%"></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Table -->
<div class="card fade-in">
    <div class="card-header"><i class="bi bi-table mr-1"></i> <?php echo t('yearly_report'); ?> - <?php echo bnNumeral($year); ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th><?php echo t('payment_month'); ?></th>
                        <th><?php echo t('income'); ?></th>
                        <th><?php echo t('expense'); ?></th>
                        <th><?php echo t('profit'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <?php
                            $inc = $monthlyIncome[$m];
                            $exp = $monthlyExpense[$m];
                            $profit = $inc - $exp;
                        ?>
                        <tr>
                            <td><?php echo monthName($m); ?></td>
                            <td class="text-success"><?php echo money($inc); ?></td>
                            <td class="text-danger"><?php echo money($exp); ?></td>
                            <td class="fw-semibold <?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo money($profit); ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <th><?php echo t('total'); ?></th>
                        <th class="text-success"><?php echo money($yearlyIncome); ?></th>
                        <th class="text-danger"><?php echo money($yearlyExpense); ?></th>
                        <th><?php echo money($yearlyProfit); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php
$labels = [];
$income = [];
$expense = [];
for ($m = 1; $m <= 12; $m++) {
    $labels[] = monthName($m);
    $income[] = $monthlyIncome[$m];
    $expense[] = $monthlyExpense[$m];
}

$extraJs = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById("reportChart");
    if (ctx) {
        new Chart(ctx, {
            type: "line",
            data: {
                labels: ' . json_encode($labels) . ',
                datasets: [
                    {
                        label: ' . json_encode(t("income")) . ',
                        data: ' . json_encode($income) . ',
                        borderColor: "#007c47",
                        backgroundColor: "rgba(1,124,71,0.15)",
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    },
                    {
                        label: ' . json_encode(t("expense")) . ',
                        data: ' . json_encode($expense) . ',
                        borderColor: "#ef4444",
                        backgroundColor: "rgba(239,68,68,0.15)",
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
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
});
</script>';
require __DIR__ . '/inc/footer.php';
?>