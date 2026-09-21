<?php
// ============================================================
// Language AJAX Endpoint
// ============================================================

require_once __DIR__ . '/../classes/init.php';
csrf_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lang = post('lang', 'en');
    Lang::setLang($lang);
    jsonResponse(['success' => true, 'lang' => Lang::current()]);
}

jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
