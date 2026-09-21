<?php
// ============================================================
// Flat AJAX - Get single record
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(post('id'))) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$flatModel = new Flat();
$data = $flatModel->find((int)post('id'));

if (!$data) {
    jsonResponse(['success' => false, 'message' => 'Not found']);
}

foreach (['rent_amount', 'advance_amount', 'size_sqft', 'floor', 'bedrooms', 'bathrooms'] as $k) {
    if (isset($data[$k]) && $data[$k] !== null) {
        $data[$k] = bnNumeral(enDigits($data[$k]));
    }
}

if (isset($data['flat_no'])) {
    $data['flat_no'] = bnFlatCode($data['flat_no']);
}

jsonResponse(['success' => true, 'data' => $data]);
