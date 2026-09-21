<?php
// ============================================================
// Tenant Portal - My Home
// Every query is scoped to the tenant id stored in the portal
// session, so a tenant can only ever see his/her own data.
// ============================================================

require_once __DIR__ . '/classes/init.php';
Auth::requireTenantLogin();

$tenantId = Auth::tenantId();
$pageTitle = t('my_home');

$tenantModel  = new Tenant();
$leaseModel   = new Lease();
$paymentModel = new Payment();
$invoiceModel = new Invoice();

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

require_once __DIR__ . '/inc/tenant_header.php';
?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 fade-in">
    <div>
        <div class="stat-card bg-primary-c">
            <i class="bi bi-calendar-check stat-icon"></i>
            <div class="stat-value"><?php echo money($monthlyRent); ?></div>
            <div class="stat-label"><?php echo t('monthly_rent'); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card <?php echo $monthDue > 0 ? 'bg-danger-c' : 'bg-success-c'; ?>">
            <i class="bi bi-exclamation-circle stat-icon"></i>
            <div class="stat-value"><?php echo money($monthDue); ?></div>
            <div class="stat-label"><?php echo t('current_month_due'); ?> · <?php echo monthName($month); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card bg-warning-c">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div class="stat-value"><?php echo money($allTotals['outstanding']); ?></div>
            <div class="stat-label"><?php echo t('outstanding'); ?></div>
        </div>
    </div>
    <div>
        <div class="stat-card bg-info-c">
            <i class="bi bi-cash-coin stat-icon"></i>
            <div class="stat-value"><?php echo money($paidThisYear); ?></div>
            <div class="stat-label"><?php echo t('paid_this_year'); ?> · <?php echo bnNumeral($year); ?></div>
        </div>
    </div>
</div>

<?php if ($currentInvoice): ?>
<div class="card fade-in mb-4">
    <div class="card-body">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h5 class="card-title mb-0"><i class="bi bi-file-earmark-text mr-1"></i><?php echo t('due_invoice'); ?> · <?php echo monthName((int)$month) . ' ' . bnNumeral($year); ?></h5>
            <a href="<?php echo BASE_URL; ?>tenant_invoice_view.php?id=<?php echo (int)$currentInvoice['id']; ?>" class="btn btn-sm btn-outline-primary">
                <?php echo t('view_demand_note'); ?> <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="flex flex-wrap gap-4">
            <div>
                <span class="text-muted small"><?php echo t('total_due'); ?></span>
                <div class="fw-semibold fs-5"><?php echo money($currentInvoice['total_due']); ?></div>
            </div>
            <div>
                <span class="text-muted small"><?php echo t('paid_amount'); ?></span>
                <div class="fw-semibold fs-5 text-success"><?php echo money($currentInvoice['paid_amount']); ?></div>
            </div>
            <div>
                <span class="text-muted small"><?php echo t('status'); ?></span>
                <div class="mt-1"><?php echo paymentStatusBadge($currentInvoice['status_effective'] ?? 'unpaid'); ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-body">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h5 class="card-title mb-0"><i class="bi bi-cash-stack mr-1"></i><?php echo t('recent_payments'); ?></h5>
            <a href="<?php echo BASE_URL; ?>tenant_payments.php?lang=<?php echo Lang::current(); ?>" class="btn btn-sm btn-outline-primary">
                <?php echo t('my_rent'); ?> <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo t('payment_month'); ?></th>
                        <th><?php echo t('payment_date'); ?></th>
                        <th><?php echo t('amount'); ?></th>
                        <th><?php echo t('payment_status'); ?></th>
                        <th><?php echo t('view_receipt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentPayments)): ?>
                        <tr><td colspan="6" class="text-muted py-4"><?php echo t('no_data'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($recentPayments as $i => $p): ?>
                            <tr>
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td><?php echo monthName((int)$p['month']) . ' ' . bnNumeral($p['year']); ?></td>
                                <td><?php echo e(bnNumeral(date('d-m-Y', strtotime($p['payment_date'])))); ?></td>
                                <td class="fw-semibold text-success"><?php echo money($p['total_amount']); ?></td>
                                <td><?php echo paymentStatusBadge($p['inv_status_effective'] ?? 'unpaid'); ?></td>
                                <td>
                                    <?php echo actionButtons([
                                        ['label' => t('view_receipt'), 'icon' => 'bi bi-receipt', 'variant' => 'view',
                                         'href' => BASE_URL . 'tenant_receipt.php?id=' . (int)$p['id']],
                                    ]); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
