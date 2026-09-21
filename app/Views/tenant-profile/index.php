<?php
// Tenant Profile view - data: tenantId, tenant, lease
$pageTitle = t('my_info');
require dirname(__DIR__) . '/partials/tenant_header.php';
?>

<div class="grid md:grid-cols-2 gap-3 mb-4 fade-in">
    <!-- Tenant information -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="bi bi-person mr-1"></i><?php echo t('personal_info'); ?></h5>
            <div class="flex items-center gap-3 mb-3">
                <?php if (!empty($tenant['photo'])): ?>
                    <img src="<?php echo e($tenant['photo']); ?>" class="rounded-circle" width="56" height="56" style="object-fit:cover;" alt="">
                <?php else: ?>
                    <i class="bi bi-person-circle text-primary" style="font-size:3rem;"></i>
                <?php endif; ?>
                <div>
                    <div class="fw-semibold fs-5"><?php echo e(localizeName($tenant['name'])); ?></div>
                </div>
            </div>
            <table class="table table-sm mb-0">
                <tr><th style="width:35%;"><?php echo t('phone'); ?></th><td><?php echo e(bnNumeral(enDigits($tenant['phone']))); ?></td></tr>
                <tr><th><?php echo t('email'); ?></th><td><?php echo e($tenant['email'] ?: '-'); ?></td></tr>
                <tr><th><?php echo t('nid'); ?></th><td><?php echo $tenant['nid'] ? e(bnNumeral(enDigits($tenant['nid']))) : '-'; ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Flat information -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="bi bi-door-open mr-1"></i><?php echo t('flat_details'); ?></h5>
            <?php if ($lease): ?>
                <table class="table table-sm mb-0">
                    <tr><th style="width:40%;"><?php echo t('building_name'); ?></th><td><?php echo e(localizeText($lease['building_name'] ?? '') ?: '-'); ?></td></tr>
                    <tr><th><?php echo t('flat_no'); ?></th><td><span class="badge bg-primary-subtle text-primary-emphasis"><?php echo e(bnFlatCode($lease['flat_no'] ?? '-')); ?></span></td></tr>
                    <tr><th><?php echo t('unit_type'); ?></th><td><?php echo $lease['unit_type'] === 'shop' ? e(t('shop')) : e(t('flat_no')); ?></td></tr>
                    <tr><th><?php echo t('floor'); ?></th><td><?php echo e(floorLabel($lease['floor'] ?? '')); ?></td></tr>
                </table>
            <?php else: ?>
                <p class="text-muted mb-0"><?php echo t('no_lease'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-3 fade-in">
    <!-- Lease information -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="bi bi-file-earmark-text mr-1"></i><?php echo t('lease_details'); ?></h5>
            <?php if ($lease): ?>
                <table class="table table-sm mb-0">
                    <tr><th style="width:40%;"><?php echo t('rent_amount'); ?></th><td class="fw-semibold"><?php echo money($lease['rent_amount'] ?? 0); ?></td></tr>
                    <tr><th><?php echo t('utility_fee'); ?></th><td><?php echo money($lease['utility_fee'] ?? 0); ?></td></tr>
                    <tr><th><?php echo t('start_date'); ?></th><td><?php echo e(bnNumeral(date('d-m-Y', strtotime($lease['start_date'])))); ?></td></tr>
                    <tr><th><?php echo t('end_date'); ?></th><td><?php echo !empty($lease['end_date']) ? e(bnNumeral(date('d-m-Y', strtotime($lease['end_date'])))) : '-'; ?></td></tr>
                    <tr><th><?php echo t('lease_status'); ?></th><td><?php $ls = statusBadge($lease['status'] ?? 'active'); ?><span class="badge bg-<?php echo $ls['class']; ?>-subtle text-<?php echo $ls['class']; ?>-emphasis"><?php echo $ls['label']; ?></span></td></tr>
                </table>
            <?php else: ?>
                <p class="text-muted mb-0"><?php echo t('no_lease'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Documents -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="bi bi-file-earmark-text mr-1"></i><?php echo t('documents'); ?></h5>
            <div class="flex flex-wrap gap-2">
                <?php if (!empty($tenant['deed_file'])): ?>
                    <button type="button" class="btn btn-outline-primary" onclick="viewDocument('<?php echo e($tenant['deed_file']); ?>', '<?php echo t('deed'); ?>')" title="<?php echo t('deed'); ?>">
                        <i class="bi bi-file-earmark-text"></i> <?php echo t('deed'); ?>
                    </button>
                <?php else: ?>
                    <span class="btn btn-outline-secondary disabled" title="<?php echo t('deed'); ?>">
                        <i class="bi bi-file-earmark-text text-muted"></i> <?php echo t('deed'); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($tenant['nid_file'])): ?>
                    <button type="button" class="btn btn-outline-success" onclick="viewDocument('<?php echo e($tenant['nid_file']); ?>', '<?php echo t('nid'); ?>')" title="<?php echo t('nid'); ?>">
                        <i class="bi bi-person-vcard"></i> <?php echo t('nid'); ?>
                    </button>
                <?php else: ?>
                    <span class="btn btn-outline-secondary disabled" title="<?php echo t('nid'); ?>">
                        <i class="bi bi-person-vcard text-muted"></i> <?php echo t('nid'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Document Viewer Modal -->
    <div class="modal" id="docViewerModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-text mr-1"></i><span id="docViewerTitle"></span></h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeModal('docViewerModal')" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="docViewerFrame" src="" style="width:100%;height:75vh;border:0;display:block;" title="Document"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Change portal password -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="bi bi-shield-lock mr-1"></i><?php echo t('change_password'); ?></h5>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label class="form-label"><?php echo t('current_password'); ?></label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo t('new_password'); ?></label>
                    <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo t('confirm_password'); ?></label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 mr-1"></i><?php echo t('save'); ?></button>
            </form>
        </div>
    </div>
</div>

<script>
function viewDocument(url, title) {
    document.getElementById("docViewerFrame").src = BASE_URL + url;
    document.getElementById("docViewerTitle").textContent = title || "<?php echo t('documents'); ?>";
    openModal("docViewerModal");
}
document.addEventListener("modal:closed", function(e) {
    if (e.detail && e.detail.id === "docViewerModal") {
        document.getElementById("docViewerFrame").src = "";
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>