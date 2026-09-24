<?php
// ============================================================
// Tenant AJAX - Get single record
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireLogin();

// Apply language override from AJAX request
if (isset($_POST['lang']) && in_array($_POST['lang'], ['en', 'bn'])) {
    Lang::override($_POST['lang']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(post('id'))) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$tenantModel = new Tenant();
$data = $tenantModel->find((int)post('id'));

if (!$data) {
    jsonResponse(['success' => false, 'message' => 'Not found']);
}

// Landlords may only open the tenants they entered.
if (!tenantAccessible((int)$data['id'])) {
    jsonResponse(['success' => false, 'message' => t('access_denied')], 403);
}

// Never expose the portal password hash to the browser.
unset($data['portal_password']);

$data['name'] = localizeName($data['name']);

if (isset($data['address']) && $data['address'] !== '') {
    $data['address'] = localizeText($data['address']);
}

foreach (['phone', 'nid'] as $k) {
    if (isset($data[$k]) && $data[$k] !== '') {
        $data[$k] = bnNumeral(enDigits($data[$k]));
    }
}

if (!isset($data['deed_file'])) $data['deed_file'] = '';
if (!isset($data['nid_file'])) $data['nid_file'] = '';
// Portal admin fields for the tenant modal (never the password hash).
$data['portal_username'] = $data['portal_username'] ?? '';
$data['portal_enabled'] = isset($data['portal_enabled']) ? (int)$data['portal_enabled'] : 1;
$data['portal_last_login'] = $data['portal_last_login'] ?? null;
unset($data['portal_password']);
$data['created_by'] = $data['created_by'] ?? null;

// Advance shown in the Edit Tenant modal comes from the tenant's unit
// (flats.advance_amount, maintained on the Flats page).
$unit = $tenantModel->currentUnit($data['id']);
$data['advance_amount'] = $unit && $unit['advance_amount'] !== null ? (string)$unit['advance_amount'] : '';
// The unit type (flat / shop) is exposed for the tenant modal.
$data['unit_type'] = $unit ? ($unit['unit_type'] ?? null) : null;

jsonResponse(['success' => true, 'data' => $data]);
