<?php
// ============================================================
// Language AJAX Endpoint
// ============================================================

require_once __DIR__ . '/../classes/init.php';
csrf_require();

// Lang::init() (classes/init.php) has already worked out whether this is the
// office or the tenant portal, and Lang::setLang() writes that area's own
// preference, so switching the UI in the portal no longer overwrites the
// office preference in the same browser. A missing 'lang' keeps the current
// language instead of defaulting to English and silently flipping the page.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lang = post('lang', Lang::current());
    Lang::setLang($lang);
    jsonResponse(['success' => true, 'lang' => Lang::current()]);
}

jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
