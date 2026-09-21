<?php
// ============================================================
// Tenant Portal - Receipt View (wrapper for receipt.php)
// Forces tenant copy and tenant-only access
// ============================================================

require_once __DIR__ . '/classes/init.php';
Auth::requireTenantLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    redirect('tenant_payments.php');
}

// Verify ownership before including
$paymentModel = new Payment();
$receipt = $paymentModel->getReceiptData($id);

if (!$receipt || (int)$receipt['tenant_id'] !== Auth::tenantId()) {
    Session::setFlash('danger', t('access_denied'));
    redirect('tenant_payments.php');
}

// Force tenant copy and indicate tenant portal context
$_GET['copy'] = 'tenant';
$_GET['tenant_portal'] = '1';
require_once __DIR__ . '/receipt.php';