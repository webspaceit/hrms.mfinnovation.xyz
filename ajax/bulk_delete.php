<?php
// ============================================================
// Generic Bulk Delete AJAX Endpoint
// Handles bulk deletion for all entities with proper
// post-delete side effects (status refresh, document cleanup,
// invoice reconciliation)
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$entity = post('entity', '');
$ids = isset($_POST['ids']) ? $_POST['ids'] : [];

if (!is_array($ids) || empty($ids)) {
    jsonResponse(['success' => false, 'message' => 'No records selected for deletion']);
}

$ids = array_map('intval', $ids);
$ids = array_filter($ids, fn($id) => $id > 0);

if (empty($ids)) {
    jsonResponse(['success' => false, 'message' => 'Invalid selection']);
}

// Configure entity-specific handling
$entityConfig = [
    'buildings'  => ['model' => 'Building',  'side_effect' => null],
    'flats'      => ['model' => 'Flat',      'side_effect' => null],
    'tenants'    => ['model' => 'Tenant',    'side_effect' => 'documents'],
    'leases'     => ['model' => 'Lease',     'side_effect' => 'lease'],
    'payments'   => ['model' => 'Payment',   'side_effect' => 'payment'],
    'expenses'   => ['model' => 'Expense',   'side_effect' => null],
];

if (!isset($entityConfig[$entity])) {
    jsonResponse(['success' => false, 'message' => 'Unknown entity type'], 400);
}

$modelClass = $entityConfig[$entity]['model'];
$sideEffect = $entityConfig[$entity]['side_effect'];

$model = new $modelClass();
$deletedCount = 0;
$errorCount = 0;

try {
    foreach ($ids as $id) {
        // Capture data needed for side effects before deleting
        $record = $model->find($id);
        
        if (!$record) {
            $errorCount++;
            continue;
        }

        // Run pre-delete side effects
        switch ($sideEffect) {
            case 'documents':
                // Tenant: delete uploaded documents from disk
                deleteUploadedDocument($record['deed_file'] ?? '');
                deleteUploadedDocument($record['nid_file'] ?? '');
                break;

            case 'lease':
                // Lease: store flat_id for status refresh
                $flatModel = new Flat();
                break;

            case 'payment':
                // Payment: store lease_id, month, year for invoice reconciliation
                $paymentData = [
                    'lease_id' => (int)$record['lease_id'],
                    'month'    => (int)$record['month'],
                    'year'     => (int)$record['year']
                ];
                break;
        }

        if ($model->delete($id)) {
            $deletedCount++;

            // Run post-delete side effects
            switch ($sideEffect) {
                case 'lease':
                    $flatModel->refreshStatus((int)$record['flat_id']);
                    break;

                case 'payment':
                    $paymentModel = new Payment();
                    $paymentModel->reconcileInvoice(
                        $paymentData['lease_id'],
                        $paymentData['month'],
                        $paymentData['year']
                    );
                    break;
            }
        } else {
            $errorCount++;
        }
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

if ($deletedCount > 0) {
    $message = $deletedCount . ' record(s) deleted successfully';
    if ($errorCount > 0) {
        $message .= ', ' . $errorCount . ' failed';
    }
    jsonResponse(['success' => true, 'message' => $message, 'deleted' => $deletedCount, 'failed' => $errorCount]);
} else {
    jsonResponse(['success' => false, 'message' => 'Failed to delete any records']);
}
