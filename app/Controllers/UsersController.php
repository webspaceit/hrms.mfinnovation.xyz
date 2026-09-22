<?php
// ============================================================
// Users - list accounts, change access (admin / landlord),
// add users, reset passwords, delete. Admin role only.
// ============================================================

namespace App\Controllers;

use App\Core\Controller;

class UsersController extends Controller {

    public function index(): void {
        \Auth::requireAdmin();

        $userModel = new \User();
        $users = $userModel->allUsers();

        $this->view('users/index', [
            'users'         => $users,
            'currentUserId' => (int)\Auth::id(),
            'currentRole'   => \Auth::role(),
        ]);
    }
}