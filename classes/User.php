<?php
// ============================================================
// User Model
// ============================================================

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    protected $table = 'users';

    public function findByUsername($username) {
        return $this->db->fetch(
            "SELECT * FROM users WHERE username = :u OR email = :e LIMIT 1",
            ['u' => $username, 'e' => $username]
        );
    }

    /**
     * All accounts, newest first, list-friendly columns only.
     */
    public function allUsers($orderBy = 'id DESC') {
        return $this->db->fetchAll(
            "SELECT id, username, full_name, email, role, created_at FROM users ORDER BY $orderBy"
        );
    }

    /**
     * Number of accounts with the admin role (guard for the
     * "at least one admin must remain" rule).
     */
    public function countAdmins() {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE role = 'admin'"
        );
    }

    /**
     * Is the username/email already used by another account?
     */
    public function usernameTaken($username, $exceptId = 0) {
        $user = $this->db->fetch(
            "SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1",
            ['u' => $username, 'e' => $username]
        );
        return $user && (int)$user['id'] !== (int)$exceptId;
    }

    /**
     * Update a user's role, disallowing demotion of the last admin.
     */
    public function updateRole($id, $role) {
        $target = $this->find($id);
        if (!$target) {
            return ['success' => false, 'message' => 'User not found'];
        }
        if ($role !== 'admin' && (string)$target['role'] === 'admin' && $this->countAdmins() <= 1) {
            return ['success' => false, 'message' => \Auth::id() === (int)$id
                ? t('cannot_change_own_role', 'You cannot change your own role')
                : t('last_admin_required', 'At least one admin account is required')];
        }
        $this->update($id, ['role' => $role]);
        return ['success' => true];
    }
}