<?php
// ============================================================
// Expense AJAX - Delete
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(post('id'))) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$expenseId = (int)post('id');
if (!expenseAccessible($expenseId)) {
    jsonResponse(['success' => false, 'message' => t('access_denied')]);
}

$expenseModel = new Expense();
$expenseModel->delete($expenseId);

jsonResponse(['success' => true, 'message' => t('deleted_success')]);
