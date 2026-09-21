<?php
// ============================================================
// Tenant Portal - Logout
// Leaves an open admin session untouched (portal keys only).
// ============================================================

require_once __DIR__ . '/classes/init.php';

Auth::logoutTenant();
redirect('index.php');
