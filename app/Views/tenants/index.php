<?php
// Tenants view - data: $tenants (array)
$pageTitle = t('tenants');
require dirname(__DIR__) . '/partials/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-people mr-2"></i><?php echo t('tenants'); ?></h4>
    <div class="flex items-center gap-2">
        <button id="bulkDeleteBtn" class="btn btn-sm btn-danger" style="display:none;" data-delete-text="<?php echo t('delete_selected'); ?>" onclick="BulkSelect.confirmAndDelete()">
            <i class="bi bi-trash mr-1"></i><?php echo t('delete_selected'); ?> (0)
        </button>
        <button class="btn btn-primary" onclick="resetTenantForm(); openModal('tenantModal')">
            <i class="bi bi-plus-lg mr-1"></i> <?php echo t('add_tenant'); ?>
        </button>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" data-bulk-entity="tenants">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>#</th>
                        <th><?php echo t('tenant_name'); ?></th>
                        <th><?php echo t('phone'); ?></th>
                        <th><?php echo t('nid'); ?></th>
                        <th><?php echo t('documents'); ?></th>
                        <th><?php echo t('status'); ?></th>
                        <?php if ($isAdmin): ?><th><?php echo t('entered_by'); ?></th><?php endif; ?>
                        <th><?php echo t('flat_shop_no'); ?></th>
                        <th><?php echo t('rent_amount'); ?></th>
                        <th><?php echo t('advance'); ?></th>
                        <th><?php echo t('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tenants)): ?>
                        <tr><td colspan="<?php echo $isAdmin ? 12 : 11; ?>" class="text-muted py-4"><?php echo t('no_data'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($tenants as $i => $tn): ?>
                            <?php $st = statusBadge($tn['status']); ?>
                            <tr data-id="<?php echo $tn['id']; ?>">
                                <td><input type="checkbox" class="bulk-checkbox form-check-input" value="<?php echo $tn['id']; ?>"></td>
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td class="fw-semibold">
                                    <?php if ($tn['photo']): ?>
                                        <img src="<?php echo e($tn['photo']); ?>" class="rounded-circle mr-1" width="28" height="28" style="object-fit:cover;">
                                    <?php else: ?>
                                        <i class="bi bi-person rounded-circle mr-1 text-primary"></i>
                                    <?php endif; ?>
                                    <?php echo e(localizeName($tn['name'])); ?>
                                    <?php if (!empty($tn['portal_password']) || !empty($tn['portal_username'])): ?>
                                        <span class="badge bg-light text-dark border ml-1" title="<?php echo t('tenant_portal'); ?>: <?php echo e($tn['portal_username'] ?: '-'); ?>"><i class="bi bi-person-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e(bnNumeral(enDigits($tn['phone']))); ?></td>
                                <td><?php echo $tn['nid'] ? e(bnNumeral(enDigits($tn['nid']))) : '-'; ?></td>
                                <td>
                                    <span id="doc-<?php echo $tn['id']; ?>-deed">
                                        <?php if ($tn['deed_file']): ?>
                                            <button type="button" class="btn btn-sm btn-light border mr-1" title="<?php echo t('deed'); ?>" onclick="viewDocument('<?php echo e($tn['deed_file']); ?>')">
                                                <i class="bi bi-file-earmark-text text-primary"></i>
                                            </button>
                                        <?php else: ?>
                                            <i class="bi bi-file-earmark-text text-muted opacity-25 mr-1"></i>
                                        <?php endif; ?>
                                    </span>
                                    <span id="doc-<?php echo $tn['id']; ?>-nid">
                                        <?php if ($tn['nid_file']): ?>
                                            <button type="button" class="btn btn-sm btn-light border" title="<?php echo t('nid'); ?>" onclick="viewDocument('<?php echo e($tn['nid_file']); ?>')">
                                                <i class="bi bi-person-vcard text-success"></i>
                                            </button>
                                        <?php else: ?>
                                            <i class="bi bi-person-vcard text-muted opacity-25"></i>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $st['class']; ?>-subtle text-<?php echo $st['class']; ?>-emphasis"><?php echo $st['label']; ?></span>
                                </td>
                                <?php if ($isAdmin): ?>
                                <td>
                                    <?php if (!empty($tn['created_by_name'])): ?>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis"><?php echo e($tn['created_by_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($tn['flat_id']): ?>
                                        <span class="badge bg-info-subtle text-info-emphasis"><?php echo e(localizeText($tn['building_name'])); ?> · <?php echo e(bnFlatCode($tn['flat_no'])); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $tn['rent_amount'] ? money($tn['rent_amount']) : '-'; ?></td>
                                <td><?php echo isset($tn['advance_amount']) && $tn['advance_amount'] ? money($tn['advance_amount']) : '-'; ?></td>
                                <td>
                                    <?php echo actionButtons([
                                        ['label' => t('edit'), 'icon' => 'bi bi-pencil', 'variant' => 'edit', 'onclick' => 'editTenant(' . (int)$tn['id'] . ')'],
                                        ['label' => t('delete'), 'icon' => 'bi bi-trash', 'variant' => 'delete',
                                         'onclick' => 'confirmDelete(' . jsQuote(BASE_URL . 'ajax/tenant_delete.php?id=' . (int)$tn['id']) . ', ' . jsQuote(t('delete_confirm')) . ')'],
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

<!-- Tenant Modal -->
<div class="modal" id="tenantModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title" id="tenantModalTitle"><?php echo t('add_tenant'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('tenantModal')" aria-label="Close"></button>
            </div>
            <form id="tenantForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="tn_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('tenant_name'); ?> <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="tn_name" class="form-control" required>
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('entered_by'); ?></label>
                        <select name="created_by" id="tn_created_by" class="form-select">
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>"<?php echo (int)$u['id'] === (int)\Auth::id() ? ' selected' : ''; ?>>
                                    <?php echo e($u['full_name'] ?: $u['username']); ?> (<?php echo e(t($u['role'], $u['role'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text mt-1"><i class="bi bi-info-circle mr-1"></i><?php echo t('entered_by_hint'); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label"><?php echo t('phone'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="tn_phone" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('email'); ?></label>
                            <input type="email" name="email" id="tn_email" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('nid'); ?></label>
                        <input type="text" name="nid" id="tn_nid" class="form-control">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label"><?php echo t('deed'); ?></label>
                            <input type="file" name="deed_file" id="tn_deed_file" class="form-control" accept=".pdf,image/*">
                            <div class="form-text mt-1" id="tn_deed_link"></div>
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('nid'); ?></label>
                            <input type="file" name="nid_file" id="tn_nid_file" class="form-control" accept=".pdf,image/*">
                            <div class="form-text mt-1" id="tn_nid_link"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('address'); ?></label>
                        <textarea name="address" id="tn_address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('advance'); ?></label>
                        <input type="text" name="advance_amount" id="tn_advance" class="form-control" inputmode="decimal" data-bn-num>
                        <div class="form-text mt-1"><i class="bi bi-info-circle mr-1"></i><?php echo t('advance_unit_hint'); ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('status'); ?></label>
                        <select name="status" id="tn_status" class="form-select">
                            <option value="active"><?php echo t('active'); ?></option>
                            <option value="inactive"><?php echo t('inactive'); ?></option>
                        </select>
                    </div>
                    <div class="mb-3 border rounded p-3 bg-light">
                        <div class="fw-semibold mb-2"><i class="bi bi-key mr-1"></i><?php echo t('portal_access'); ?></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label"><?php echo t('portal_username'); ?></label>
                                <input type="text" name="portal_username" id="tn_portal_username" class="form-control" autocomplete="off">
                            </div>
                            <div>
                                <label class="form-label"><?php echo t('portal_password'); ?></label>
                                <input type="password" name="portal_password" id="tn_portal_password" class="form-control" autocomplete="new-password" placeholder="<?php echo t('portal_keep_hint'); ?>">
                            </div>
                        </div>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="portal_enabled" id="tn_portal_enabled" value="1" class="form-check-input" checked>
                            <label class="form-check-label" for="tn_portal_enabled"><?php echo t('portal_enabled'); ?></label>
                        </div>
                        <div class="form-text mt-1"><?php echo t('portal_last_login'); ?>: <span id="tn_portal_last_login">-</span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('tenantModal')"><?php echo t('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary" id="tn_submit"><?php echo t('save'); ?></button>
                </div>
            </form>
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
var currentUserId = ' . (int)\Auth::id() . ';
function resetTenantForm() {
    document.getElementById("tenantForm").reset();
    document.getElementById("tn_id").value = "";
    document.getElementById("tn_deed_link").innerHTML = "";
    document.getElementById("tn_nid_link").innerHTML = "";
    document.getElementById("tn_portal_username").value = "";
    document.getElementById("tn_portal_password").value = "";
    document.getElementById("tn_portal_enabled").checked = true;
    document.getElementById("tn_advance").value = "";
    var ownerEl = document.getElementById("tn_created_by");
    if (ownerEl) ownerEl.value = currentUserId;
    document.getElementById("tenantModalTitle").textContent = "' . t('add_tenant') . '";
    document.getElementById("tn_submit").textContent = "' . t('save') . '";
}

function viewDocument(url, title) {
    document.getElementById("docViewerFrame").src = BASE_URL + url;
    document.getElementById("docViewerTitle").textContent = title || "' . t('documents') . '";
    openModal("docViewerModal");
}

document.addEventListener("modal:closed", function(e) {
    if (e.detail && e.detail.id === "docViewerModal") {
        document.getElementById("docViewerFrame").src = "";
    }
});

let pendingDocDelete = null;

function deleteDoc(id, type) {
    pendingDocDelete = { id: id, type: type };
    const cm = document.getElementById("confirmModal");
    if (cm) {
        document.getElementById("confirmModalText").textContent = "' . t('doc_delete_confirm') . '";
        openModal("confirmModal");
    }
}

function executeDocDelete(id, type) {
    ajax(BASE_URL + "ajax/tenant_doc_delete.php", { id: id, type: type }, function(result) {
        if (result.success) {
            showToast(result.message, "success");
            const linkEl = document.getElementById(type === "deed" ? "tn_deed_link" : "tn_nid_link");
            if (linkEl) {
                linkEl.innerHTML = "";
            }
            const cellEl = document.getElementById("doc-" + id + "-" + type);
            if (cellEl) {
                const muted = type === "deed"
                    ? "<i class=\"bi bi-file-earmark-text text-muted opacity-25 mr-1\"></i>"
                    : "<i class=\"bi bi-person-vcard text-muted opacity-25\"></i>";
                cellEl.innerHTML = muted;
            }
        } else {
            showToast(result.message || "' . t('error_occurred') . '", "danger");
        }
    });
}

function setDocLink(el, path, label, id, type) {
    el.innerHTML = "";
    if (!path) {
        return;
    }
    const name = path.split("/").pop();
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-link btn-sm p-0";
    btn.title = "' . t('current_file') . ' " + label;
    btn.innerHTML = "<i class=\"bi bi-paperclip\"></i> <span class=\"text-muted\">" + name + "</span>";
    btn.addEventListener("click", function() { viewDocument(path, name); });
    el.appendChild(btn);
    const del = document.createElement("button");
    del.type = "button";
    del.className = "btn btn-link btn-sm p-0 text-danger ml-2";
    del.title = "' . t('remove') . '";
    del.innerHTML = "<i class=\"bi bi-x-circle\"></i>";
    del.addEventListener("click", function() { deleteDoc(id, type); });
    el.appendChild(del);
}

function editTenant(id) {
    var currentLang = ' . json_encode(Lang::current()) . ';
    ajax(BASE_URL + "ajax/tenant_get.php", { id: id, lang: currentLang }, function(result) {
        if (result.success) {
            const d = result.data;
            document.getElementById("tn_id").value = d.id;
            document.getElementById("tn_name").value = d.name;
            document.getElementById("tn_phone").value = d.phone;
            document.getElementById("tn_email").value = d.email || "";
            document.getElementById("tn_nid").value = d.nid || "";
            document.getElementById("tn_address").value = d.address || "";
            document.getElementById("tn_advance").value = d.advance_amount || "";
            document.getElementById("tn_status").value = d.status;
            var ownerEl = document.getElementById("tn_created_by");
            if (ownerEl) ownerEl.value = d.created_by ? d.created_by : currentUserId;
            document.getElementById("tn_portal_username").value = d.portal_username || "";
            document.getElementById("tn_portal_password").value = "";
            document.getElementById("tn_portal_enabled").checked = (d.portal_enabled === undefined ? 1 : d.portal_enabled) == 1;
            setDocLink(document.getElementById("tn_deed_link"), d.deed_file, "' . t('deed') . '", d.id, "deed");
            setDocLink(document.getElementById("tn_nid_link"), d.nid_file, "' . t('nid') . '", d.id, "nid");
            document.getElementById("tenantModalTitle").textContent = "' . t('edit_tenant') . '";
            document.getElementById("tn_submit").textContent = "' . t('update') . '";
            openModal("tenantModal");
        }
    });
}

document.getElementById("tenantForm").addEventListener("submit", function(e) {
    e.preventDefault();
    submitModalForm("tenantForm", BASE_URL + "ajax/tenant_save.php");
});

document.getElementById("confirmDeleteBtn").addEventListener("click", function() {
    if (pendingDocDelete) {
        closeModal("confirmModal");
        executeDocDelete(pendingDocDelete.id, pendingDocDelete.type);
        pendingDocDelete = null;
    }
});
</script>';
require dirname(__DIR__) . '/partials/footer.php';
?>