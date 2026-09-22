<?php
// Users view - data: $users (array), $currentUserId, $currentRole
$pageTitle = t('users');
require dirname(__DIR__) . '/partials/header.php';
$roleLabels = [
    'admin'    => t('admin'),
    'landlord' => t('landlord'),
];
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-person-gear mr-2"></i><?php echo t('users'); ?></h4>
    <div class="flex items-center gap-2">
        <button class="btn btn-primary" onclick="openUserModal()">
            <i class="bi bi-plus-lg mr-1"></i> <?php echo t('add_user'); ?>
        </button>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo t('full_name'); ?></th>
                        <th><?php echo t('username'); ?></th>
                        <th><?php echo t('email'); ?></th>
                        <th><?php echo t('role'); ?></th>
                        <th><?php echo t('created_at', 'Created'); ?></th>
                        <th class="text-end"><?php echo t('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" class="text-muted py-4"><?php echo t('no_data'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $i => $u): ?>
                            <?php $isSelf = (int)$u['id'] === (int)$currentUserId; ?>
                            <tr data-id="<?php echo (int)$u['id']; ?>">
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td class="fw-semibold">
                                    <i class="bi bi-person-circle mr-1 text-primary"></i>
                                    <?php echo e($u['full_name']); ?>
                                    <?php if ($isSelf): ?>
                                        <span class="badge bg-secondary" title="<?php echo t('you_this', 'It is you'); ?>"><i class="bi bi-person-check"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($u['username']); ?></td>
                                <td><?php echo e($u['email'] ?: '-'); ?></td>
                                <td>
                                    <select class="form-select form-select-sm mw-130 d-inline-block"
                                            onchange="changeRole(<?php echo (int)$u['id']; ?>, this)"
                                            data-default="<?php echo e($u['role']); ?>">
                                        <?php foreach ($roleLabels as $val => $label): ?>
                                            <option value="<?php echo e($val); ?>" <?php echo $u['role'] === $val ? 'selected' : ''; ?>>
                                                <?php echo e($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><?php echo e(!empty($u['created_at']) ? date('d M Y', strtotime($u['created_at'])) : '-'); ?></td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-primary" title="<?php echo t('edit'); ?>"
                                            onclick="openUserModal(<?php echo (int)$u['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" title="<?php echo t('reset_password'); ?>"
                                            onclick="openPwdModal(<?php echo (int)$u['id']; ?>, '<?php echo addslashes($u['full_name']); ?>')">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" title="<?php echo t('delete'); ?>"
                                            <?php if ($isSelf): ?>disabled<?php endif; ?>
                                            onclick="confirmDelete(
                                                '<?php echo BASE_URL; ?>ajax/user_delete.php?id=<?php echo (int)$u['id']; ?>',
                                                '<?php echo addslashes(t('delete_user_q', 'Delete this user account?')); ?>'
                                            )">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title" id="userModalTitle"><?php echo t('add_user'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('userModal')" aria-label="Close"></button>
            </div>
            <form id="userForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('full_name'); ?> *</label>
                        <input type="text" class="form-control" name="full_name" id="user_full_name" maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('username'); ?> *</label>
                        <input type="text" class="form-control" name="username" id="user_username" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('email'); ?></label>
                        <input type="email" class="form-control" name="email" id="user_email" maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('role'); ?></label>
                        <select class="form-select" name="role" id="user_role">
                            <option value="admin"><?php echo t('admin'); ?></option>
                            <option value="landlord"><?php echo t('landlord'); ?></option>
                        </select>
                    </div>
                    <div class="mb-3" id="user_pwd_field">
                        <label class="form-label" id="user_pwd_label"><?php echo t('password'); ?> *</label>
                        <input type="password" class="form-control" name="password" id="user_password" autocomplete="new-password">
                        <div class="form-text" id="user_pwd_hint"></div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('userModal')"><?php echo t('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo t('save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="pwdModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-key mr-1"></i><?php echo t('reset_password'); ?></h5>
                <button type="button" class="btn-close" onclick="closeModal('pwdModal')" aria-label="Close"></button>
            </div>
            <form id="pwdForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="pwd_user_id">
                <div class="modal-body">
                    <p class="text-muted" id="pwd_user_name"></p>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('new_password'); ?> *</label>
                        <input type="password" class="form-control" name="password" id="pwd_value" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('confirm_password'); ?> *</label>
                        <input type="password" class="form-control" id="pwd_confirm" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('pwdModal')"><?php echo t('cancel'); ?></button>
                    <button type="submit" class="btn btn-warning text-dark"><?php echo t('reset_password'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete confirm -->
<div class="modal" id="confirmModal">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle mr-1"></i><?php echo t('delete_confirm'); ?></h5>
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

<script>
const USERS = <?php echo json_encode($users, JSON_UNESCAPED_UNICODE); ?>;
const CURRENT_USER_ID = <?php echo (int)$currentUserId; ?>;

function openUserModal(id) {
    const form = document.getElementById('userForm');
    form.reset();
    document.getElementById('user_id').value = '';
    document.getElementById('user_role').value = 'landlord';
    const pwdField = document.getElementById('user_pwd_field');
    const pwdLabel = document.getElementById('user_pwd_label');
    const pwdHint = document.getElementById('user_pwd_hint');
    document.getElementById('user_password').required = true;
    pwdLabel.innerHTML = '<?php echo t('password'); ?> *';
    pwdHint.textContent = '';

    if (id) {
        const u = USERS.find(x => parseInt(x.id, 10) === parseInt(id, 10));
        if (!u) return;
        document.getElementById('userModalTitle').textContent = <?php echo jsQuote(t('edit_user')); ?>;
        document.getElementById('user_id').value = u.id;
        document.getElementById('user_full_name').value = u.full_name;
        document.getElementById('user_username').value = u.username;
        document.getElementById('user_email').value = u.email || '';
        document.getElementById('user_role').value = u.role;
        // Leave blank to keep current password
        document.getElementById('user_password').required = false;
        pwdLabel.innerHTML = '<?php echo t('password'); ?>';
        pwdHint.textContent = <?php echo jsQuote(t('leave_blank_keep', 'Leave blank to keep the current password')); ?>;
        // Your own role cannot be changed
        if (parseInt(u.id, 10) === CURRENT_USER_ID) {
            document.getElementById('user_role').disabled = true;
        }
    } else {
        document.getElementById('userModalTitle').textContent = <?php echo jsQuote(t('add_user')); ?>;
        document.getElementById('user_role').disabled = false;
    }
    openModal('userModal');
}

function openPwdModal(id, name) {
    document.getElementById('pwd_user_id').value = id;
    document.getElementById('pwd_user_name').textContent = name;
    document.getElementById('pwd_value').value = '';
    document.getElementById('pwd_confirm').value = '';
    openModal('pwdModal');
}

function changeRole(id, el) {
    const sel = el;
    ajax('<?php echo BASE_URL; ?>ajax/user_role.php', { id: id, role: sel.value }, function(result) {
        if (!result.success) {
            sel.value = sel.dataset.default; // revert
            showToast(result.message || 'Error', 'danger');
        } else {
            sel.dataset.default = role;
            showToast(result.message, 'success');
        }
    });
}

document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitModalForm('userForm', '<?php echo BASE_URL; ?>ajax/user_save.php');
});

document.getElementById('pwdForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const pw = document.getElementById('pwd_value').value;
    const cf = document.getElementById('pwd_confirm').value;
    if (pw !== cf) {
        showToast(<?php echo jsQuote(t('passwords_not_match', 'Passwords do not match')); ?>, 'danger');
        return;
    }
    submitModalForm('pwdForm', '<?php echo BASE_URL; ?>ajax/user_password.php');
});
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>