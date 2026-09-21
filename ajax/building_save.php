<?php
// ============================================================
// Building AJAX - Save (create/update)
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$id = (int)post('id', 0);
$name = storeText(post('name'));
$address = storeText(post('address'));
$description = storeText(post('description'));

if (empty($name)) {
    jsonResponse(['success' => false, 'message' => t('error_occurred') . ': Name required']);
}

$buildingModel = new Building();
$data = [
    'name' => $name,
    'address' => $address,
    'description' => $description
];

if ($id > 0) {
    $buildingModel->update($id, $data);
    jsonResponse(['success' => true, 'message' => t('updated_success')]);
} else {
    $data['total_flats'] = 0;
    $buildingModel->create($data);
    jsonResponse(['success' => true, 'message' => t('added_success')]);
}
