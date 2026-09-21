<?php
// ============================================================
// Building AJAX - Get single record
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(post('id'))) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$buildingModel = new Building();
$data = $buildingModel->find((int)post('id'));

if (!$data) {
    jsonResponse(['success' => false, 'message' => 'Not found']);
}

foreach (['name', 'address', 'description'] as $k) {
    if (isset($data[$k]) && $data[$k] !== '') {
        $data[$k] = localizeText($data[$k]);
    }
}

jsonResponse(['success' => true, 'data' => $data]);
