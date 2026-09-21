<?php
// ============================================================
// Logout - ends the admin session, back to the landing page.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class LogoutController extends Controller {

    public function index(): void {
        \Auth::logout();
        redirect('home');
    }
}