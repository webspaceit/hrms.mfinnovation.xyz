<?php
// Tenant Payments view - data: tenantId, years, year, payments, totalPaid
$pageTitle = t('my_rent');
require dirname(__DIR__) . '/partials/tenant_header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-cash-stack mr-2"></i><?php echo t('my_rent'); ?></h4>
    <?php if (count($years) > 1): ?>
        <form method="GET" action="">
            <select class="form-select form-select-sm" style="max-width: 130px;" onchange="this.form.submit()" name="year">
                <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo bnNumeral($y); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<p class="text-muted small mb-3"><?php echo t('my_rent_hint'); ?></p>

<div class="card fade-in mb-4">
    <div class="card-body py-3">
        <span class="text-muted"><?php echo t('total_paid'); ?> (<?php echo bnNumeral($year); ?>):</span>
        <strong class="text-success ml-1"><?php echo money($totalPaid); ?></strong>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo t('payment_date'); ?></th>
                        <th><?php echo t('payment_month'); ?></th>
                        <th><?php echo t('amount'); ?></th>
                        <th><?php echo t('payment_method'); ?></th>
                        <th><?php echo t('status'); ?></th>
                        <th><?php echo t('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="7" class="text-muted py-4"><?php echo t('no_data'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $i => $p): ?>
                            <?php $invStatus = $p['inv_status_effective'] ?? $p['payment_status'] ?? null; ?>
                            <tr>
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td><?php echo e(bnNumeral(date('d-m-Y', strtotime($p['payment_date'])))); ?></td>
                                <td><?php echo monthName((int)$p['month']) . ' ' . bnNumeral($p['year']); ?></td>
                                <td class="fw-semibold text-success"><?php echo money($p['total_amount']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo t($p['payment_method'], $p['payment_method']); ?></span></td>
                                <td>
                                    <?php if ($invStatus === 'paid'): ?>
                                        <span class="badge bg-success-subtle text-success-emphasis"><?php echo t('status_paid'); ?></span>
                                    <?php elseif ($invStatus === 'partial'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis"><?php echo t('status_partial'); ?></span>
                                    <?php elseif ($invStatus === 'unpaid'): ?>
                                        <span class="badge bg-danger-subtle text-danger-emphasis"><?php echo t('status_unpaid'); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo actionButtons([
                                        ['label' => t('view_receipt'), 'icon' => 'bi bi-receipt', 'variant' => 'view',
                                         'href' => url('tenant-receipt') . '?id=' . (int)$p['id']],
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

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>