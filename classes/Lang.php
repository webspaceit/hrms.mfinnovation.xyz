<?php
// ============================================================
// Language / Translation Class
// Supports English and Bengali
// ============================================================

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

class Lang {
    private static $translations = [];
    private static $currentLang = DEFAULT_LANG;

    /**
     * Initialize language system
     */
    public static function init() {
        Session::start();

        // Determine language: session -> cookie -> default
        if (Session::has('lang')) {
            self::$currentLang = Session::get('lang');
        } elseif (isset($_COOKIE['rms_lang'])) {
            self::$currentLang = $_COOKIE['rms_lang'];
            Session::set('lang', self::$currentLang);
        } else {
            self::$currentLang = DEFAULT_LANG;
        }

        self::load();
    }

    /**
     * Set the language
     */
    public static function setLang($lang) {
        $allowed = ['en', 'bn'];
        if (!in_array($lang, $allowed)) {
            $lang = DEFAULT_LANG;
        }
        self::$currentLang = $lang;
        Session::set('lang', $lang);
        setcookie('rms_lang', $lang, time() + (60 * 60 * 24 * 30), '/');
        self::load();
    }

    /**
     * Temporarily override language for this request only
     * (does not write session/cookie)
     */
    public static function override($lang) {
        $allowed = ['en', 'bn'];
        if (in_array($lang, $allowed)) {
            self::$currentLang = $lang;
            self::load();
        }
    }

    /**
     * Get current language
     */
    public static function current() {
        return self::$currentLang;
    }

    /**
     * Load translation file
     */
    private static function load() {
        $file = __DIR__ . '/../lang/' . self::$currentLang . '.php';
        if (file_exists($file)) {
            self::$translations = include $file;
        } else {
            self::$translations = [];
        }
    }

    /**
     * Translate a key
     */
    public static function get($key, $default = null) {
        if (isset(self::$translations[$key])) {
            return self::$translations[$key];
        }
        // fallback to English
        $enFile = __DIR__ . '/../lang/en.php';
        if (self::$currentLang !== 'en' && file_exists($enFile)) {
            $en = include $enFile;
            if (isset($en[$key])) {
                return $en[$key];
            }
        }
        return $default !== null ? $default : $key;
    }

    /**
     * Shortcut function with optional parameters for formatting
     */
    public static function t($key, $default = null) {
        return self::get($key, $default);
    }
}

// Global helper function removed to avoid conflicts with Helpers.php
