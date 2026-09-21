<?php
// ============================================================
// Leases Page
// ============================================================

require_once __DIR__ . '/classes/init.php';
Auth::requireLogin();

$pageTitle = t('leases');

$leaseModel = new Lease();
$tenantModel = new Tenant();
$flatModel = new Flat();

$leases = $leaseModel->allWithDetails();
$tenants = $tenantModel->where('status', 'active'); // all active tenants
$allFlats = $flatModel->allWithBuilding();

require_once __DIR__ . '/inc/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text mr-2"></i><?php echo t('leases'); ?></h4>
    <div class="flex items-center gap-2">
        <button id="bulkDeleteBtn" class="btn btn-sm btn-danger" style="display:none;" data-delete-text="<?php echo t('delete_selected'); ?>" onclick="BulkSelect.confirmAndDelete()">
            <i class="bi bi-trash mr-1"></i><?php echo t('delete_selected'); ?> (0)
        </button>
        <button class="btn btn-primary" onclick="resetLeaseForm(); openModal('leaseModal')">
            <i class="bi bi-plus-lg mr-1"></i> <?php echo t('add_lease'); ?>
        </button>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" data-bulk-entity="leases">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>#</th>
                        <th><?php echo t('tenant_name'); ?></th>
                        <th><?php echo t('building_name'); ?> / <?php echo t('flat_no'); ?></th>
                        <th><?php echo t('start_date'); ?></th>
                        <th><?php echo t('end_date'); ?></th>
                        <th><?php echo t('rent_amount'); ?></th>
                        <th><?php echo t('utility_fee'); ?></th>
                        <th><?php echo t('advance'); ?></th>
                        <th><?php echo t('status'); ?></th>
                        <th><?php echo t('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leases)): ?>
                        <tr><td colspan="11" class="text-muted py-4"><?php echo t('no_data'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($leases as $i => $l): ?>
                            <?php $st = statusBadge($l['status']); ?>
                            <tr data-id="<?php echo $l['id']; ?>">
                                <td><input type="checkbox" class="bulk-checkbox form-check-input" value="<?php echo $l['id']; ?>"></td>
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td class="fw-semibold"><?php echo e(localizeName($l['tenant_name'])); ?>
                                    <small class="text-muted d-block"><?php echo e($l['tenant_phone'] ? bnNumeral(enDigits($l['tenant_phone'])) : ''); ?></small>
                                </td>
                                <td>
                                    <?php $badgeIcon = isset($l['unit_type']) && $l['unit_type'] === 'shop' ? '<i class="bi bi-shop mr-1"></i>' . t('shop') . ' · ' : ''; ?>
                                    <span class="badge bg-info-subtle text-info-emphasis"><?php echo $badgeIcon; ?> <?php echo e(localizeText($l['building_name'])); ?> · <?php echo e(bnFlatCode($l['flat_no'])); ?></span>
                                </td>
                                <td><?php echo e(bnNumeral(date('d-m-Y', strtotime($l['start_date'])))); ?></td>
                                <td><?php echo $l['end_date'] ? e(bnNumeral(date('d-m-Y', strtotime($l['end_date'])))) : '-'; ?></td>
                                <td><?php echo money($l['rent_amount']); ?></td>
                                <td><?php echo money($l['utility_fee']); ?></td>
                                <td><?php echo !empty($l['unit_advance']) ? money($l['unit_advance']) : '-'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $st['class']; ?>-subtle text-<?php echo $st['class']; ?>-emphasis"><?php echo $st['label']; ?></span>
                                </td>
                                <td>
                                    <?php echo actionButtons([
                                        ['label' => t('edit'), 'icon' => 'bi bi-pencil', 'variant' => 'edit', 'onclick' => 'editLease(' . (int)$l['id'] . ')'],
                                        ['label' => t('delete'), 'icon' => 'bi bi-trash', 'variant' => 'delete',
                                         'onclick' => 'confirmDelete(' . jsQuote(BASE_URL . 'ajax/lease_delete.php?id=' . (int)$l['id']) . ', ' . jsQuote(t('delete_confirm')) . ')'],
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

<!-- Lease Modal -->
<div class="modal" id="leaseModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title" id="leaseModalTitle"><?php echo t('add_lease'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('leaseModal')" aria-label="Close"></button>
            </div>
            <form id="leaseForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="l_id">
                <input type="hidden" name="flat_id" id="l_flat_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('select_tenant'); ?> <span class="text-danger">*</span></label>
                        <select name="tenant_id" id="l_tenant" class="form-select" required>
                            <option value="">-- <?php echo t('select_tenant'); ?> --</option>
                            <?php foreach ($tenants as $tn): ?>
                                <option value="<?php echo $tn['id']; ?>"><?php echo e(localizeName($tn['name'])); ?> (<?php echo e(bnNumeral(enDigits($tn['phone']))); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('select_flat'); ?> <span class="text-danger">*</span></label>
                        <select name="select_flat" id="l_flat" class="form-select" required onchange="l_flat_selected()">
                            <option value="">-- <?php echo t('select_flat'); ?> --</option>
                            <?php foreach ($allFlats as $f): ?>
                                <?php $unitTag = $f['unit_type'] === 'shop' ? '[' . t('shop') . ']' : ''; ?>
                                <?php $statusTag = $f['status'] !== 'available' ? ' (' . t($f['status']) . ')' : ''; ?>
                                <option value="<?php echo $f['id']; ?>" data-rent="<?php echo $f['rent_amount']; ?>" data-advance="<?php echo $f['advance_amount']; ?>"><?php echo $unitTag; ?> <?php echo e(localizeText($f['building_name'])); ?> · <?php echo e(bnFlatCode($f['flat_no'])); ?><?php echo e($statusTag); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label"><?php echo t('start_date'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="start_date" id="l_start" class="form-control" placeholder="DD-MM-YYYY" inputmode="numeric" data-date required>
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('end_date'); ?></label>
                            <input type="text" name="end_date" id="l_end" class="form-control" placeholder="DD-MM-YYYY" inputmode="numeric" data-date>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label"><?php echo t('rent_amount'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="rent_amount" id="l_rent" class="form-control" inputmode="decimal" required>
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('utility_fee'); ?></label>
                            <input type="text" name="utility_fee" id="l_utility" class="form-control" inputmode="decimal" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('advance'); ?></label>
                        <input type="text" name="advance_amount" id="l_advance" class="form-control" inputmode="decimal">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('leaseModal')"><?php echo t('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary" id="l_submit"><?php echo t('save'); ?></button>
                </div>
            </form>
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
function l_flat_selected() {
    const sel = document.getElementById("l_flat");
    const opt = sel.options[sel.selectedIndex];
    document.getElementById("l_flat_id").value = opt.value;
    const bn = document.documentElement.lang === "bn";
    if (opt.dataset.rent) {
        document.getElementById("l_rent").value = bn ? toBengaliDigits(opt.dataset.rent) : opt.dataset.rent;
    }
    // Advance comes from the unit (flats.php). Prefill whenever the select changes.
    if (opt.dataset.advance !== undefined) {
        const adv = opt.dataset.advance || "";
        document.getElementById("l_advance").value = bn && adv ? toBengaliDigits(adv) : adv;
    }
}

function resetLeaseForm() {
    document.getElementById("leaseForm").reset();
    document.getElementById("l_id").value = "";
    document.getElementById("l_flat_id").value = "";
    document.getElementById("l_utility").value = "0";
    document.getElementById("leaseModalTitle").textContent = "' . t('add_lease') . '";
    document.getElementById("l_submit").textContent = "' . t('save') . '";
}

function editLease(id) {
    ajax(BASE_URL + "ajax/lease_get.php", { id: id }, function(result) {
        if (result.success) {
            const d = result.data;
            document.getElementById("l_id").value = d.id;
            document.getElementById("l_tenant").value = d.tenant_id;
            // Flat: add option if not present
            const flatSel = document.getElementById("l_flat");
            let found = false;
            for (let i = 0; i < flatSel.options.length; i++) {
                if (flatSel.options[i].value == d.flat_id) { flatSel.selectedIndex = i; found = true; break; }
            }
            if (!found) {
                const opt = new Option(d.building_name + " · " + d.flat_no, d.flat_id);
                opt.dataset.rent = d.rent_amount;
                opt.dataset.advance = d.unit_advance || d.advance_amount || "";
                flatSel.appendChild(opt);
                flatSel.selectedIndex = flatSel.options.length - 1;
            }
            document.getElementById("l_flat_id").value = d.flat_id;
            document.getElementById("l_start").value = d.start_date;
            document.getElementById("l_end").value = d.end_date || "";
            document.getElementById("l_rent").value = d.rent_amount;
            document.getElementById("l_utility").value = d.utility_fee;
            // Advance shown on Edit Lease comes from the unit (flats.php):
            // lease_get returns the unit advance amount as unit_advance.
            const bnUpd = document.documentElement.lang === "bn";
            const unitAdv = d.unit_advance || "";
            document.getElementById("l_advance").value = bnUpd && unitAdv ? toBengaliDigits(unitAdv) : unitAdv;
            document.getElementById("leaseModalTitle").textContent = "' . t('edit_lease') . '";
            document.getElementById("l_submit").textContent = "' . t('update') . '";
            openModal("leaseModal");
        }
    });
}

document.getElementById("leaseForm").addEventListener("submit", function(e) {
    e.preventDefault();
    submitModalForm("leaseForm", BASE_URL + "ajax/lease_save.php");
});
</script>';
require __DIR__ . '/inc/footer.php';
?>