<?php
// ============================================================
// Admin login - auth gate. Handles the POST submit, then the
// dashboard route on success.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class LoginController extends Controller {

    public function index(): void {
        // Already logged in? Straight to the dashboard.
        if (\Auth::check()) {
            redirect('dashboard');
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth = new \Auth();
            $result = $auth->login(post('username'), post('password'));
            if ($result['success']) {
                redirect('dashboard');
            }
            $error = $result['message'];
        }

        $this->view('login/index', ['error' => $error]);
    }
}