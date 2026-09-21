<?php
// ============================================================
// Tenant Portal - My Information (flat + lease + profile)
// All data is scoped to the logged-in tenant id; the tenant can
// view everything but only change his/her own portal password.
// Former tenant_profile.php, now a controller + view.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class TenantProfileController extends Controller {

    public function index(): void {
        \Auth::requireTenantLogin();

        $tenantId = \Auth::tenantId();

        $tenantModel = new \Tenant();
        $leaseModel  = new \Lease();

        $tenant = $tenantModel->find($tenantId);
        if (!$tenant) {
            \Auth::logoutTenant();
            redirect('tenant-login');
        }

        $leases = $leaseModel->forTenant($tenantId);
        $lease  = $leases ? $leases[0] : null;

        // ---- Portal password change ----
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'change_password') {
            if (!csrf_verify()) {
                \Session::setFlash('danger', 'Invalid CSRF token');
                redirect('tenant-profile');
            }
            $current = (string)($_POST['current_password'] ?? '');
            $new     = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            if (!password_verify($current, (string)($tenant['portal_password'] ?? ''))) {
                \Session::setFlash('danger', t('current_password_wrong'));
            } elseif (mb_strlen($new) < 6) {
                \Session::setFlash('danger', t('password_min_length'));
            } elseif ($new !== $confirm) {
                \Session::setFlash('danger', t('password_mismatch'));
            } else {
                $tenantModel->setPortalPassword($tenantId, $new);
                \Session::setFlash('success', t('password_updated'));
            }
            redirect('tenant-profile');
        }

        $this->view('tenant-profile/index', [
            'tenantId' => $tenantId,
            'tenant'   => $tenant,
            'lease'    => $lease,
        ]);
    }
}