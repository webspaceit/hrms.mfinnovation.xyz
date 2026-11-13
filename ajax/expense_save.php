<?php
// ============================================================
// Expense AJAX - Save (create/update)
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$category = post('category');
$description = post('description');
$amount = (float)bnToEnDigits(post('amount', 0));
$expense_date = bnToEnDigits(post('expense_date'));
$expense_date = date('Y-m-d', strtotime($expense_date));

if (empty($category) || $amount <= 0 || empty($expense_date)) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$expenseModel = new Expense();
$data = [
    'category' => $category,
    'description' => $description,
    'amount' => $amount,
    'expense_date' => $expense_date
];

if ($id > 0) {
    if (!expenseAccessible($id)) {
        jsonResponse(['success' => false, 'message' => t('access_denied')]);
    }
    $expenseModel->update($id, $data);
    jsonResponse(['success' => true, 'message' => t('updated_success')]);
} else {
    $data['created_by'] = (int)Auth::id();
    $expenseModel->create($data);
    jsonResponse(['success' => true, 'message' => t('added_success')]);
}
