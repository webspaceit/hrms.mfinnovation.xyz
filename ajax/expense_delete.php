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

$expenseModel = new Expense();
$expenseModel->delete((int)post('id'));

jsonResponse(['success' => true, 'message' => t('deleted_success')]);
