<?php
// Buildings view - data: $buildings (array), $editing
$pageTitle = t('buildings');
require dirname(__DIR__) . '/partials/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-buildings mr-2"></i><?php echo t('buildings'); ?></h4>
    <div class="flex items-center gap-2">
        <button id="bulkDeleteBtn" class="btn btn-sm btn-danger" style="display:none;" data-delete-text="<?php echo t('delete_selected'); ?>" onclick="BulkSelect.confirmAndDelete()">
            <i class="bi bi-trash mr-1"></i><?php echo t('delete_selected'); ?> (0)
        </button>
        <button class="btn btn-primary" onclick="resetBuildingForm(); openModal('buildingModal')">
            <i class="bi bi-plus-lg mr-1"></i> <?php echo t('add_building'); ?>
        </button>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" data-bulk-entity="buildings">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>#</th>
                        <th><?php echo t('building_name'); ?></th>
                        <th><?php echo t('address'); ?></th>
                        <th><?php echo t('total_flats'); ?></th>
                        <th><?php echo t('available_flats'); ?></th>
                        <th><?php echo t('total_shops'); ?></th>
                        <th><?php echo t('available_shops'); ?></th>
                        <th><?php echo t('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($buildings)): ?>
                        <tr><td colspan="9" class="text-muted py-4"><?php echo t('no_data'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($buildings as $i => $b): ?>
                            <tr data-id="<?php echo $b['id']; ?>">
                                <td><input type="checkbox" class="bulk-checkbox form-check-input" value="<?php echo $b['id']; ?>"></td>
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td class="fw-semibold">
                                    <a href="<?php echo url('flats'); ?>?building=<?php echo $b['id']; ?>" class="text-decoration-none">
                                        <i class="bi bi-building mr-1 text-primary"></i><?php echo e(localizeText($b['name'])); ?>
                                    </a>
                                </td>
                                <td><?php echo e(localizeText($b['address'])); ?></td>
                                <td>
                                    <span class="text-primary"><?php echo bnNumeral($b['flat_count']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $b['available_flat_count'] > 0 ? 'success' : 'secondary'; ?>-subtle text-<?php echo $b['available_flat_count'] > 0 ? 'success' : 'secondary'; ?>-emphasis">
                                        <?php echo bnNumeral($b['available_flat_count']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-warning"><i class="bi bi-shop"></i> <?php echo bnNumeral($b['shop_count']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $b['available_shop_count'] > 0 ? 'success' : 'secondary'; ?>-subtle text-<?php echo $b['available_shop_count'] > 0 ? 'success' : 'secondary'; ?>-emphasis">
                                        <?php echo bnNumeral($b['available_shop_count']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo actionButtons([
                                        ['label' => t('edit'), 'icon' => 'bi bi-pencil', 'variant' => 'edit', 'onclick' => 'editBuilding(' . (int)$b['id'] . ')'],
                                        ['label' => t('delete'), 'icon' => 'bi bi-trash', 'variant' => 'delete',
                                         'onclick' => 'confirmDelete(' . jsQuote(BASE_URL . 'ajax/building_delete.php?id=' . (int)$b['id']) . ', ' . jsQuote(t('delete_confirm')) . ')'],
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

<!-- Building Modal -->
<div class="modal" id="buildingModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title" id="buildingModalTitle"><?php echo t('add_building'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('buildingModal')" aria-label="Close"></button>
            </div>
            <form id="buildingForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="b_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('building_name'); ?> <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="b_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('address'); ?></label>
                        <input type="text" name="address" id="b_address" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('description'); ?></label>
                        <textarea name="description" id="b_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('buildingModal')"><?php echo t('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary" id="b_submit"><?php echo t('save'); ?></button>
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
function resetBuildingForm() {
    document.getElementById("buildingForm").reset();
    document.getElementById("b_id").value = "";
    document.getElementById("buildingModalTitle").textContent = "' . t('add_building') . '";
    document.getElementById("b_submit").textContent = "' . t('save') . '";
}

function editBuilding(id) {
    ajax(BASE_URL + "ajax/building_get.php", { id: id }, function(result) {
        if (result.success) {
            document.getElementById("b_id").value = result.data.id;
            document.getElementById("b_name").value = result.data.name;
            document.getElementById("b_address").value = result.data.address || "";
            document.getElementById("b_description").value = result.data.description || "";
            document.getElementById("buildingModalTitle").textContent = "' . t('edit_building') . '";
            document.getElementById("b_submit").textContent = "' . t('update') . '";
            openModal("buildingModal");
        }
    });
}

document.getElementById("buildingForm").addEventListener("submit", function(e) {
    e.preventDefault();
    submitModalForm("buildingForm", BASE_URL + "ajax/building_save.php");
});
</script>';
require dirname(__DIR__) . '/partials/footer.php';
?>