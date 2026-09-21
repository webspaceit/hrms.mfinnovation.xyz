<?php
// ============================================================
// Tenant Portal - Invoice (Demand Note) View (wrapper for invoice_view.php)
// Forces tenant-only access and portal context, mirroring
// tenant_receipt.php -> receipt.php.
// ============================================================

require_once __DIR__ . '/classes/init.php';
Auth::requireTenantLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    redirect('tenant_dues.php');
}

// Verify ownership before including
$invoiceModel = new Invoice();
$inv = $invoiceModel->getWithDetails($id);

if (!$inv || (int)$inv['tenant_id'] !== Auth::tenantId()) {
    Session::setFlash('danger', t('access_denied'));
    redirect('tenant_dues.php');
}

// Force tenant portal context (hides admin toolbar actions)
$_GET['tenant_portal'] = '1';
require_once __DIR__ . '/invoice_view.php';