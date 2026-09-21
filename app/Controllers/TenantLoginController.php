<?php
// ============================================================
// Tenant Portal - Login
// Tenants sign in with the portal username, phone or email that
// the office set up for them (Tenants -> edit -> Portal Login).
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class TenantLoginController extends Controller {

    public function index(): void {
        // Already signed in to the portal
        if (\Auth::checkTenant()) {
            redirect('tenant-dashboard');
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_verify()) {
                $error = t('invalid_cred');
            } else {
                $auth = new \Auth();
                $result = $auth->loginTenant(post('identifier'), (string)($_POST['password'] ?? ''));
                if ($result['success']) {
                    redirect('tenant-dashboard');
                }
                $error = $result['message'];
            }
        }

        $this->view('tenant-login/index', ['error' => $error]);
    }
}