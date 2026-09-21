<?php
// ============================================================
// Lease AJAX - Get single record with details
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(post('id'))) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$leaseModel = new Lease();
$data = $leaseModel->findWithDetails((int)post('id'));

if (!$data) {
    jsonResponse(['success' => false, 'message' => 'Not found']);
}

foreach (['rent_amount', 'advance_amount', 'utility_fee'] as $k) {
    if (isset($data[$k]) && $data[$k] !== null) {
        $data[$k] = bnNumeral($data[$k]);
    }
}
foreach (['start_date', 'end_date'] as $k) {
    if (isset($data[$k]) && $data[$k] !== null && $data[$k] !== '') {
        $data[$k] = bnNumeral(date('d-m-Y', strtotime($data[$k])));
    }
}

foreach (['tenant_name', 'building_name'] as $k) {
    if (isset($data[$k]) && $data[$k] !== '') {
        $data[$k] = localizeText($data[$k]);
    }
}

if (isset($data['flat_no']) && $data['flat_no'] !== '') {
    $data['flat_no'] = bnFlatCode($data['flat_no']);
}

jsonResponse(['success' => true, 'data' => $data]);
