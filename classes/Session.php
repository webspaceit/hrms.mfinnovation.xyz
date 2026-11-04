<?php
// ============================================================
// Session Class
// ============================================================

require_once __DIR__ . '/../config/config.php';

class Session {

    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);

            // Harden session cookie for shared hosting:
            // - httponly: JS cannot read the cookie
            // - samesite=Lax: CSRF protection
            // - secure: HTTPS only (auto-detected; skipped on plain HTTP dev)
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                     || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                     || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

            // PHP 7.3+ supports the options array form of session_set_cookie_params
            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                // PHP < 7.3 fallback
                session_set_cookie_params(0, '/', '', $isSecure, true);
            }

            session_start();
        }
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key) {
        return isset($_SESSION[$key]);
    }

    public static function remove($key) {
        unset($_SESSION[$key]);
    }

    public static function destroy() {
        $_SESSION = [];
        session_destroy();
    }

    public static function setFlash($type, $message) {
        $_SESSION['flash_' . $type] = $message;
    }

    public static function hasFlash($type) {
        return isset($_SESSION['flash_' . $type]);
    }

    public static function getFlash($type, $default = '') {
        $msg = $_SESSION['flash_' . $type] ?? $default;
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }

    // Alias for compatibility
    public static function message($type, $msg = null) {
        if ($msg !== null) {
            self::setFlash($type, $msg);
            return null;
        }
        return self::getFlash($type);
    }
}
