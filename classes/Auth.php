<?php
// ============================================================
// Auth Class - handles login, logout, and access control
// ============================================================

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        Session::start();
    }

    /**
     * Attempt to login a user
     */
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        $user = $this->db->fetch(
            "SELECT * FROM users WHERE username = :u OR email = :e LIMIT 1",
            ['u' => $username, 'e' => $username]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('full_name', $user['full_name']);
        Session::set('email', $user['email']);
        Session::set('logged_in', true);

        return ['success' => true, 'message' => 'Logged in successfully'];
    }

    /**
     * Attempt to login a tenant through the tenant portal (tenant_login.php).
     *
     * Tenants live in their own table and use their own session keys, so a
     * tenant session can never satisfy self::check() / requireLogin().
     */
    public function loginTenant($identifier, $password) {
        $identifier = trim((string)$identifier);
        if ($identifier === '' || (string)$password === '') {
            return ['success' => false, 'message' => t('invalid_cred')];
        }

        $tenantModel = new Tenant();
        // Look the account up regardless of portal_enabled so a disabled
        // account gets a specific "contact the office" message.
        $tenant = $tenantModel->findByPortalLoginRaw($identifier);

        if (!$tenant || empty($tenant['portal_password']) || !password_verify((string)$password, (string)$tenant['portal_password'])) {
            return ['success' => false, 'message' => t('invalid_cred')];
        }

        if (isset($tenant['portal_enabled']) && (int)$tenant['portal_enabled'] !== 1) {
            return ['success' => false, 'message' => t('portal_disabled_contact')];
        }

        Session::set('tenant_id', (int)$tenant['id']);
        Session::set('tenant_name', $tenant['name']);
        Session::set('tenant_logged_in', true);

        $tenantModel->touchPortalLogin((int)$tenant['id']);

        return ['success' => true, 'message' => t('logged_in_success')];
    }

    /**
     * Check if user is logged in
     */
    public static function check() {
        Session::start();
        return Session::get('logged_in') === true;
    }

    /**
     * Get current user id
     */
    public static function id() {
        Session::start();
        return Session::get('user_id');
    }

    /**
     * Get current user name
     */
    public static function user($key = null) {
        Session::start();
        if ($key) {
            return Session::get($key);
        }
        return [
            'id' => Session::get('user_id'),
            'username' => Session::get('username'),
            'full_name' => Session::get('full_name'),
            'email' => Session::get('email')
        ];
    }

    /**
     * Require login for a page
     */
    public static function requireLogin() {
        if (!self::check()) {
            redirect('login');
            exit;
        }
    }

    /**
     * Logout current user
     */
    public static function logout() {
        Session::start();
        Session::destroy();
    }

    // ------------------------------------------------------------
    // Tenant portal sessions (separate keys from the admin session,
    // so a tenant can never pass check() / requireLogin()).
    // ------------------------------------------------------------

    /**
     * Is a tenant currently logged in to the portal?
     */
    public static function checkTenant() {
        Session::start();
        return Session::get('tenant_logged_in') === true && (int)Session::get('tenant_id') > 0;
    }

    /**
     * Current portal tenant id (0 when not logged in).
     */
    public static function tenantId() {
        Session::start();
        return self::checkTenant() ? (int)Session::get('tenant_id') : 0;
    }

    /**
     * Current portal tenant display name.
     */
    public static function tenantName() {
        Session::start();
        return (string)Session::get('tenant_name', '');
    }

    /**
     * Require a portal login for a tenant page.
     */
    public static function requireTenantLogin() {
        if (!self::checkTenant()) {
            redirect('tenant-login');
            exit;
        }
    }

    /**
     * Log out of the tenant portal only (an open admin session is kept).
     */
    public static function logoutTenant() {
        Session::start();
        Session::remove('tenant_id');
        Session::remove('tenant_name');
        Session::remove('tenant_logged_in');
    }
}
