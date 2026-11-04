<?php
// ============================================================
// Language / Translation Class
// Supports English and Bengali
// ============================================================

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

class Lang {
    const SCOPE_ADMIN  = 'admin';
    const SCOPE_TENANT = 'tenant';

    private static $translations = [];
    private static $currentLang = DEFAULT_LANG;
    private static $scope = self::SCOPE_ADMIN;
    private static $defaultLang = null;

    /**
     * Languages this build ships a translation file for.
     */
    public static function allowed() {
        return ['en', 'bn'];
    }

    /**
     * Which area of the site the visitor is looking at.
     *
     * The office and the tenant portal run on the same session and the same
     * browser, so they keep separate language preferences. They used to share
     * one 'lang' session key, which meant an admin who switched the UI to
     * English pushed every tenant who then used the same machine into English
     * too - the portal could never get back to Bengali on its own. Scope is
     * derived from the portal session rather than the route, because
     * Lang::init() runs before routing.
     */
    public static function detectScope() {
        return Session::get('tenant_logged_in') === true
            ? self::SCOPE_TENANT
            : self::SCOPE_ADMIN;
    }

    /** Current scope for this request. */
    public static function scope() {
        return self::$scope;
    }

    private static function sessionKey() {
        return self::$scope === self::SCOPE_TENANT ? 'tenant_lang' : 'lang';
    }

    private static function cookieName() {
        return self::$scope === self::SCOPE_TENANT ? 'rms_tenant_lang' : 'rms_lang';
    }

    /**
     * The site-wide default language, from the settings table.
     *
     * This is what Settings -> Default language writes, and what a visitor
     * sees before picking a language themselves. Falls back to the DEFAULT_LANG
     * constant when the setting is missing, blank or not a shipped language,
     * and when the settings table cannot be read (a half-finished install).
     */
    public static function defaultLang() {
        if (self::$defaultLang !== null) {
            return self::$defaultLang;
        }
        $lang = DEFAULT_LANG;
        if (function_exists('getSetting')) {
            try {
                $lang = getSetting('language', DEFAULT_LANG);
            } catch (Throwable $e) {
                $lang = DEFAULT_LANG;
            }
        }
        if (!is_string($lang) || !in_array($lang, self::allowed(), true)) {
            $lang = DEFAULT_LANG;
        }
        self::$defaultLang = $lang;
        return $lang;
    }

    /**
     * Coerce a stored value to a language this build supports, falling back to
     * the site default rather than to a hardcoded 'bn'.
     */
    private static function normalise($lang) {
        return is_string($lang) && in_array($lang, self::allowed(), true)
            ? $lang
            : self::defaultLang();
    }

    /**
     * Initialize language system
     */
    public static function init() {
        Session::start();
        self::$scope = self::detectScope();

        $sessionKey = self::sessionKey();
        $cookieName = self::cookieName();

        // Determine language: this area's session -> its cookie -> site default
        if (Session::has($sessionKey)) {
            self::$currentLang = self::normalise(Session::get($sessionKey));
        } elseif (isset($_COOKIE[$cookieName])) {
            self::$currentLang = self::normalise($_COOKIE[$cookieName]);
            Session::set($sessionKey, self::$currentLang);
        } else {
            self::$currentLang = self::defaultLang();
        }

        self::load();
    }

    /**
     * Set the language for the current area (see detectScope()).
     */
    public static function setLang($lang) {
        $lang = self::normalise($lang);
        self::$currentLang = $lang;
        Session::set(self::sessionKey(), $lang);
        setcookie(self::cookieName(), $lang, time() + (60 * 60 * 24 * 30), '/');
        self::load();
    }

    /**
     * Temporarily override language for this request only
     * (does not write session/cookie)
     */
    public static function override($lang) {
        if (in_array($lang, self::allowed(), true)) {
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
