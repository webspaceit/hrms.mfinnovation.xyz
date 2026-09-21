<?php
// ============================================================
// Expenses Page
// ============================================================

require_once __DIR__ . '/classes/init.php';
Auth::requireLogin();

$pageTitle = t('expenses');

$expenseModel = new Expense();
$expenses = $expenseModel->all('expense_date DESC, id DESC');

require_once __DIR__ . '/inc/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-receipt mr-2"></i><?php echo t('expenses'); ?>
        <small class="text-muted fs-6 ml-2"><?php echo t('total'); ?>: <?php echo money($expenseModel->totalExpense()); ?></small>
    </h4>
    <div class="flex items-center gap-2">
        <button id="bulkDeleteBtn" class="btn btn-sm btn-danger" style="display:none;" data-delete-text="<?php echo t('delete_selected'); ?>" onclick="BulkSelect.confirmAndDelete()">
            <i class="bi bi-trash mr-1"></i><?php echo t('delete_selected'); ?> (0)
        </button>
        <button class="btn btn-primary" onclick="resetExpenseForm(); openModal('expenseModal')">
            <i class="bi bi-plus-lg mr-1"></i> <?php echo t('add_expense'); ?>
        </button>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" data-bulk-entity="expenses">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>#</th>
                        <th><?php echo t('category'); ?></th>
                        <th><?php echo t('description'); ?></th>
                        <th><?php echo t('expense_date'); ?></th>
                        <th><?php echo t('amount'); ?></th>
                        <th><?php echo t('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="7" class="text-muted py-4"><?php echo t('no_data'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $i => $ex): ?>
                            <tr data-id="<?php echo $ex['id']; ?>">
                                <td><input type="checkbox" class="bulk-checkbox form-check-input" value="<?php echo $ex['id']; ?>"></td>
                                <td><?php echo bnNumeral($i + 1); ?></td>
                                <td><span class="badge bg-warning-subtle text-warning-emphasis"><?php echo e(expenseCategoryLabel($ex['category'])); ?></span></td>
                                <td><?php echo e($ex['description'] ?: '-'); ?></td>
                                <td><?php echo e(bnNumeral(date('d-m-Y', strtotime($ex['expense_date'])))); ?></td>
                                <td class="fw-semibold text-danger"><?php echo money($ex['amount']); ?></td>
                                <td>
                                    <?php echo actionButtons([
                                        ['label' => t('edit'), 'icon' => 'bi bi-pencil', 'variant' => 'edit', 'onclick' => 'editExpense(' . (int)$ex['id'] . ')'],
                                        ['label' => t('delete'), 'icon' => 'bi bi-trash', 'variant' => 'delete',
                                         'onclick' => 'confirmDelete(' . jsQuote(BASE_URL . 'ajax/expense_delete.php?id=' . (int)$ex['id']) . ', ' . jsQuote(t('delete_confirm')) . ')'],
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

<!-- Expense Modal -->
<div class="modal" id="expenseModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title" id="expenseModalTitle"><?php echo t('add_expense'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('expenseModal')" aria-label="Close"></button>
            </div>
            <form id="expenseForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="ex_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('category'); ?> <span class="text-danger">*</span></label>
                        <select name="category" id="ex_category" class="form-select" required>
                            <?php foreach (['Electricity', 'Water', 'Gas', 'Repair', 'Maintenance', 'Salary', 'Tax', 'Internet', 'Cleaning', 'Other'] as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo e(expenseCategoryLabel($cat)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('description'); ?></label>
                        <input type="text" name="description" id="ex_description" class="form-control">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label"><?php echo t('amount'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="amount" id="ex_amount" class="form-control" inputmode="decimal" required>
                        </div>
                        <div>
                            <label class="form-label"><?php echo t('expense_date'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="expense_date" id="ex_date" class="form-control" placeholder="DD-MM-YYYY" inputmode="numeric" data-date required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('expenseModal')"><?php echo t('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary" id="ex_submit"><?php echo t('save'); ?></button>
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
function getToday() {
    const d = new Date();
    return String(d.getDate()).padStart(2,"0") + "-" + String(d.getMonth()+1).padStart(2,"0") + "-" + d.getFullYear();
}
function resetExpenseForm() {
    document.getElementById("expenseForm").reset();
    document.getElementById("ex_id").value = "";
    document.getElementById("ex_date").value = getToday();
    document.getElementById("expenseModalTitle").textContent = "' . t('add_expense') . '";
    document.getElementById("ex_submit").textContent = "' . t('save') . '";
}

function editExpense(id) {
    ajax(BASE_URL + "ajax/expense_get.php", { id: id }, function(result) {
        if (result.success) {
            const d = result.data;
            document.getElementById("ex_id").value = d.id;
            document.getElementById("ex_category").value = d.category;
            document.getElementById("ex_description").value = d.description || "";
            document.getElementById("ex_amount").value = d.amount;
            document.getElementById("ex_date").value = d.expense_date;
            document.getElementById("expenseModalTitle").textContent = "' . t('edit') . '";
            document.getElementById("ex_submit").textContent = "' . t('update') . '";
            openModal("expenseModal");
        }
    });
}

document.getElementById("expenseForm").addEventListener("submit", function(e) {
    e.preventDefault();
    submitModalForm("expenseForm", BASE_URL + "ajax/expense_save.php");
});
</script>';
require __DIR__ . '/inc/footer.php';
?>