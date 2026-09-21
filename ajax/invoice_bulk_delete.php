<?php
// ============================================================
// Invoice AJAX - Bulk Delete Invoices
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

// Sanitize IDs
$ids = isset($_POST['ids']) ? $_POST['ids'] : [];
if (!is_array($ids) || empty($ids)) {
    jsonResponse(['success' => false, 'message' => 'No invoices selected for deletion']);
}

// Sanitize IDs
$ids = array_map('intval', $ids);
$ids = array_filter($ids, fn($id) => $id > 0);

if (empty($ids)) {
    jsonResponse(['success' => false, 'message' => 'Invalid invoice selection']);
}

$invoiceModel = new Invoice();
$deletedCount = 0;
$errorCount = 0;

foreach ($ids as $id) {
    try {
        if ($invoiceModel->delete($id)) {
            $deletedCount++;
        } else {
            $errorCount++;
        }
    } catch (Exception $e) {
        $errorCount++;
    }
}

if ($deletedCount > 0 && $errorCount === 0) {
    jsonResponse(['success' => true, 'message' => $deletedCount . ' invoice(s) deleted successfully']);
} elseif ($deletedCount > 0) {
    jsonResponse(['success' => true, 'message' => $deletedCount . ' deleted, ' . $errorCount . ' failed', 'deleted' => $deletedCount, 'failed' => $errorCount]);
} else {
    jsonResponse(['success' => false, 'message' => 'Failed to delete invoices']);
}
