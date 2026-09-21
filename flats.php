<?php
// ============================================================
// Flats Page
// ============================================================

require_once __DIR__ . '/classes/init.php';
Auth::requireLogin();

$pageTitle = t('flats');

$buildingModel = new Building();
$flatModel = new Flat();

$buildings = $buildingModel->all();

// Filters
$filterBuilding = isset($_GET['building']) ? (int)$_GET['building'] : 0;
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
if ($filterType !== 'flat' && $filterType !== 'shop') {
    $filterType = '';
}

$flats = $flatModel->allWithBuilding($filterType);
if ($filterBuilding) {
    $flats = array_filter($flats, function($f) use ($filterBuilding) {
        return $f['building_id'] == $filterBuilding;
    });
}

// Filter URL helper
function flatFilterUrl($type, $building) {
    $q = [];
    if ($type) $q['type'] = $type;
    if ($building) $q['building'] = $building;
    $s = http_build_query($q);
    return $s ? 'flats.php?' . $s : 'flats.php';
}

require_once __DIR__ . '/inc/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <div>
        <h4 class="mb-0"><i class="bi bi-door-open mr-2"></i><?php echo t('flats'); ?></h4>
        <?php if ($filterBuilding): ?>
            <?php
            $bname = '';
            foreach ($buildings as $b) { if ($b['id'] == $filterBuilding) { $bname = $b['name']; break; } }
            ?>
            <small class="text-muted"><a href="flats.php" class="text-decoration-none">&larr; <?php echo t('back'); ?></a> · <?php echo e($bname); ?></small>
        <?php endif; ?>
    </div>
    <div class="flex items-center gap-2">
        <button id="bulkDeleteBtn" class="btn btn-sm btn-danger" style="display:none;" data-delete-text="<?php echo t('delete_selected'); ?>" onclick="BulkSelect.confirmAndDelete()">
            <i class="bi bi-trash mr-1"></i><?php echo t('delete_selected'); ?> (0)
        </button>
        <button class="btn btn-primary" onclick="resetFlatForm(); openModal('flatModal')">
            <i class="bi bi-plus-lg mr-1"></i> <?php echo t('add_flat'); ?>
        </button>
    </div>
</div>

<!-- Filters -->
<div class="filter-bar mb-3 fade-in flex flex-wrap items-center gap-2">
    <span class="filter-label"><i class="bi bi-funnel mr-1"></i> <?php echo t('filter'); ?></span>
    <ul class="filter-pills mr-2">
        <li>
            <a class="nav-link <?php echo $filterType === '' ? 'active' : ''; ?>" href="<?php echo flatFilterUrl('', $filterBuilding); ?>"><?php echo t('all_units'); ?></a>
        </li>
        <li>
            <a class="nav-link <?php echo $filterType === 'flat' ? 'active' : ''; ?>" href="<?php echo flatFilterUrl('flat', $filterBuilding); ?>"><i class="bi bi-door-open mr-1"></i><?php echo t('all_flats'); ?></a>
        </li>
        <li>
            <a class="nav-link <?php echo $filterType === 'shop' ? 'active' : ''; ?>" href="<?php echo flatFilterUrl('shop', $filterBuilding); ?>"><i class="bi bi-shop mr-1"></i><?php echo t('all_shops'); ?></a>
        </li>
    </ul>
    <select class="form-select form-select-sm" style="max-width: 240px;" onchange="if(this.value) location='flats.php?building='+this.value+'<?php echo e($filterType ? '&type=' . $filterType : ''); ?>'; else location='<?php echo e($filterType ? 'flats.php?type=' . $filterType : 'flats.php'); ?>';">
        <option value=""><?php echo t('all'); ?> <?php echo t('buildings'); ?></option>
        <?php foreach ($buildings as $b): ?>
            <option value="<?php echo $b['id']; ?>" <?php echo $filterBuilding == $b['id'] ? 'selected' : ''; ?>><?php echo e(localizeText($b['name'])); ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="card fade-in hidden md:block">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" data-bulk-entity="flats">
                <?php $isShopOnlyView = $filterType === 'shop'; ?>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>#</th>
                        <th><?php echo t('unit_type'); ?></th>
                        <th><?php echo t('unit_no'); ?></th>
                        <th><?php echo t('building_name'); ?></th>
                        <th><?php echo t('floor'); ?></th>
                        <?php if (!$isShopOnlyView): ?>
                        <th><?php echo t('bedrooms'); ?></th>
                        <th><?php echo t('bathrooms'); ?></th>
                        <?php endif; ?>
                        <th><?php echo t('rent_amount'); ?></th>
                        <th><?php echo t('status'); ?></th>
                        <th><?php echo t('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($flats)): ?>
                        <tr><td colspan="<?php echo $isShopOnlyView ? 9 : 11; ?>" class="text-muted py-4"><?php echo t('no_data'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($flats as $i => $f): ?>
                            <?php
                            $status = statusBadge($f['status']);
                            $tenant = $flatModel->getCurrentTenant($f['id']);
                            $isShop = $f['unit_type'] === 'shop';
                            ?>
                            <tr data-id="<?php echo $f['id']; ?>">
                                <td><input type="checkbox" class="bulk-checkbox form-check-input" value="<?php echo $f['id']; ?>"></td>
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td>
                                    <?php if ($isShop): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-shop mr-1"></i><?php echo t('shop'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-door-open mr-1"></i><?php echo t('flat_no'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold">
                                    <span class="badge bg-<?php echo $isShop ? 'warning' : 'primary'; ?>-subtle text-<?php echo $isShop ? 'warning' : 'primary'; ?>-emphasis"><?php echo e(bnFlatCode($f['flat_no'])); ?></span>
                                    <?php if ($f['size_sqft']): ?><small class="text-muted d-block"><?php echo e(bnNumeral(enDigits($f['size_sqft']))); ?> <?php echo t('sqft'); ?></small><?php endif; ?>
                                </td>
                                <td><?php echo e(localizeText($f['building_name'])); ?></td>
                                <td><?php echo e(floorLabel($f['floor'])); ?></td>
                                <?php if (!$isShopOnlyView): ?>
                                <td><?php echo $isShop ? '-' : bnNumeral((int)$f['bedrooms']); ?></td>
                                <td><?php echo $isShop ? '-' : bnNumeral((int)$f['bathrooms']); ?></td>
                                <?php endif; ?>
                                <td class="text-left fw-semibold"><?php echo money($f['rent_amount']); ?></td>
                                <td class="text-left">
                                    <span class="badge bg-<?php echo $status['class']; ?>-subtle text-<?php echo $status['class']; ?>-emphasis"><?php echo $status['label']; ?></span>
                                    <?php if ($tenant): ?>
                                        <small class="d-block text-muted"><?php echo e(localizeName($tenant['name'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo actionButtons([
                                        ['label' => t('edit'), 'icon' => 'bi bi-pencil', 'variant' => 'edit', 'onclick' => 'editFlat(' . (int)$f['id'] . ')'],
                                        ['label' => t('delete'), 'icon' => 'bi bi-trash', 'variant' => 'delete',
                                         'onclick' => 'confirmDelete(' . jsQuote(BASE_URL . 'ajax/flat_delete.php?id=' . (int)$f['id']) . ', ' . jsQuote(t('delete_confirm')) . ')'],
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

<!-- Mobile card list -->
<div class="md:hidden">
    <?php if (empty($flats)): ?>
        <div class="text-center text-muted py-4"><?php echo t('no_data'); ?></div>
    <?php else: ?>
        <?php foreach ($flats as $f): ?>
            <?php
            $status = statusBadge($f['status']);
            $tenant = $flatModel->getCurrentTenant($f['id']);
            $isShop = $f['unit_type'] === 'shop';
            ?>
            <div class="card fade-in mb-2 flat-card" data-id="<?php echo $f['id']; ?>">
                <div class="card-body">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" class="bulk-checkbox form-check-input" value="<?php echo $f['id']; ?>" aria-label="<?php echo t('select_all'); ?>">
                            <?php if ($isShop): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-shop mr-1"></i><?php echo t('shop'); ?></span>
                            <?php else: ?>
                                <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-door-open mr-1"></i><?php echo t('flat_no'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-1 action-wrap">
                            <button class="btn btn-sm btn-outline-primary" onclick="editFlat(<?php echo $f['id']; ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete('<?php echo BASE_URL; ?>ajax/flat_delete.php?id=<?php echo $f['id']; ?>', '<?php echo t('delete_confirm'); ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="fw-semibold">
                        <span class="badge bg-<?php echo $isShop ? 'warning' : 'primary'; ?>-subtle text-<?php echo $isShop ? 'warning' : 'primary'; ?>-emphasis"><?php echo e(bnFlatCode($f['flat_no'])); ?></span>
                        <?php if ($f['size_sqft']): ?><small class="text-muted ml-1"><?php echo e(bnNumeral(enDigits($f['size_sqft']))); ?> <?php echo t('sqft'); ?></small><?php endif; ?>
                    </div>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-building mr-1"></i><?php echo e(localizeText($f['building_name'])); ?>
                        <?php if ($f['floor'] !== ''): ?>· <?php echo e(floorLabel($f['floor'])); ?><?php endif; ?>
                    </div>
                    <?php if (!$isShop): ?>
                        <div class="text-muted small">
                            <i class="bi bi-house mr-1"></i><?php echo bnNumeral((int)$f['bedrooms']); ?> <?php echo t('bedrooms'); ?> · <?php echo bnNumeral((int)$f['bathrooms']); ?> <?php echo t('bathrooms'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex items-center justify-between mt-2 pt-2 border-top">
                        <span class="fw-semibold text-primary"><?php echo money($f['rent_amount']); ?></span>
                        <div class="text-right">
                            <span class="badge bg-<?php echo $status['class']; ?>-subtle text-<?php echo $status['class']; ?>-emphasis"><?php echo $status['label']; ?></span>
                            <?php if ($tenant): ?>
                                <small class="d-block text-muted"><?php echo e(localizeName($tenant['name'])); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Flat Modal -->
<div class="modal" id="flatModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title" id="flatModalTitle"><?php echo t('add_flat'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('flatModal')" aria-label="Close"></button>
            </div>
            <form id="flatForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="f_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('unit_type'); ?> <span class="text-danger">*</span></label>
                        <select name="unit_type" id="f_type" class="form-select" required onchange="toggleUnitFields()">
                            <option value="flat"><?php echo t('flat_unit'); ?></option>
                            <option value="shop"><?php echo t('shop_unit'); ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('select_building'); ?> <span class="text-danger">*</span></label>
                        <select name="building_id" id="f_building" class="form-select" required>
                            <option value="">-- <?php echo t('select_building'); ?> --</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?php echo $b['id']; ?>"><?php echo e(localizeText($b['name'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label" id="f_no_label"><?php echo t('flat_no'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="flat_no" id="f_no" class="form-control" data-bn-flat required>
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('floor'); ?></label>
                            <input type="text" name="floor" id="f_floor" class="form-control" data-digits-only>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3" id="f_room_row">
                        <div>
                            <label class="form-label"><?php echo t('bedrooms'); ?></label>
                            <input type="text" name="bedrooms" id="f_bed" class="form-control" inputmode="numeric" value="1">
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('bathrooms'); ?></label>
                            <input type="text" name="bathrooms" id="f_bath" class="form-control" inputmode="numeric" value="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('size_sqft'); ?></label>
                        <input type="text" name="size_sqft" id="f_size" class="form-control" inputmode="decimal">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label"><?php echo t('rent_amount'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="rent_amount" id="f_rent" class="form-control" inputmode="decimal" required>
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('advance'); ?></label>
                            <input type="text" name="advance_amount" id="f_advance" class="form-control" inputmode="decimal">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('status'); ?></label>
                        <select name="status" id="f_status" class="form-select">
                            <option value="available"><?php echo t('available'); ?></option>
                            <option value="occupied"><?php echo t('occupied'); ?></option>
                            <option value="maintenance"><?php echo t('maintenance'); ?></option>
                        </select>
                    </div>
                </div>
            </form>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-secondary px-4" onclick="closeModal('flatModal')"><?php echo t('cancel'); ?></button>
                <button type="submit" class="btn btn-primary px-4" id="f_submit" form="flatForm"><?php echo t('save'); ?></button>
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
const L10N = ' . json_encode([
    'shop_no'   => t('shop_no'),
    'flat_no'   => t('flat_no'),
    'add_flat'  => t('add_flat'),
    'edit_flat' => t('edit_flat'),
    'save'      => t('save'),
    'update'    => t('update'),
]) . ';
const REQUIRED = " <span class=\"text-danger\">*</span>";

function toggleUnitFields() {
    const type = document.getElementById("f_type").value;
    const isShop = type === "shop";
    document.getElementById("f_room_row").style.display = isShop ? "none" : "";
    document.getElementById("f_no_label").innerHTML = (isShop ? L10N.shop_no : L10N.flat_no) + REQUIRED;
}

function resetFlatForm() {
    document.getElementById("flatForm").reset();
    document.getElementById("f_id").value = "";
    document.getElementById("f_type").value = "flat";
    document.getElementById("f_bed").value = "1";
    document.getElementById("f_bath").value = "1";
    document.getElementById("f_size").value = "";
    document.getElementById("f_room_row").style.display = "";
    document.getElementById("f_no_label").innerHTML = L10N.flat_no + REQUIRED;
    document.getElementById("flatModalTitle").textContent = L10N.add_flat;
    document.getElementById("f_submit").textContent = L10N.save;
}

function editFlat(id) {
    ajax(BASE_URL + "ajax/flat_get.php", { id: id }, function(result) {
        if (result.success) {
            const d = result.data;
            document.getElementById("f_id").value = d.id;
            document.getElementById("f_type").value = d.unit_type || "flat";
            document.getElementById("f_building").value = d.building_id;
            document.getElementById("f_no").value = d.flat_no;
            document.getElementById("f_floor").value = d.floor || "";
            document.getElementById("f_bed").value = d.bedrooms;
            document.getElementById("f_bath").value = d.bathrooms;
            document.getElementById("f_size").value = d.size_sqft || "";
            document.getElementById("f_rent").value = d.rent_amount;
            document.getElementById("f_advance").value = d.advance_amount;
            document.getElementById("f_status").value = d.status;
            toggleUnitFields();
            document.getElementById("flatModalTitle").textContent = L10N.edit_flat;
            document.getElementById("f_submit").textContent = L10N.update;
            openModal("flatModal");
        }
    });
}

document.getElementById("flatForm").addEventListener("submit", function(e) {
    e.preventDefault();
    submitModalForm("flatForm", BASE_URL + "ajax/flat_save.php");
});
</script>';
require __DIR__ . '/inc/footer.php';
?>