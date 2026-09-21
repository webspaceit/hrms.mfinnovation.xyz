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
}
