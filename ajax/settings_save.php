<?php
// ============================================================
// Settings AJAX - Save site preferences
// Admin only. Currently the default language: the language a visitor
// sees when they have not picked one themselves, in both the office
// and the tenant portal. A language chosen in the top bar is a
// per-browser preference and still wins over this setting.
// ============================================================

require_once __DIR__ . '/../classes/init.php';
Auth::requireAdmin();
csrf_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => t('error_occurred')], 400);
}

$language = (string)post('language', '');
if (!in_array($language, Lang::allowed(), true)) {
    jsonResponse(['success' => false, 'message' => t('error_occurred')]);
}

setSetting('language', $language);

jsonResponse(['success' => true, 'message' => t('updated_success')]);
