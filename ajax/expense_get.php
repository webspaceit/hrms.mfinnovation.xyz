<?php
// ============================================================
// Expense AJAX - Get single record
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(post('id'))) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$expenseModel = new Expense();
$data = $expenseModel->find((int)post('id'));

if (!$data) {
    jsonResponse(['success' => false, 'message' => 'Not found']);
}

foreach (['amount'] as $k) {
    if (isset($data[$k]) && $data[$k] !== null) {
        $data[$k] = bnNumeral($data[$k]);
    }
}
if (isset($data['expense_date']) && $data['expense_date'] !== null) {
    $data['expense_date'] = bnNumeral(date('d-m-Y', strtotime($data['expense_date'])));
}

jsonResponse(['success' => true, 'data' => $data]);
