<?php
// Payments view - data: payments, activeLeases, paymentsJson, unpaidRows,
// unpaidTotal, unpaidJson, invoiceMapJson, paymentModel, year, month
$pageTitle = t('payments');
require dirname(__DIR__) . '/partials/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-cash-stack mr-2"></i><?php echo t('payments'); ?></h4>
    <div class="flex items-center gap-2">
        <button id="bulkDeleteBtn" class="btn btn-sm btn-danger" style="display:none;" data-delete-text="<?php echo t('delete_selected'); ?>" onclick="BulkSelect.confirmAndDelete()">
            <i class="bi bi-trash mr-1"></i><?php echo t('delete_selected'); ?> (0)
        </button>
        <button class="btn btn-primary" onclick="openAddPayment(); openModal('paymentModal')">
            <i class="bi bi-plus-lg mr-1"></i> <?php echo t('add_payment'); ?>
        </button>
    </div>
</div>

<!-- Filter -->
<div class="filter-bar mb-3 fade-in flex flex-wrap items-center gap-2">
    <span class="filter-label"><i class="bi bi-funnel mr-1"></i> <?php echo t('filter'); ?></span>
    <select class="form-select form-select-sm" style="max-width: 180px;" onchange="location='<?php echo url('payments'); ?>?month='+this.value+'&year=<?php echo $year; ?>'">
        <option value="0"><?php echo t('all'); ?></option>
        <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>><?php echo monthName($m); ?></option>
        <?php endfor; ?>
    </select>
    <select class="form-select form-select-sm" style="max-width: 130px;" onchange="location='<?php echo url('payments'); ?>?month=<?php echo $month; ?>&year='+this.value">
        <?php for ($y = date('Y') - 3; $y <= date('Y'); $y++): ?>
            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo bnNumeral($y); ?></option>
        <?php endfor; ?>
    </select>
    <div class="ml-auto flex items-center gap-3">
        <?php if ($unpaidTotal > 0): ?>
            <span class="text-muted"><?php echo t('due_rent'); ?>: <strong class="text-danger"><?php echo money($unpaidTotal); ?></strong></span>
        <?php endif; ?>
        <span class="text-muted"><?php echo t('total'); ?>: <strong><?php echo money($paymentModel->totalCollected($year, $month ?: null)); ?></strong></span>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" data-bulk-entity="payments">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>#</th>
                        <th><?php echo t('payment_date'); ?></th>
                        <th><?php echo t('tenant_name'); ?></th>
                        <th><?php echo t('building_name'); ?></th>
                        <th><?php echo t('flat_shop_no'); ?></th>
                        <th><?php echo t('unit_type'); ?></th>
                        <th><?php echo t('payment_month'); ?></th>
                        <th><?php echo t('payment_method'); ?></th>
                        <th><?php echo t('amount'); ?></th>
                        <th><?php echo t('status'); ?></th>
                        <th><?php echo t('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $filtered = array_filter($payments, function($p) use ($month, $year) {
                        if ($month && $p['month'] != $month) return false;
                        if ($year && $p['year'] != $year) return false;
                        return true;
                    });
                    // Due rows (unpaid invoices) first, then payment history.
                    $rows = array_merge($unpaidRows, $filtered);
                    ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="12" class="text-muted py-4"><?php echo t('no_data'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $i => $p): ?>
                            <?php $isDue = !empty($p['is_due']); ?>
                            <tr class="<?php echo $isDue ? 'due-row' : ''; ?>"<?php echo $isDue ? '' : ' data-id="' . (int)$p['id'] . '"'; ?>>
                                <td>
                                    <?php if ($isDue): ?>
                                        <span class="text-muted"><i class="bi bi-hourglass-split"></i></span>
                                    <?php else: ?>
                                        <input type="checkbox" class="bulk-checkbox form-check-input" value="<?php echo $p['id']; ?>">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td><?php echo $isDue ? '<span class="text-muted">&mdash;</span>' : e(bnNumeral(date('d-m-Y', strtotime($p['payment_date'])))); ?></td>
                                <td class="fw-semibold"><?php echo e(localizeName($p['tenant_name'])); ?></td>
                                <td><?php echo e(localizeText($p['building_name'])); ?></td>
                                <td><span class="badge bg-primary-subtle text-primary-emphasis"><?php echo e(bnFlatCode($p['flat_no'])); ?></span>
                                <?php if (!empty($p['service_charge_file']) && ($p['unit_type'] ?? 'flat') === 'flat'): ?>
                                    <button type="button" class="btn btn-sm btn-light border ml-1" title="<?php echo t('service_charge_receipt'); ?>" onclick="viewDocument('<?php echo e($p['service_charge_file']); ?>', '<?php echo e(t('service_charge_receipt')); ?>')">
                                        <i class="bi bi-paperclip"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                                <td>
                                    <?php if (($p['unit_type'] ?? 'flat') === 'shop'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-shop mr-1"></i><?php echo t('shop_unit'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-buildings mr-1"></i><?php echo t('flat_unit'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo monthName($p['month']) . ' ' . bnNumeral($p['year']); ?></td>
                                <td><?php echo $isDue ? '<span class="text-muted">&mdash;</span>' : '<span class="badge bg-light text-dark border">' . t($p['payment_method'], $p['payment_method']) . '</span>'; ?></td>
                                <td class="fw-semibold <?php echo $isDue ? 'text-danger' : 'text-success'; ?>"><?php echo money($p['total_amount']); ?></td>
                                <td class="whitespace-nowrap">
                                    <?php if ($isDue): ?>
                                        <span class="badge bg-danger-subtle text-danger-emphasis"><i class="bi bi-exclamation-circle mr-1"></i><?php echo t('status_unpaid'); ?></span>
                                    <?php else: ?>
                                        <?php $invStatus = $p['inv_status_effective'] ?? $p['payment_status'] ?? null; if ($invStatus && $invStatus !== 'paid'): ?>
                                            <span class="badge <?php echo $invStatus === 'partial' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-danger-subtle text-danger-emphasis'; ?>">
                                                <?php echo t('status_' . $invStatus, $invStatus); ?>
                                            </span>
                                        <?php elseif ($invStatus === 'paid'): ?>
                                            <span class="badge bg-success-subtle text-success-emphasis"><?php echo t('status_paid'); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($isDue) {
                                        echo actionButtons([
                                            ['label' => t('collect_payment'), 'icon' => 'bi bi-cash-coin', 'variant' => 'success',
                                             'onclick' => 'openCollectPayment(' . (int)$p['invoice_id'] . ')'],
                                            ['label' => t('view_invoice'), 'icon' => 'bi bi-eye', 'variant' => 'view',
                                             'href' => url('invoice-view') . '?id=' . (int)$p['invoice_id'] . '&lang=' . Lang::current()],
                                        ]);
                                    } else {
                                        $payItems = [
                                            ['label' => t('edit'), 'icon' => 'bi bi-pencil', 'variant' => 'edit', 'onclick' => 'editPayment(' . (int)$p['id'] . ')'],
                                        ];
                                        if ($invStatus && $invStatus !== 'paid') {
                                            $payItems[] = ['label' => t('mark_fully_paid'), 'icon' => 'bi bi-check2-circle', 'variant' => 'success', 'onclick' => 'markFullyPaid(' . (int)$p['id'] . ')'];
                                        }
                                        $payItems[] = ['label' => t('money_receipt'), 'icon' => 'bi bi-receipt', 'variant' => 'view', 'href' => url('receipt') . '?id=' . (int)$p['id']];
                                        $payItems[] = ['label' => t('delete'), 'icon' => 'bi bi-trash', 'variant' => 'delete',
                                                       'onclick' => 'confirmDelete(' . jsQuote(BASE_URL . 'ajax/payment_delete.php?id=' . (int)$p['id']) . ', ' . jsQuote(t('delete_confirm')) . ')'];
                                        echo actionButtons($payItems);
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal" id="paymentModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title" id="paymentModalTitle"><?php echo t('add_payment'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('paymentModal')" aria-label="Close"></button>
            </div>
            <form id="paymentForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="p_id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('select_tenant'); ?> / <?php echo t('lease'); ?> <span class="text-danger">*</span></label>
                        <select name="lease_id" id="p_lease" class="form-select" required onchange="p_lease_selected()">
                            <option value="">-- <?php echo t('select_flat'); ?> --</option>
                            <?php foreach ($activeLeases as $l): ?>
                                <option value="<?php echo $l['id']; ?>" data-rent="<?php echo $l['rent_amount']; ?>" data-tenant="<?php echo $l['tenant_id']; ?>" data-flat="<?php echo $l['flat_id']; ?>" data-unit="<?php echo $l['unit_type']; ?>">
                                    <?php echo e(localizeName($l['tenant_name'])); ?> · <?php echo e(localizeText($l['building_name'])); ?> <?php echo e(bnFlatCode($l['flat_no'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label"><?php echo t('payment_month'); ?> <span class="text-danger">*</span></label>
                            <select name="month" id="p_month" class="form-select" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>><?php echo monthName($m); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('payment_year'); ?> <span class="text-danger">*</span></label>
                            <input type="number" name="year" id="p_year" class="form-control" value="<?php echo date('Y'); ?>" min="2000" max="2100" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label"><?php echo t('paid_amount'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="amount" id="p_amount" class="form-control" inputmode="decimal" required>
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('receipt_type'); ?></label>
                            <select name="receipt_type" id="p_receipt_type" class="form-select">
                                <option value="rent" selected><?php echo t('flat_rent'); ?></option>
                                <option value="parking"><?php echo t('parking_bill'); ?></option>
                                <option value="gas"><?php echo t('gas_bill'); ?></option>
                                <option value="water"><?php echo t('water_bill'); ?></option>
                                <option value="waste"><?php echo t('waste_bill'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-receipt-cutoff mr-1"></i><?php echo t('other_bills'); ?></label>
                        <div class="grid grid-cols-12 items-center gap-2 mb-1" id="p_row_parking">
                            <div class="col-span-5">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="p_chk_parking" onchange="toggleCharge('parking')">
                                    <label class="form-check-label" for="p_chk_parking"><?php echo t('parking_bill'); ?></label>
                                </div>
                            </div>
                            <div class="col-span-7">
                                <input type="text" name="parking_amount" id="p_parking" class="form-control" inputmode="decimal" value="0" style="display:none;">
                            </div>
                        </div>
                        <div class="grid grid-cols-12 items-center gap-2 mb-1" id="p_row_gas">
                            <div class="col-span-5">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="p_chk_gas" onchange="toggleCharge('gas')">
                                    <label class="form-check-label" for="p_chk_gas"><?php echo t('gas_bill'); ?></label>
                                </div>
                            </div>
                            <div class="col-span-7">
                                <input type="text" name="gas_amount" id="p_gas" class="form-control" inputmode="decimal" value="0" style="display:none;">
                            </div>
                        </div>
                        <div class="grid grid-cols-12 items-center gap-2 mb-1" id="p_row_water">
                            <div class="col-span-5">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="p_chk_water" onchange="toggleCharge('water')">
                                    <label class="form-check-label" for="p_chk_water"><?php echo t('water_bill'); ?></label>
                                </div>
                            </div>
                            <div class="col-span-7">
                                <input type="text" name="water_fee" id="p_water" class="form-control" inputmode="decimal" value="0" style="display:none;">
                            </div>
                        </div>
                        <div class="grid grid-cols-12 items-center gap-2" id="p_row_waste">
                            <div class="col-span-5">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="p_chk_waste" onchange="toggleCharge('waste')">
                                    <label class="form-check-label" for="p_chk_waste"><?php echo t('waste_bill'); ?></label>
                                </div>
                            </div>
                            <div class="col-span-7">
                                <input type="text" name="waste_fee" id="p_waste" class="form-control" inputmode="decimal" value="0" style="display:none;">
                            </div>
                        </div>
                        <div class="grid grid-cols-12 items-center gap-2 mt-1" id="p_row_arrears">
                            <div class="col-span-5">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="p_chk_arrears" onchange="toggleCharge('arrears')">
                                    <label class="form-check-label" for="p_chk_arrears"><?php echo t('arrears'); ?></label>
                                </div>
                            </div>
                            <div class="col-span-7">
                                <input type="text" name="arrears" id="p_arrears" class="form-control" inputmode="decimal" value="0" style="display:none;">
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label"><?php echo t('payment_method'); ?></label>
                            <select name="payment_method" id="p_method" class="form-select">
                                <?php foreach (['cash', 'bank', 'bKash', 'nogod', 'rocket', 'other'] as $m): ?>
                                    <option value="<?php echo $m; ?>"><?php echo t($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('payment_date'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="payment_date" id="p_date" class="form-control" placeholder="DD-MM-YYYY" inputmode="numeric" data-date value="<?php echo date('d-m-Y'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('note'); ?></label>
                        <input type="text" name="note" id="p_note" class="form-control">
                    </div>
                    <div class="mb-3" id="p_sc_wrap" style="display:none;">
                        <label class="form-label"><i class="bi bi-paperclip mr-1"></i><?php echo t('service_charge_receipt'); ?>
                            <span class="text-muted small fw-normal">(<?php echo t('service_charge_hint'); ?>)</span></label>
                        <input type="file" name="service_charge_file" id="p_sc_file" class="form-control" accept=".pdf,image/*">
                        <div class="form-text mt-1" id="p_sc_link"></div>
                        <input type="hidden" name="remove_sc" id="p_sc_remove" value="">
                    </div>
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle mr-1"></i> <?php echo t('total'); ?>: <strong id="p_total"><?php echo money(0); ?></strong>
                        <span id="p_remaining_hint" class="d-block mt-1 text-muted"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('paymentModal')"><?php echo t('cancel'); ?></button>
                    <button type="submit" class="btn btn-success"><?php echo t('save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark Paid Modal (sets the actual paid date) -->
<div class="modal" id="markPaidModal">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-check2-circle mr-1"></i><?php echo t('mark_fully_paid'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('markPaidModal')" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mp_id" value="">
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

<!-- Document Viewer Modal -->
<div class="modal" id="docViewerModal">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-paperclip mr-1"></i><span id="docViewerTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('docViewerModal')" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="docViewerFrame" src="" style="width:100%;height:75vh;border:0;display:block;" title="Document"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal" id="confirmModal">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle mr-1"></i><?php echo t('delete_confirm'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('confirmModal')" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p id="confirmModalText" class="mb-3"><?php echo t('delete_confirm'); ?></p>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-secondary px-4" onclick="closeModal('confirmModal')"><?php echo t('cancel'); ?></button>
                <button type="button" class="btn btn-danger px-4" id="confirmDeleteBtn"><?php echo t('delete'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php
$extraJs = '
<script>
window.PAYMENTS = ' . $paymentsJson . ';
window.UNPAID_INVOICES = ' . $unpaidJson . ';
window.INVOICE_MAP = ' . $invoiceMapJson . ';

function viewDocument(url, title) {
    document.getElementById("docViewerFrame").src = BASE_URL + url;
    document.getElementById("docViewerTitle").textContent = title || "";
    openModal("docViewerModal");
}
document.addEventListener("modal:closed", function(e) {
    if (e.detail && e.detail.id === "docViewerModal") {
        document.getElementById("docViewerFrame").src = "";
    }
});

function openCollectPayment(invId) {
    const inv = (window.UNPAID_INVOICES || []).find(x => x.id == invId);
    if (!inv) return;
    resetPaymentForm();
    document.getElementById("paymentModalTitle").textContent = ' . json_encode(t('collect_payment')) . ';
    const sel = document.getElementById("p_lease");
    if (![...sel.options].some(o => o.value == inv.lease_id)) {
        const o = new Option(inv.tenant_name + " · " + inv.building_name + " " + inv.flat_no, inv.lease_id);
        o.dataset.unit = inv.unit_type;
        o.dataset.rent = inv.rent_amount;
        sel.appendChild(o);
    }
    sel.value = inv.lease_id;
    const bn = document.documentElement.lang === "bn";
    document.getElementById("p_month").value = inv.month;
    document.getElementById("p_year").value = inv.year;
    // Pre-fill the payment with the exact breakdown stored on the invoice so
    // the money receipt reproduces it (rent + parking + gas + water + waste +
    // arrears). Utility is billed separately and never appears on the receipt.
    setFieldValue("p_amount", inv.rent_amount, bn);
    applyChargeStates({
        parking: inv.parking_amount, gas: inv.gas_amount,
        water: inv.water_fee, waste: inv.waste_fee, arrears: inv.arrears
    }, bn);
    document.getElementById("p_receipt_type").disabled = inv.unit_type === "shop";
    updateScWrap();
    updateTotal();
    showInvoiceHint(sel, inv.month, inv.year);
    openModal("paymentModal");
}
function p_lease_selected() {
    const sel = document.getElementById("p_lease");
    const opt = sel.options[sel.selectedIndex];
    if (opt.value) {
        const bn = document.documentElement.lang === "bn";
        document.getElementById("p_amount").value = bn ? toBengaliDigits(opt.dataset.rent || "0") : (opt.dataset.rent || 0);
        const rType = document.getElementById("p_receipt_type");
        if (opt.dataset.unit === "shop") {
            rType.value = "rent";
            rType.disabled = true;
        } else {
            rType.disabled = false;
        }
        // When an invoice exists for the chosen flat + month, pre-fill its
        // exact breakdown (rent, service bills, arrears) so the receipt matches
        // the invoice — the invoice is the single source of truth.
        if (!document.getElementById("p_id").value) {
            const inv = window.INVOICE_MAP
                ? window.INVOICE_MAP[opt.value + "-" + document.getElementById("p_month").value + "-" + document.getElementById("p_year").value]
                : null;
            if (inv) {
                document.getElementById("p_amount").value = bn ? toBengaliDigits(inv.rent_amount) : inv.rent_amount;
                applyChargeStates({
                    parking: inv.parking_amount, gas: inv.gas_amount,
                    water: inv.water_fee, waste: inv.waste_fee, arrears: inv.arrears
                }, bn);
            }
        }
        updateTotal();
        updateRemainingHint();
        updateScWrap();
    }
}

function updateScWrap() {
    const sel = document.getElementById("p_lease");
    const opt = sel.options[sel.selectedIndex];
    const isFlatRent = opt && opt.value && opt.dataset.unit !== "shop"
        && document.getElementById("p_receipt_type").value === "rent";
    document.getElementById("p_sc_wrap").style.display = isFlatRent ? "" : "none";
}

function setPaymentScLink(path, id) {
    const el = document.getElementById("p_sc_link");
    el.innerHTML = "";
    document.getElementById("p_sc_remove").value = "";
    if (!path) return;
    const name = path.split("/").pop();
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-link btn-sm p-0";
    btn.title = "' . t('current_file') . ' " + name;
    btn.innerHTML = "<i class=\"bi bi-paperclip\"></i> <span class=\"text-muted\">" + name + "</span>";
    btn.addEventListener("click", function() { viewDocument(path, name); });
    el.appendChild(btn);
    const del = document.createElement("button");
    del.type = "button";
    del.className = "btn btn-link btn-sm p-0 text-danger ml-2";
    del.title = "' . t('remove') . '";
    del.innerHTML = "<i class=\"bi bi-x-circle\"></i>";
    del.addEventListener("click", function() {
        el.innerHTML = "";
        document.getElementById("p_sc_remove").value = "1";
        document.getElementById("p_sc_file").value = "";
    });
    el.appendChild(del);
}

function toggleCharge(key) {
    const chk = document.getElementById("p_chk_" + key);
    const inp = document.getElementById("p_" + key);
    const bn = document.documentElement.lang === "bn";
    if (chk.checked) {
        inp.style.display = "";
    } else {
        inp.style.display = "none";
        inp.value = bn ? "০" : "0";
    }
    updateTotal();
}

function updateTotal() {
    const amt = parseFloat(toAsciiDigits(document.getElementById("p_amount").value) || 0);
    const park = parseFloat(toAsciiDigits(document.getElementById("p_parking").value) || 0);
    const gas = parseFloat(toAsciiDigits(document.getElementById("p_gas").value) || 0);
    const wat = parseFloat(toAsciiDigits(document.getElementById("p_water").value) || 0);
    const was = parseFloat(toAsciiDigits(document.getElementById("p_waste").value) || 0);
    const arr = parseFloat(toAsciiDigits(document.getElementById("p_arrears").value) || 0);
    document.getElementById("p_total").textContent = formatMoney(amt + park + gas + wat + was + arr);
}

function showInvoiceHint(sel, month, year) {
    const opt = sel && sel.options[sel.selectedIndex];
    const box = document.getElementById("p_remaining_hint");
    if (!opt || !opt.value) { box.textContent = ""; return; }
    const inv = window.INVOICE_MAP
        ? window.INVOICE_MAP[opt.value + "-" + month + "-" + year]
        : null;
    if (!inv) { box.textContent = ""; return; }
    const rem = Math.max(0, inv.total_due - inv.paid);
    if (rem <= 0.009) {
        box.textContent = ' . json_encode(t('total_due') . ': ') . ' + formatMoney(inv.total_due) + " · " + ' . json_encode(t('already_fully_paid')) . ';
    } else {
        box.textContent = ' . json_encode(t('total_due') . ': ') . ' + formatMoney(inv.total_due)
            + " · " + ' . json_encode(t('remaining_due') . ': ') . ' + formatMoney(rem) + " (" + inv.status + ")";
    }
}

function updateRemainingHint() {
    const sel = document.getElementById("p_lease");
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) { document.getElementById("p_remaining_hint").textContent = ""; return; }
    showInvoiceHint(sel,
        document.getElementById("p_month").value,
        document.getElementById("p_year").value);
}

function markFullyPaid(paymentId) {
    const p = (window.PAYMENTS || []).find(x => x.id == paymentId);
    if (!p) return;
    document.getElementById("mp_id").value = p.id;
    document.getElementById("mp_date").value = getToday();
    openModal("markPaidModal");
}

function confirmMarkPaid() {
    const date = toAsciiDigits(document.getElementById("mp_date").value || "");
    if (!date) {
        showToast(' . json_encode(t('set_paid_date_required')) . ', "danger");
        return;
    }
    const data = new URLSearchParams();
    data.append("id", document.getElementById("mp_id").value);
    data.append("payment_date", date);
    data.append("payment_method", "cash");
    data.append("csrf_token", CSRF_TOKEN);
    fetch(BASE_URL + "ajax/payment_mark_paid.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: data.toString()
    })
    .then(res => res.json())
    .then(function(result) {
        closeModal("markPaidModal");
        showToast(result.message || "Error", result.success ? "success" : "danger");
        setTimeout(function() { location.reload(); }, 900);
    })
    .catch(function() {
        closeModal("markPaidModal");
        showToast("Request failed", "danger");
    });
}

function cancelMarkPaid() {
    closeModal("markPaidModal");
}

document.getElementById("p_lease").addEventListener("change", updateRemainingHint);
document.getElementById("p_month").addEventListener("change", updateRemainingHint);
document.getElementById("p_year").addEventListener("change", updateRemainingHint);

document.getElementById("p_amount").addEventListener("input", updateTotal);
document.getElementById("p_parking").addEventListener("input", updateTotal);
document.getElementById("p_gas").addEventListener("input", updateTotal);
document.getElementById("p_water").addEventListener("input", updateTotal);
document.getElementById("p_waste").addEventListener("input", updateTotal);
document.getElementById("p_arrears").addEventListener("input", updateTotal);
document.getElementById("p_receipt_type").addEventListener("change", updateScWrap);

function resetPaymentForm() {
    document.getElementById("paymentForm").reset();
    document.getElementById("p_id").value = "";
    document.getElementById("p_year").value = ' . date('Y') . ';
    document.getElementById("p_date").value = getToday();
    document.getElementById("p_month").value = new Date().getMonth() + 1;
    const rType = document.getElementById("p_receipt_type");
    rType.value = "rent";
    rType.disabled = false;
    ["parking", "gas", "water", "waste", "arrears"].forEach(k => {
        document.getElementById("p_chk_" + k).checked = false;
        document.getElementById("p_" + k).value = "0";
        document.getElementById("p_" + k).style.display = "none";
    });
    document.getElementById("p_sc_link").innerHTML = "";
    document.getElementById("p_sc_remove").value = "";
    document.getElementById("p_sc_wrap").style.display = "none";
    updateScWrap();
    updateTotal();
}

function applyChargeStates(values, bn) {
    ["parking", "gas", "water", "waste", "arrears"].forEach(k => {
        const chk = document.getElementById("p_chk_" + k);
        const inp = document.getElementById("p_" + k);
        const v = parseFloat(toAsciiDigits(values[k] || "0")) || 0;
        inp.value = bn ? toBengaliDigits(values[k] || "0") : (values[k] || "0");
        chk.checked = v > 0;
        inp.style.display = chk.checked ? "" : "none";
    });
}

function openAddPayment() {
    resetPaymentForm();
    document.getElementById("paymentModalTitle").textContent = ' . json_encode(t('add_payment')) . ';
}

function setFieldValue(id, val, bn) {
    document.getElementById(id).value = bn ? toBengaliDigits(val || "0") : (val || "0");
}

function editPayment(id) {
    const p = (window.PAYMENTS || []).find(x => x.id == id);
    if (!p) return;
    resetPaymentForm();
    document.getElementById("paymentModalTitle").textContent = ' . json_encode(t('edit_payment')) . ';
    document.getElementById("p_id").value = p.id;

    const sel = document.getElementById("p_lease");
    if (![...sel.options].some(o => o.value == p.lease_id)) {
        const o = new Option(p.tenant_name + " · " + p.building_name + " " + p.flat_no, p.lease_id);
        o.dataset.unit = p.unit_type;
        o.dataset.rent = p.amount;
        sel.appendChild(o);
    }
    sel.value = p.lease_id;

    const bn = document.documentElement.lang === "bn";
    document.getElementById("p_month").value = p.month;
    document.getElementById("p_year").value = p.year;
    setFieldValue("p_amount", p.amount, bn);
    applyChargeStates({ parking: p.parking_amount, gas: p.gas_amount, water: p.water_fee, waste: p.waste_fee, arrears: p.arrears || 0 }, bn);
    document.getElementById("p_method").value = p.payment_method;
    document.getElementById("p_date").value = p.payment_date;
    document.getElementById("p_receipt_type").value = p.receipt_type;
    document.getElementById("p_receipt_type").disabled = p.unit_type === "shop";
    document.getElementById("p_note").value = p.note || "";
    setPaymentScLink(p.service_charge_file || "", p.id);
    updateTotal();
    updateScWrap();

    openModal("paymentModal");
}

function getToday() {
    const d = new Date();
    return String(d.getDate()).padStart(2,"0") + "-" + String(d.getMonth()+1).padStart(2,"0") + "-" + d.getFullYear();
}

document.getElementById("paymentForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const id = document.getElementById("p_id").value;
    const url = BASE_URL + (id ? "ajax/payment_update.php" : "ajax/payment_save.php");
    submitModalForm("paymentForm", url);
});
</script>';
require dirname(__DIR__) . '/partials/footer.php';
?>