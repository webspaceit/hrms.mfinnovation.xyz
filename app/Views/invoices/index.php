<?php
// Invoices view - data: invoices, paidCount, showPaid, totalDue, invoiceDataJson,
// sendsIcons (closure), year, month
$pageTitle = t('invoices');
require dirname(__DIR__) . '/partials/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text mr-2"></i><?php echo t('invoices'); ?></h4>
    <div class="flex items-center gap-2">
        <button class="btn btn-primary" id="generateInvoicesBtn" onclick="generateInvoices()">
            <i class="bi bi-magic mr-1"></i> <?php echo t('generate_invoices'); ?>
        </button>
        <button class="btn btn-outline-primary" id="backfillInvoicesBtn" onclick="openBackfillModal()">
            <i class="bi bi-calendar-range mr-1"></i> <?php echo t('generate_previous_months'); ?>
        </button>
        <button class="btn btn-danger" id="bulkDeleteBtn" onclick="bulkDeleteInvoices()" style="display: none;">
            <i class="bi bi-trash mr-1"></i> <?php echo t('delete_selected'); ?>
        </button>
    </div>
</div>

<!-- Filter -->
<div class="filter-bar mb-3 fade-in flex flex-wrap items-center gap-2">
    <span class="filter-label"><i class="bi bi-funnel mr-1"></i> <?php echo t('filter'); ?></span>
    <?php $showPaidQ = $showPaid ? '&show_paid=1' : ''; ?>
    <select class="form-select form-select-sm" style="max-width: 170px;" onchange="location='<?php echo url('invoices'); ?>?month='+this.value+'&year=<?php echo $year; ?><?php echo $showPaidQ; ?>'">
        <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>><?php echo monthName($m); ?></option>
        <?php endfor; ?>
    </select>
    <select class="form-select form-select-sm" style="max-width: 130px;" onchange="location='<?php echo url('invoices'); ?>?month=<?php echo $month; ?>&year='+this.value+'<?php echo $showPaidQ; ?>'">
        <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo bnNumeral($y); ?></option>
        <?php endfor; ?>
    </select>
    <div class="ml-auto flex items-center gap-2">
        <span class="text-muted"><?php echo t('total_due'); ?>: <strong><?php echo money($totalDue); ?></strong></span>
        <?php if ($invoices && !$showPaid): ?>
            <button class="btn btn-sm btn-info text-white" onclick="sendAll('email', this)"><i class="bi bi-envelope mr-1"></i><?php echo t('send_all_email'); ?></button>
            <button class="btn btn-sm btn-warning" onclick="sendAll('sms', this)"><i class="bi bi-chat-dots mr-1"></i><?php echo t('send_all_sms'); ?></button>
            <button id="bulkDeleteBtn2" class="btn btn-sm btn-danger" style="display: none;" onclick="bulkDeleteInvoices()"><i class="bi bi-trash mr-1"></i> Delete Selected (0)</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($paidCount > 0): ?>
    <div class="alert alert-success py-2 small mb-2 fade-in d-flex flex-wrap align-items-center gap-2">
        <span>
            <i class="bi bi-check2-circle mr-1"></i>
            <?php echo str_replace('{n}', bnNumeral($paidCount), t($showPaid ? 'paid_shown_count' : 'paid_moved_rent')); ?>
        </span>
        <a class="btn btn-sm btn-outline-success ms-auto"
           href="<?php echo url('invoices') . '?month=' . (int)$month . '&year=' . (int)$year . ($showPaid ? '' : '&show_paid=1'); ?>">
            <i class="bi bi-eye<?php echo $showPaid ? '-slash' : ''; ?> me-1"></i>
            <?php echo t($showPaid ? 'hide_paid_invoices' : 'show_paid_invoices'); ?>
        </a>
    </div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?php if (!$showPaid): ?><input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleBulkSelect()"><?php endif; ?></th>
                        <th>#</th>
                        <th><?php echo t('tenant_name'); ?></th>
                        <th><?php echo t('building_name'); ?></th>
                        <th><?php echo t('flat_shop_no'); ?></th>
                        <th><?php echo t('monthly_rent'); ?></th>
                        <th><?php echo t('arrears'); ?></th>
                        <th><?php echo t('total_due'); ?></th>
                        <th><?php echo t('payment_status'); ?></th>
                        <th><?php echo t('delivery_status'); ?></th>
                        <th><?php echo t('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="11" class="text-muted py-4">
                            <?php if ($paidCount > 0): ?>
                                <?php echo t('all_paid_invoices'); ?>
                                <?php if (!$showPaid): ?>
                                    <div class="small mt-2">
                                        <?php echo t('paid_hidden_hint'); ?>
                                        <a href="<?php echo url('invoices') . '?month=' . (int)$month . '&year=' . (int)$year . '&show_paid=1'; ?>">
                                            <?php echo t('show_paid_invoices'); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php echo t('no_invoices'); ?>
                                <button class="btn btn-sm btn-outline-primary ml-2" onclick="generateInvoices()"><?php echo t('generate_invoices'); ?></button>
                            <?php endif; ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $i => $inv): ?>
                            <tr data-invoice-id="<?php echo (int)$inv['id']; ?>">
                                <td><?php if (!$showPaid): ?><input type="checkbox" class="invoice-checkbox form-check-input" value="<?php echo (int)$inv['id']; ?>"><?php endif; ?></td>
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td class="fw-semibold"><?php echo e(localizeName($inv['tenant_name'])); ?>
                                    <div class="small text-muted"><?php echo e($inv['tenant_email'] ?: ''); ?></div>
                                </td>
                                <td><?php echo e(localizeText($inv['building_name'])); ?>
                                    <?php if ($inv['unit_type'] === 'shop'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis ml-1"><?php echo t('shop_unit'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary-emphasis"><?php echo e(bnFlatCode($inv['flat_no'])); ?></span></td>
                                <td><?php echo money($inv['rent_amount']); ?></td>
                                <td>
                                    <?php if ((float)$inv['arrears'] > 0): ?>
                                        <span class="text-danger fw-semibold"><?php echo money($inv['arrears']); ?></span>
                                        <div class="small text-muted"><?php echo bnNumeral($inv['arrears_months']); ?> <?php echo t('months'); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold <?php echo (float)$inv['total_due'] > 0 ? 'text-danger' : ''; ?>"><?php echo money($inv['total_due']); ?></td>
                                <td class="whitespace-nowrap">
                                    <?php
                                    $effStatus = (string)($inv['payment_status_override'] ?: ($inv['payment_status'] ?: 'unpaid'));
                                    $isOverride = !empty($inv['payment_status_override']);
                                    ?>
                                    <select class="form-select form-select-sm invoice-status-select"
                                            data-invoice-id="<?php echo (int)$inv['id']; ?>"
                                            onchange="saveInvoiceStatus(this)"
                                            style="min-width: 128px;">
                                        <option value="auto" <?php echo !$isOverride ? 'selected' : ''; ?>><?php echo t('auto') . ' (' . t('status_' . $effStatus, $effStatus) . ')'; ?></option>
                                        <option value="paid" <?php echo $isOverride && $inv['payment_status_override'] === 'paid' ? 'selected' : ''; ?>><?php echo t('status_paid'); ?></option>
                                        <option value="partial" <?php echo $isOverride && $inv['payment_status_override'] === 'partial' ? 'selected' : ''; ?>><?php echo t('status_partial'); ?></option>
                                        <option value="unpaid" <?php echo $isOverride && $inv['payment_status_override'] === 'unpaid' ? 'selected' : ''; ?>><?php echo t('status_unpaid'); ?></option>
                                    </select>
                                    <?php if ($isOverride): ?>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis ml-1" title="<?php echo t('manual_status_override'); ?>"><?php echo t('manual'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="mr-1"><?php echo $sendsIcons($inv); ?></span>
                                    <?php if ($inv['status'] === 'sent'): ?>
                                        <span class="badge bg-success-subtle text-success-emphasis"><?php echo t('sent'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis"><?php echo t('draft'); ?></span>
                                    <?php endif; ?>
                                    <?php if ((int)$inv['is_manual'] === 1): ?>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis" title="<?php echo t('manual_override'); ?>"><?php echo t('manual'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo actionButtons([
                                        ['label' => t('edit_invoice'), 'icon' => 'bi bi-pencil', 'variant' => 'edit', 'onclick' => 'editInvoice(' . (int)$inv['id'] . ')'],
                                        ['label' => t('view_invoice'), 'icon' => 'bi bi-eye', 'variant' => 'view',
                                         'href' => url('invoice-view') . '?id=' . (int)$inv['id'] . '&lang=' . Lang::current()],
                                        ['label' => t('send_email'), 'icon' => 'bi bi-envelope', 'variant' => 'info', 'onclick' => 'sendInvoice(' . (int)$inv['id'] . ', \'email\')'],
                                        ['label' => t('send_whatsapp'), 'icon' => 'bi bi-whatsapp', 'variant' => 'success', 'onclick' => 'sendInvoice(' . (int)$inv['id'] . ', \'whatsapp\')'],
                                        ['label' => t('send_sms'), 'icon' => 'bi bi-chat-dots', 'variant' => 'primary', 'onclick' => 'sendInvoice(' . (int)$inv['id'] . ', \'sms\')'],
                                        ['label' => t('delete'), 'icon' => 'bi bi-trash', 'variant' => 'delete', 'onclick' => 'deleteInvoice(' . (int)$inv['id'] . ')'],
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

<!-- Bulk Delete Confirmation Modal -->
<div class="modal" id="bulkDeleteModal" data-close-on-backdrop="false" data-close-on-escape="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle mr-1"></i><?php echo t('confirm_bulk_delete'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('bulkDeleteModal')" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p><?php echo t('bulk_delete_confirm'); ?></p>
                <ul id="deleteCountList" class="small"></ul>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-secondary px-4" onclick="closeModal('bulkDeleteModal')"><?php echo t('cancel'); ?></button>
                <button type="button" class="btn btn-danger px-4" onclick="performBulkDelete()"><i class="bi bi-trash mr-1"></i><?php echo t('delete_selected'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Mark Paid Modal (sets the actual paid date) -->
<div class="modal" id="markPaidModal" data-close-on-backdrop="false" data-close-on-escape="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-check2-circle mr-1"></i><?php echo t('mark_fully_paid'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('markPaidModal')" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mp_invoice_id" value="">
                <label class="form-label"><?php echo t('payment_date'); ?> <span class="text-danger">*</span></label>
                <input type="text" id="mp_date" class="form-control" data-date value="<?php echo date('d-m-Y'); ?>" placeholder="DD-MM-YYYY" inputmode="numeric" required>
                <div class="form-text"><?php echo t('mark_paid_date_hint'); ?></div>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-secondary px-4" onclick="cancelMarkPaid()"><?php echo t('cancel'); ?></button>
                <button type="button" class="btn btn-success px-4" onclick="confirmMarkPaid()"><i class="bi bi-check2-circle mr-1"></i><?php echo t('mark_fully_paid'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Invoice Modal -->
<div class="modal" id="editInvoiceModal" data-close-on-backdrop="false" data-close-on-escape="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square mr-2"></i><?php echo t('edit_invoice'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('editInvoiceModal')" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editInvoiceId" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-house mr-1"></i><?php echo t('monthly_rent'); ?></label>
                        <input type="text" class="form-control form-control-lg" id="editRentAmount" placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-lightning mr-1"></i><?php echo t('utility_fee'); ?></label>
                        <input type="text" class="form-control form-control-lg" id="editUtilityFee" placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-p-square mr-1"></i><?php echo t('parking_bill'); ?></label>
                        <input type="text" class="form-control form-control-lg" id="editParking" placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-flame mr-1"></i><?php echo t('gas_bill'); ?></label>
                        <input type="text" class="form-control form-control-lg" id="editGas" placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-droplet mr-1"></i><?php echo t('water_bill'); ?></label>
                        <input type="text" class="form-control form-control-lg" id="editWater" placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-trash mr-1"></i><?php echo t('waste_bill'); ?></label>
                        <input type="text" class="form-control form-control-lg" id="editWaste" placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-exclamation-triangle mr-1"></i><?php echo t('arrears'); ?></label>
                        <input type="text" class="form-control form-control-lg" id="editArrears" placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-calendar mr-1"></i><?php echo t('arrears_months'); ?></label>
                        <input type="number" class="form-control form-control-lg" id="editArrearsMonths" value="0" min="0">
                    </div>
                    <div>
                        <label class="form-label fw-semibold"><i class="bi bi-calendar-event mr-1"></i><?php echo t('due_date'); ?></label>
                        <input type="text" class="form-control form-control-lg" id="editDueDate" placeholder="DD-MM-YYYY" inputmode="numeric" data-date>
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label fw-semibold"><i class="bi bi-card-text mr-1"></i><?php echo t('note'); ?></label>
                        <textarea class="form-control" id="editNote" rows="2" placeholder="Optional note..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-secondary px-4" onclick="closeModal('editInvoiceModal')"><?php echo t('cancel'); ?></button>
                <button type="button" class="btn btn-primary px-4" onclick="saveInvoiceEdit()"><i class="bi bi-check2-circle mr-1"></i><?php echo t('save'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Backfill Previous Months Modal -->
<div class="modal" id="backfillModal" data-close-on-backdrop="false" data-close-on-escape="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-range mr-1"></i> <?php echo t('previous_months'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('backfillModal')" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="form-label"><?php echo t('from_month'); ?></label>
                        <select class="form-select" id="bf_from_month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo monthName($m); ?></option>
                            <?php endfor; ?>
                        </select>
                        <select class="form-select mt-2" id="bf_from_year">
                            <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y === (int)date('Y') - 1 ? 'selected' : ''; ?>><?php echo bnNumeral($y); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label"><?php echo t('to_month'); ?></label>
                        <select class="form-select" id="bf_to_month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo monthName($m); ?></option>
                            <?php endfor; ?>
                        </select>
                        <select class="form-select mt-2" id="bf_to_year">
                            <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y === (int)date('Y') ? 'selected' : ''; ?>><?php echo bnNumeral($y); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-text mt-3"><?php echo t('backfill_hint'); ?></div>
                <?php if ($billableMonths): ?>
                    <div class="alert alert-info py-2 small mt-2 mb-0 fade-in">
                        <i class="bi bi-info-circle mr-1"></i>
                        <?php echo t('billable_from'); ?>
                        <strong><?php echo monthName($billableMonths[0][1]) . ' ' . bnNumeral($billableMonths[0][0]); ?></strong>
                        &ndash;
                        <strong><?php echo monthName($billableMonths[count($billableMonths) - 1][1]) . ' ' . bnNumeral($billableMonths[count($billableMonths) - 1][0]); ?></strong>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning py-2 small mt-2 mb-0 fade-in">
                        <i class="bi bi-exclamation-triangle mr-1"></i>
                        <?php echo str_replace('{year}', bnNumeral($year), t('no_billable_month')); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-secondary px-4" onclick="closeModal('backfillModal')"><?php echo t('cancel'); ?></button>
                <button type="button" class="btn btn-primary px-4" onclick="runBackfill()"><i class="bi bi-magic mr-1"></i> <?php echo t('generate'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php
$extraJs = '
<script>
let invoiceData = ' . ($invoiceDataJson ?? '[]') . ';
let selectedInvoices = [];
let currentMonth = ' . (int)$month . ';
let currentYear = ' . (int)$year . ';

// Months that have at least one active lease, so the backfill dialog can
// default to a range that actually bills somebody. Passede as [year, month].
const BILLABLE_MONTHS = ' . json_encode($billableMonths) . ';

function generateInvoices() {
    var btn = document.getElementById("generateInvoicesBtn");
    if (btn) { btn.disabled = true; btn.innerHTML = `<i class="bi bi-hourglass-split"></i> Generating...`; }
    fetch(BASE_URL + "ajax/invoice_generate.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "month=" + currentMonth + "&year=" + currentYear + "&csrf_token=" + CSRF_TOKEN
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            showToast(result.message || "Invoices generated successfully", "success");
            setTimeout(function() { location.reload(); }, 800);
        } else {
            showToast(result.message || "Failed to generate invoices", "danger");
            if (btn) { btn.disabled = false; btn.innerHTML = `<i class="bi bi-magic mr-1"></i> ` + ' . json_encode(t('generate_invoices')) . '; }
        }
    })
    .catch(function() {
        showToast("Request failed", "danger");
        if (btn) { btn.disabled = false; btn.innerHTML = `<i class="bi bi-magic mr-1"></i> ` + ' . json_encode(t('generate_invoices')) . '; }
    });
}

function openBackfillModal() {
    // Default to the newest month that actually has an active lease, both ends.
    // Defaulting to the current month (or the previous one) opened the dialog
    // on a range that predates every lease in a fresh install, so the first
    // click could only ever report "nothing to generate".
    var y, m;
    if (BILLABLE_MONTHS && BILLABLE_MONTHS.length) {
        var last = BILLABLE_MONTHS[BILLABLE_MONTHS.length - 1];
        y = String(last[0]);
        m = String(last[1]);
    } else {
        var now = new Date();
        y = String(now.getFullYear());
        m = String(now.getMonth() + 1);
    }
    document.getElementById("bf_to_month").value = m;
    document.getElementById("bf_to_year").value = y;
    document.getElementById("bf_from_month").value = m;
    document.getElementById("bf_from_year").value = y;
    openModal("backfillModal");
}

function runBackfill() {
    const btn = document.getElementById("backfillInvoicesBtn");
    const data = new URLSearchParams();
    data.append("from_month", document.getElementById("bf_from_month").value);
    data.append("from_year", document.getElementById("bf_from_year").value);
    data.append("to_month", document.getElementById("bf_to_month").value);
    data.append("to_year", document.getElementById("bf_to_year").value);
    data.append("csrf_token", CSRF_TOKEN);

    const original = btn ? btn.innerHTML : null;
    if (btn) { btn.disabled = true; btn.innerHTML = `<i class="bi bi-hourglass-split"></i> Generating...`; }

    fetch(BASE_URL + "ajax/invoice_backfill.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: data.toString()
    })
    .then(res => res.json())
    .then(function (result) {
        closeModal("backfillModal");
        if (btn) { btn.disabled = false; btn.innerHTML = original; }
        if (result.success) {
            // "Nothing to generate" is a valid outcome, not a failure, so it
            // gets an informational toast instead of a red error one.
            showToast(result.message || "Invoices generated successfully", result.nothing ? "info" : "success");
            setTimeout(function () { location.reload(); }, 1200);
        } else {
            showToast(result.message || "Failed to generate invoices", "danger");
        }
    })
    .catch(function () {
        closeModal("backfillModal");
        showToast("Request failed", "danger");
        if (btn) { btn.disabled = false; btn.innerHTML = original; }
    });
}

function editInvoice(id) {
    var inv = invoiceData[id];
    if (!inv) return;
    document.getElementById("editInvoiceId").value = id;
    document.getElementById("editRentAmount").value = inv.rent_amount ?? 0;
    document.getElementById("editUtilityFee").value = inv.utility_fee ?? 0;
    document.getElementById("editParking").value = inv.parking_amount ?? 0;
    document.getElementById("editGas").value = inv.gas_amount ?? 0;
    document.getElementById("editWater").value = inv.water_fee ?? 0;
    document.getElementById("editWaste").value = inv.waste_fee ?? 0;
    document.getElementById("editArrears").value = inv.arrears ?? 0;
    document.getElementById("editArrearsMonths").value = inv.arrears_months ?? 0;
    document.getElementById("editDueDate").value = inv.due_date || "";
    document.getElementById("editNote").value = inv.note || "";
    openModal("editInvoiceModal");
}

function saveInvoiceEdit() {
    var id = document.getElementById("editInvoiceId").value;
    var data = new URLSearchParams();
    data.append("id", id);
    data.append("rent_amount", document.getElementById("editRentAmount").value);
    data.append("utility_fee", document.getElementById("editUtilityFee").value);
    data.append("parking_amount", document.getElementById("editParking").value);
    data.append("gas_amount", document.getElementById("editGas").value);
    data.append("water_fee", document.getElementById("editWater").value);
    data.append("waste_fee", document.getElementById("editWaste").value);
    data.append("arrears", document.getElementById("editArrears").value);
    data.append("arrears_months", document.getElementById("editArrearsMonths").value);
    data.append("due_date", document.getElementById("editDueDate").value);
    data.append("note", document.getElementById("editNote").value);
    data.append("csrf_token", CSRF_TOKEN);
    fetch(BASE_URL + "ajax/invoice_update.php", {
        method: "POST",
        body: data
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, "success");
            closeModal("editInvoiceModal");
            setTimeout(function() { location.reload(); }, 600);
        } else {
            showToast(result.message || "Error", "danger");
        }
    })
    .catch(function() { showToast("Request failed", "danger"); });
}

function sendInvoice(id, channel) {
    fetch(BASE_URL + "ajax/invoice_send.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + id + "&channel=" + channel + "&csrf_token=" + CSRF_TOKEN
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, "success");
            setTimeout(function() { location.reload(); }, 800);
        } else {
            showToast(result.message || "Failed", "danger");
        }
    })
    .catch(function() { showToast("Request failed", "danger"); });
}

let pendingInvoiceSel = null;

function saveInvoiceStatus(sel) {
    var val = sel.value;
    if (val === "paid") {
        // Ask for the actual paid date first (cancel restores the select).
        pendingInvoiceSel = sel;
        document.getElementById("mp_invoice_id").value = sel.dataset.invoiceId;
        document.getElementById("mp_date").value = ' . json_encode(date('d-m-Y')) . ';
        openModal("markPaidModal");
        return;
    }
    var prev = sel.dataset.prev || "auto";
    sel.dataset.prev = val;
    fetch(BASE_URL + "ajax/invoice_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + sel.dataset.invoiceId + "&status=" + val + "&csrf_token=" + CSRF_TOKEN
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, "success");
            setTimeout(function() { location.reload(); }, 500);
        } else {
            sel.value = prev;
            showToast(result.message || "Failed", "danger");
        }
    })
    .catch(function() { sel.value = prev; showToast("Request failed", "danger"); });
}

function confirmMarkPaid() {
    const date = toAsciiDigits(document.getElementById("mp_date").value || "");
    if (!date) {
        showToast(' . json_encode(t('set_paid_date_required')) . ', "danger");
        return;
    }
    const invId = document.getElementById("mp_invoice_id").value;
    const sel = pendingInvoiceSel;
    pendingInvoiceSel = null;
    if (sel) sel.dataset.prev = "paid";
    fetch(BASE_URL + "ajax/invoice_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + invId + "&status=paid&payment_date=" + encodeURIComponent(date) + "&csrf_token=" + CSRF_TOKEN
    })
    .then(res => res.json())
    .then(function(result) {
        closeModal("markPaidModal");
        showToast(result.message || "Error", result.success ? "success" : "danger");
        if (result.success) {
            setTimeout(function() { location.reload(); }, 600);
        } else if (sel) {
            sel.value = sel.dataset.prev || "auto";
        }
    })
    .catch(function() {
        closeModal("markPaidModal");
        if (sel) sel.value = sel.dataset.prev || "auto";
        showToast("Request failed", "danger");
    });
}

function cancelMarkPaid() {
    const sel = pendingInvoiceSel;
    pendingInvoiceSel = null;
    if (sel) sel.value = sel.dataset.prev || "auto";
    closeModal("markPaidModal");
}

// If the modal is dismissed (Escape / backdrop) without confirming, restore
// the status select so it does not stay on "paid" unsaved.
document.addEventListener("modal:closed", function(e) {
    if (e.detail.id === "markPaidModal" && pendingInvoiceSel) {
        const sel = pendingInvoiceSel;
        pendingInvoiceSel = null;
        if (sel) sel.value = sel.dataset.prev || "auto";
    }
});

function deleteInvoice(id) {
    confirmDelete(BASE_URL + "ajax/invoice_delete.php?id=" + id, ' . json_encode(t('delete_confirm')) . ');
}

function sendAll(channel, btn) {
    var ids = Object.keys(invoiceData).map(Number);
    if (ids.length === 0) {
        showToast("No invoices to send", "warning");
        return;
    }
    var btnText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = "Sending...";
    var sent = 0;
    var failed = 0;
    var completed = 0;
    ids.forEach(function(id) {
        fetch(BASE_URL + "ajax/invoice_send.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "id=" + id + "&channel=" + channel + "&csrf_token=" + CSRF_TOKEN
        })
        .then(res => res.json())
        .then(function(result) {
            if (result.success) sent++; else failed++;
            completed++;
            if (completed === ids.length) {
                showToast("Sent: " + sent + ", Failed: " + failed, sent > 0 ? "success" : "danger");
                btn.disabled = false;
                btn.innerHTML = btnText;
                setTimeout(function() { location.reload(); }, 800);
            }
        })
        .catch(function() {
            failed++;
            completed++;
            if (completed === ids.length) {
                showToast("Sent: " + sent + ", Failed: " + failed, "danger");
                btn.disabled = false;
                btn.innerHTML = btnText;
                setTimeout(function() { location.reload(); }, 800);
            }
        });
    });
}

function toggleBulkSelect() {
    const selectAll = document.getElementById("selectAll");
    const checkboxes = document.querySelectorAll(".invoice-checkbox");

    selectedInvoices = [];

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
        if (selectAll.checked) {
            const id = parseInt(checkbox.value);
            if (!selectedInvoices.includes(id)) {
                selectedInvoices.push(id);
            }
        }
    });

    updateBulkDeleteButton();
    updateSelectedCount();
}

function updateBulkDeleteButton() {
    const selectedCount = selectedInvoices.length;
    const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");

    if (selectedCount > 0) {
        bulkDeleteBtn.style.display = "inline-block";
        bulkDeleteBtn.title = "Delete " + selectedCount + " selected invoice" + (selectedCount > 1 ? "s" : "");
    } else {
        bulkDeleteBtn.style.display = "none";
    }
}

function updateSelectedCount() {
    const selectedCount = selectedInvoices.length;
    const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");

    if (selectedCount > 0) {
        bulkDeleteBtn.innerHTML = `<i class="bi bi-trash mr-1"></i> Delete Selected (${selectedCount})`;
    }
}

function toggleCheckbox(checkbox) {
    const id = parseInt(checkbox.value);
    const isChecked = checkbox.checked;

    if (isChecked) {
        if (!selectedInvoices.includes(id)) {
            selectedInvoices.push(id);
        }
    } else {
        const index = selectedInvoices.indexOf(id);
        if (index > -1) {
            selectedInvoices.splice(index, 1);
        }
    }
    updateBulkDeleteButton();
    updateSelectedCount();
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"\x27]/g, function(c) {
        if (c === "\x26") return "&amp;";
        if (c === "\x3c") return "&lt;";
        if (c === "\x3e") return "&gt;";
        if (c === "\x22") return "&quot;";
        return "&#39;";
    });
}

function bulkDeleteInvoices() {
    const selectedCount = selectedInvoices.length;
    if (selectedCount === 0) return;

    const tenantNames = selectedInvoices.map(id => {
        const inv = invoiceData[id];
        return inv ? escapeHtml(inv.tenant_name) : "Invoice " + id;
    });

    const countList = document.getElementById("deleteCountList");
    countList.innerHTML = tenantNames.map(name => `<li>${name}</li>`).join("");

    openModal("bulkDeleteModal");
}

function performBulkDelete() {
    if (selectedInvoices.length === 0) return;

    closeModal("bulkDeleteModal");

    ajax(BASE_URL + "ajax/invoice_bulk_delete.php", { ids: selectedInvoices }, function(result) {
        if (result.success) {
            showToast(result.message, "success");
            selectedInvoices.forEach(id => {
                const row = document.querySelector(`tr[data-invoice-id="${id}"]`);
                if (row) row.remove();
            });

            selectedInvoices = [];
            document.getElementById("selectAll").checked = false;
            document.getElementById("bulkDeleteBtn").style.display = "none";
        } else {
            showToast(result.message || "Error", "danger");
        }
    });
}

function setupBulkSelect() {
    const checkboxes = document.querySelectorAll(".invoice-checkbox");

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectedInvoices.includes(parseInt(checkbox.value));
        checkbox.addEventListener("change", function() {
            toggleCheckbox(this);
        });
    });
}

function initializeBulkSelect() {
    setupBulkSelect();

    if (selectedInvoices.length > 0) {
        updateBulkDeleteButton();
    }
}

document.addEventListener("DOMContentLoaded", function() {
    initializeBulkSelect();
});
</script>';
require dirname(__DIR__) . '/partials/footer.php';
?>