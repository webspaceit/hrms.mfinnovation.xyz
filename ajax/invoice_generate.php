<?php
// ============================================================
// Invoice AJAX - Generate invoices for a given month/year
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$month = (int)post('month', (int)date('n'));
$year = (int)post('year', (int)date('Y'));

if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$invoiceModel = new Invoice();
[$created, $updated] = $invoiceModel->generateForMonth($month, $year);

$message = $created + $updated > 0
    ? t('generated_success') . ' (' . $created . ' ' . t('created') . ', ' . $updated . ' ' . t('updated') . ')'
    : t('no_invoices');

jsonResponse([
    'success' => $created + $updated > 0,
    'created' => $created,
    'updated' => $updated,
    'message' => $message
]);