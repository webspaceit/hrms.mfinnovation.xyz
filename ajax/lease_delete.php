<?php
// ============================================================
// Lease AJAX - Delete
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(post('id'))) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$leaseId = (int)post('id');
$leaseModel = new Lease();
$flatModel = new Flat();

$lease = $leaseModel->find($leaseId);
if ($lease) {
    $leaseModel->delete($leaseId);
    $flatModel->refreshStatus($lease['flat_id']);
}

jsonResponse(['success' => true, 'message' => t('deleted_success')]);
