<?php
// ============================================================
// Session Class
// ============================================================

require_once __DIR__ . '/../config/config.php';

class Session {

    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
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
