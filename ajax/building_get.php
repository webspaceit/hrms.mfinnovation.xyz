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

if (!buildingAccessible((int)$data['id'])) {
    jsonResponse(['success' => false, 'message' => 'Not found']);
}

// Keep the untouched row: the edit dialog is pre-filled with the display
// language, and the form posts these values back for fields the user did not
// edit, so saving never overwrites the stored text with its translation.
$raw = $data;

foreach (['name', 'address', 'description'] as $k) {
    if (isset($data[$k]) && $data[$k] !== '') {
        $data[$k] = localizeText($data[$k]);
    }
}

// Same treatment the table applies, so an English dialog shows "0865-00" while
// the Bengali one shows "০৮৬৫-০০".
if (isset($data['holding_no']) && $data['holding_no'] !== '') {
    $data['holding_no'] = bnFlatCode($data['holding_no']);
}

jsonResponse(['success' => true, 'data' => $data, 'raw' => $raw]);
