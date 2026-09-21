<?php
// ============================================================
// Flat AJAX - Save (create/update)
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$building_id = (int)post('building_id', 0);
$unit_type = post('unit_type', 'flat');
if ($unit_type !== 'shop') {
    $unit_type = 'flat';
}
$flat_no = enFlatNo(post('flat_no'));
$floor = bnToEnDigits(post('floor'));
$bedrooms = (int)bnToEnDigits(post('bedrooms', 0));
$bathrooms = (int)bnToEnDigits(post('bathrooms', 0));
$size_sqft = post('size_sqft') !== '' ? (float)bnToEnDigits(post('size_sqft')) : null;
$rent_amount = (float)bnToEnDigits(post('rent_amount', 0));
$advance_amount = (float)bnToEnDigits(post('advance_amount', 0));
$status = post('status', 'available');

if (empty($building_id) || empty($flat_no)) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

$flatModel = new Flat();
$data = [
    'building_id' => $building_id,
    'unit_type' => $unit_type,
    'flat_no' => $flat_no,
    'floor' => $floor,
    'bedrooms' => $bedrooms,
    'bathrooms' => $bathrooms,
    'size_sqft' => $size_sqft,
    'rent_amount' => $rent_amount,
    'advance_amount' => $advance_amount,
    'status' => $status
];

if ($id > 0) {
    $flatModel->update($id, $data);
    jsonResponse(['success' => true, 'message' => t('updated_success')]);
} else {
    $flatModel->create($data);
    jsonResponse(['success' => true, 'message' => t('added_success')]);
}
