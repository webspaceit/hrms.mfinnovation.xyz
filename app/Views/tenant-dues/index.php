<?php $pageTitle = t('my_dues'); require dirname(__DIR__) . '/partials/tenant_header.php'; ?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text mr-2"></i><?php echo t('my_dues'); ?></h4>
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

<p class="text-muted small mb-3"><?php echo t('my_dues_hint'); ?></p>

<div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4 fade-in">
    <div class="stat-card bg-primary-c">
        <i class="bi bi-receipt stat-icon"></i>
        <div class="stat-value"><?php echo money($totals['due']); ?></div>
        <div class="stat-label"><?php echo t('total_due'); ?></div>
    </div>
    <div class="stat-card bg-info-c">
        <i class="bi bi-cash-coin stat-icon"></i>
        <div class="stat-value"><?php echo money($totals['paid']); ?></div>
        <div class="stat-label"><?php echo t('total_paid'); ?></div>
    </div>
    <div class="stat-card <?php echo $totals['outstanding'] > 0 ? 'bg-danger-c' : 'bg-success-c'; ?>">
        <i class="bi bi-exclamation-circle stat-icon"></i>
        <div class="stat-value"><?php echo money($totals['outstanding']); ?></div>
        <div class="stat-label"><?php echo $totals['outstanding'] > 0 ? t('due_amount') : t('no_due'); ?></div>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo t('payment_month'); ?></th>
                        <th><?php echo t('flat_no'); ?></th>
                        <th><?php echo t('total_due'); ?></th>
                        <th><?php echo t('paid_amount'); ?></th>
                        <th><?php echo t('balance'); ?></th>
                        <th><?php echo t('status'); ?></th>
                        <th><?php echo t('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="8" class="text-muted py-4"><?php echo $totals['outstanding'] > 0 ? t('no_data') : t('no_due'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $i => $inv): ?>
                            <?php $balance = max(0, (float)$inv['total_due'] - (float)$inv['paid_amount']); ?>
                            <tr>
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td><?php echo monthName((int)$inv['month']) . ' ' . bnNumeral($inv['year']); ?></td>
                                <td><span class="badge bg-primary-subtle text-primary-emphasis"><?php echo e(bnFlatCode($inv['flat_no'] ?? '-')); ?></span></td>
                                <td><?php echo money($inv['total_due']); ?></td>
                                <td class="text-success"><?php echo money($inv['paid_amount']); ?></td>
                                <td class="fw-semibold <?php echo $balance > 0 ? 'text-danger' : 'text-muted'; ?>"><?php echo money($balance); ?></td>
                                <td><?php echo paymentStatusBadge($inv['status_effective'] ?? 'unpaid'); ?></td>
                                <td>
                                    <?php echo actionButtons([
                                        ['label' => t('view_demand_note'), 'icon' => 'bi bi-file-earmark-text', 'variant' => 'view',
                                         'href' => url('tenant-invoice-view') . '?id=' . (int)$inv['id']],
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