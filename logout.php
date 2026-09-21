<?php
// ============================================================
// Logout Page
// ============================================================

require_once __DIR__ . '/classes/init.php';

Auth::logout();
redirect('index.php');
