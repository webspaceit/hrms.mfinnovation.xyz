<?php
// ============================================================
// Helper Functions
// ============================================================

require_once __DIR__ . '/../classes/Lang.php';

/**
 * Escape output for HTML
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Convert ASCII digits to Bengali numerals (display only, when language is Bengali)
 */
function bnNumeral($str) {
    if ($str === null) return $str;
    $str = (string)$str;
    if (Lang::current() !== 'bn') return $str;
    return strtr($str, [
        '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
        '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯'
    ]);
}

/**
 * Normalize a stored value to ASCII digits regardless of which script it holds,
 * converting any Bengali numerals to ASCII (phone/NID/amounts stored as text).
 */
function enDigits($str) {
    if ($str === null) return $str;
    return bnToEnDigits((string)$str);
}

/**
 * Format money with currency
 */
function money($amount) {
    $amount = (float)$amount;
    return CURRENCY . bnNumeral(number_format($amount, 2));
}

/**
 * Get current language helper
 */
function lang() {
    return Lang::current();
}

/**
 * Simple plural helper (not for numbers > 1 order-sensitive locales)
 */
function plural($n, $singular, $plural = null) {
    if ($plural === null) {
        return (int)$n === 1 ? $singular : $singular;
    }
    return (int)$n === 1 ? $singular : $plural;
}

/**
 * Month names in multiple languages
 */
function monthName($month, $lang = null) {
    $lang = $lang ?: Lang::current();
    if ($lang === 'bn') {
        $months = [
            1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
            5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
            9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর'
        ];
    } else {
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
    }
    return $months[(int)$month] ?? $month;
}

/**
 * Format a status for display with badge classes
 */
function statusBadge($status) {
    $class = 'secondary';
    switch (strtolower($status)) {
        case 'active':
        case 'available':
            $class = 'success';
            break;
        case 'occupied':
            $class = 'info';
            break;
        case 'inactive':
        case 'expired':
        case 'terminated':
        case 'maintenance':
            $class = 'warning';
            break;
    }
    return [
        'class' => $class,
        'label' => t($status, ucfirst($status))
    ];
}

/**
 * Badge markup for an invoice payment status (unpaid / partial / paid).
 * Used by the invoices list and the tenant portal.
 */
function paymentStatusBadge($status) {
    $status = strtolower((string)$status);
    switch ($status) {
        case 'paid':
            $class = 'success';
            break;
        case 'partial':
            $class = 'warning';
            break;
        default:
            $status = 'unpaid';
            $class = 'danger';
    }
    return '<span class="badge bg-' . $class . '-subtle text-' . $class . '-emphasis">'
        . e(t('status_' . $status, ucfirst($status)))
        . '</span>';
}

/**
 * Build an application URL. Accepts route names ('payments'),
 * legacy file paths ('payments.php'), or ''/'index.php'/'home'
 * for the landing page.
 */
function url($path = '') {
    $path = trim((string)$path);
    if ($path === '' || $path === 'index.php' || $path === 'home' || $path === 'landing') {
        return BASE_URL;
    }
    if (str_ends_with($path, '.php')) {
        return BASE_URL . $path;
    }
    return BASE_URL . ltrim($path, '/');
}

/**
 * Redirect helper - accepts routes ('payments') or legacy *.php paths.
 */
function redirect($url) {
    header('Location: ' . url($url));
    exit;
}

/**
 * True when the given route matches the current request (used by the
 * shared header to highlight the active nav item). Falls back to the
 * legacy PHP file name for direct page requests.
 */
function routeIs($route) {
    $current = defined('CURRENT_ROUTE') ? CURRENT_ROUTE : basename($_SERVER['PHP_SELF']);
    return $current === $route || $current === $route . '.php';
}

/**
 * Generate JSON response
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Get input value from POST/GET
 */
function input($key, $default = '') {
    return $_POST[$key] ?? ($_GET[$key] ?? $default);
}

/**
 * Get post data sanitized
 */
function post($key, $default = '') {
    return trim($_POST[$key] ?? $default);
}

/**
 * Generate and store CSRF token in session
 */
function csrf_token() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token from request
 */
function csrf_verify($token = null) {
    $expected = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    $provided = $token ?? ($_POST[CSRF_TOKEN_NAME] ?? ($_GET[CSRF_TOKEN_NAME] ?? ''));
    return hash_equals($expected, (string)$provided);
}

/**
 * Get CSRF token input for forms
 */
function csrf_field() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . e(csrf_token()) . '">';
}

/**
 * Require valid CSRF token (for AJAX endpoints)
 * Returns false if invalid, exits with JSON error response
 */
function csrf_require() {
    if (!csrf_verify()) {
        jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }
}

/**
 * Read a value from the settings table (singleton-cached)
 */
function getSetting($key, $default = null) {
    static $cache = [];
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = Database::getInstance()->fetchColumn(
            "SELECT setting_value FROM settings WHERE setting_key = :k",
            ['k' => $key]
        );
    }
    $val = $cache[$key];
    return ($val === false || $val === null) ? $default : $val;
}

/**
 * Upsert a value into the settings table
 */
function setSetting($key, $value) {
    Database::getInstance()->execute(
        "INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = :v2",
        ['k' => $key, 'v' => (string)$value, 'v2' => (string)$value]
    );
}

/**
 * Convert Bengali digits to ASCII digits (phone numbers, amounts, etc.)
 */
function bnToEnDigits($str) {
    if ($str === null) return $str;
    return strtr((string)$str, [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'
    ]);
}

/**
 * Detect if a string contains Bengali (Bangla) characters.
 */
function isBengali($str) {
    return (bool)preg_match('/[\x{0980}-\x{09FF}]/u', (string)$str);
}

/**
 * Translate a short text via MyMemory with a DB-backed cache.
 * Returns the original text on failure (offline, quota exceeded, etc.).
 */
function translateText($text, $from, $to) {
    $text = trim((string)$text);
    if ($text === '') return $text;

    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(\Database::prefix("SELECT result FROM translation_cache WHERE src = :src AND lang_from = :f AND lang_to = :t LIMIT 1"));
        $stmt->execute([':src' => $text, ':f' => $from, ':t' => $to]);
        $cached = $stmt->fetchColumn();
        if ($cached !== false && $cached !== null && $cached !== '') {
            $db->prepare(\Database::prefix("UPDATE translation_cache SET hits = hits + 1 WHERE src = :src AND lang_from = :f AND lang_to = :t"))
                ->execute([':src' => $text, ':f' => $from, ':t' => $to]);
            return $cached;
        }
    } catch (Exception $e) {
        $db = null;
    }

    $result = null;
    $url = 'https://api.mymemory.translated.net/get?langpair=' . rawurlencode($from . '|' . $to) . '&q=' . rawurlencode($text);
    $raw = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            // Local dev box lacks a CA bundle; retry with verification off
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $raw = curl_exec($ch);
        }
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($code == 200 && $raw) {
            $j = json_decode($raw, true);
            $txt = $j['responseData']['translatedText'] ?? null;
            if ($txt && stripos($txt, 'MYMEMORY') === false) {
                $result = $txt;
            }
        }
    } else {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw) {
            $j = json_decode($raw, true);
            $txt = $j['responseData']['translatedText'] ?? null;
            if ($txt && stripos($txt, 'MYMEMORY') === false) {
                $result = $txt;
            }
        }
    }

    if ($result !== null && $db) {
        try {
            $db->prepare(\Database::prefix("INSERT INTO translation_cache (src, lang_from, lang_to, result) VALUES (:src, :f, :t, :r) ON DUPLICATE KEY UPDATE result = VALUES(result)"))
                ->execute([':src' => $text, ':f' => $from, ':t' => $to, ':r' => $result]);
        } catch (Exception $e) {}
    }

    return $result !== null ? $result : $text;
}

/**
 * Global helper function
 */
function t($key, $default = null) {
    return Lang::get($key, $default);
}

/**
 * Render a stored display name in the current UI language.
 * Stored values are kept in English; the Bengali UI translates to Bengali,
 * and any legacy Bengali-stored value is translated to English on the English UI.
 */
function localizeName($name) {
    $name = trim((string)$name);
    if ($name === '') return $name;
    // No letters? Nothing to translate (digits, dates, punctuation...)
    if (!preg_match('/\p{L}/u', $name)) return $name;
    $lang = Lang::current();
    if ($lang === 'bn' && !isBengali($name)) {
        return translateText($name, 'en', 'bn');
    }
    if ($lang === 'en' && isBengali($name)) {
        return translateText($name, 'bn', 'en');
    }
    return $name;
}

/**
 * Generic alias of localizeName for arbitrary free-text fields (address, etc.)
 */
function localizeText($text) {
    return localizeName($text);
}

/**
 * Normalize a tenant/customer name to English before storing
 * (mirrors how phone/NID are stored in ASCII).
 */
function storeName($name) {
    $name = trim((string)$name);
    if ($name === '') return $name;
    if (!preg_match('/\p{L}/u', $name)) return $name;
    if (isBengali($name)) {
        $en = translateText($name, 'bn', 'en');
        return $en !== '' ? $en : $name;
    }
    return $name;
}

/**
 * Store free-text (address, description, notes) exactly as typed.
 * No involuntary script conversion: a Bengali address stays Bengali,
 * and the other language is produced on display via localizeText().
 */
function storeText($text) {
    return trim((string)$text);
}

/**
 * Localize a stored floor label ("1st", "2nd", "Ground", "1", ...)
 * into the current UI language.
 */
function floorLabel($floor) {
    $floor = trim((string)$floor);
    if ($floor === '') return '-';
    $key = strtolower($floor);
    if (Lang::current() !== 'bn') {
        return ucfirst($floor);
    }
    $map = [
        'ground'   => 'গ্রাউন্ড ফ্লোর',
        'gf'       => 'গ্রাউন্ড ফ্লোর',
        'g/f'      => 'গ্রাউন্ড ফ্লোর',
        '1'        => '১ম তলা',  '1st' => '১ম তলা',
        '2'        => '২য় তলা',  '2nd' => '২য় তলা',
        '3'        => '৩য় তলা',  '3rd' => '৩য় তলা',
        '4'        => '৪র্থ তলা', '4th' => '৪র্থ তলা',
        '5'        => '৫ম তলা',   '5th' => '৫ম তলা',
        '6'        => '৬ষ্ঠ তলা', '6th' => '৬ষ্ঠ তলা',
        '7'        => '৭ম তলা',   '7th' => '৭ম তলা',
        '8'        => '৮ম তলা',   '8th' => '৮ম তলা',
        '9'        => '৯ম তলা',   '9th' => '৯ম তলা',
        '10'       => '১০ম তলা',  '10th' => '১০ম তলা',
        '11'       => '১১তম তলা', '11th' => '১১তম তলা',
        '12'       => '১২তম তলা', '12th' => '১২তম তলা',
        '13'       => '১৩তম তলা', '13th' => '১৩তম তলা',
        '14'       => '১৪তম তলা', '14th' => '১৪তম তলা',
        '15'       => '১৫তম তলা', '15th' => '১৫তম তলা',
        '16'       => '১৬তম তলা', '16th' => '১৬তম তলা',
        '17'       => '১৭তম তলা', '17th' => '১৭তম তলা',
        '18'       => '১৮তম তলা', '18th' => '১৮তম তলা',
        '19'       => '১৯তম তলা', '19th' => '১৯তম তলা',
        '20'       => '২০তম তলা', '20th' => '২০তম তলা',
    ];
    if (isset($map[$key])) {
        return $map[$key];
    }
    return ucfirst($floor);
}

/**
 * Latin letter -> Bengali letter map, for flat numbers / unit codes (5A -> ৫এ).
 */
function bnLetterMap() {
    return [
        'A' => 'এ', 'B' => 'বি', 'C' => 'সি', 'D' => 'ডি', 'E' => 'ই',
        'F' => 'এফ', 'G' => 'জি', 'H' => 'এইচ', 'I' => 'আই', 'J' => 'জে',
        'K' => 'কে', 'L' => 'এল', 'M' => 'এম', 'N' => 'এন', 'O' => 'ও',
        'P' => 'পি', 'Q' => 'কিউ', 'R' => 'আর', 'S' => 'এস', 'T' => 'টি',
        'U' => 'ইউ', 'V' => 'ভি', 'W' => 'ডব্লিউ', 'X' => 'এক্স', 'Y' => 'ওয়াই', 'Z' => 'জেড'
    ];
}

/**
 * Convert Bengali letters back to their Latin equivalents (inverse of bnLetterMap).
 */
function bnToEnLetters($str) {
    $map = [];
    foreach (bnLetterMap() as $latin => $bn) {
        $map[$bn] = $latin;
    }
    return strtr((string)$str, $map);
}

/**
 * Localize a stored expense category ("Electricity", "Water", ...)
 * into the current UI language.
 */
function expenseCategoryLabel($cat) {
    $cat = trim((string)$cat);
    if ($cat === '') return $cat;
    if (Lang::current() !== 'bn') {
        return $cat;
    }
    $map = [
        'electricity' => 'বিদ্যুৎ',
        'water'       => 'পানি',
        'gas'         => 'গ্যাস',
        'repair'      => 'মেরামত',
        'maintenance' => 'রক্ষণাবেক্ষণ',
        'salary'      => 'বেতন',
        'tax'         => 'কর',
        'internet'    => 'ইন্টারনেট',
        'cleaning'    => 'পরিষ্কার',
        'other'       => 'অন্যান্য',
    ];
    $key = strtolower($cat);
    if (isset($map[$key])) {
        return $map[$key];
    }
    return localizeText($cat);
}

/**
 * Normalize a unit code (flat number) to ASCII/Latin before storing.
 * "৫এ" -> "5A", "5A" -> "5A".
 */
function enFlatNo($str) {
    return bnToEnLetters(bnToEnDigits($str));
}

/**
 * Display a unit code (flat number) in the current UI language.
 * bn: "5A" -> "৫এ"; en: "৫এ" -> "5A".
 */
function bnFlatCode($str) {
    $str = enFlatNo($str); // normalize any legacy stored Bengali digits/letters
    if (Lang::current() !== 'bn') {
        return $str;
    }
    $str = strtr(strtoupper($str), bnLetterMap());
    return bnNumeral($str);
}

/**
 * Convert amount to words (English or Bengali)
 */
function numberToWords($num, $lang = null) {
    $lang = $lang ?: Lang::current();
    $num = floor((float)$num);
    return $lang === 'bn' ? bengaliToWords($num) : englishToWords($num);
}

/**
 * English number to words
 */
function englishToWords($num) {
    if ($num == 0) return 'Zero';
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
             'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $words = '';
    if ($num >= 1000000000) { $words .= englishToWords(floor($num / 1000000000)) . ' Billion '; $num %= 1000000000; }
    if ($num >= 1000000) { $words .= englishToWords(floor($num / 1000000)) . ' Million '; $num %= 1000000; }
    if ($num >= 1000) { $words .= englishToWords(floor($num / 1000)) . ' Thousand '; $num %= 1000; }
    if ($num >= 100) { $words .= englishToWords(floor($num / 100)) . ' Hundred '; $num %= 100; }
    if ($num > 0) {
        if ($words != '') $words .= 'and ';
        if ($num < 20) $words .= $ones[$num];
        else { $words .= $tens[floor($num / 10)]; $rem = $num % 10; if ($rem) $words .= '-' . $ones[$rem]; }
    }
    return trim($words);
}

/**
 * Bengali number to words (lakh/crore system)
 */
function bengaliToWords($num) {
    if ($num == 0) return 'শূন্য';

    $ones = ['', 'এক', 'দুই', 'তিন', 'চার', 'পাঁচ', 'ছয়', 'সাত', 'আট', 'নয়'];
    $teens = ['দশ', 'এগারো', 'বারো', 'তেরো', 'চৌদ্দ', 'পনেরো', 'ষোল', 'সতেরো', 'আঠারো', 'উনিশ'];
    $tenth = ['বিশ', 'ত্রিশ', 'চল্লিশ', 'পঞ্চাশ', 'ষাট', 'সত্তর', 'আশি', 'নব্বই'];
    // 21-99 irregular forms
    $irregular = [
        21 => 'একুশ', 22 => 'বাইশ', 23 => 'তেইশ', 24 => 'চব্বিশ', 25 => 'পঁচিশ',
        26 => 'ছাব্বিশ', 27 => 'সাতাশ', 28 => 'আটাশ', 29 => 'ঊনত্রিশ',
        31 => 'একত্রিশ', 32 => 'বত্রিশ', 33 => 'তেত্রিশ', 34 => 'চৌত্রিশ', 35 => 'পঁয়ত্রিশ',
        36 => 'ছত্রিশ', 37 => 'সাঁইত্রিশ', 38 => 'আটত্রিশ', 39 => 'ঊনচল্লিশ',
        41 => 'একচল্লিশ', 42 => 'বিয়াল্লিশ', 43 => 'তেতাল্লিশ', 44 => 'চুয়াল্লিশ', 45 => 'পঁয়তাল্লিশ',
        46 => 'ছেচল্লিশ', 47 => 'সাতচল্লিশ', 48 => 'আটচল্লিশ', 49 => 'ঊনপঞ্চাশ',
        51 => 'একান্ন', 52 => 'বায়ান্ন', 53 => 'তিপ্পান্ন', 54 => 'চুয়ান্ন', 55 => 'পঞ্চান্ন',
        56 => 'ছাপ্পান্ন', 57 => 'সাতান্ন', 58 => 'আটান্ন', 59 => 'ঊনষাট',
        61 => 'একষট্টি', 62 => 'বাষট্টি', 63 => 'তেষট্টি', 64 => 'চৌষট্টি', 65 => 'পঁয়ষট্টি',
        66 => 'ছেষট্টি', 67 => 'সাতষট্টি', 68 => 'আটষট্টি', 69 => 'ঊনসত্তর',
        71 => 'একাত্তর', 72 => 'বাহাত্তর', 73 => 'তিয়াত্তর', 74 => 'চুয়াত্তর', 75 => 'পঁচাত্তর',
        76 => 'ছিয়াত্তর', 77 => 'সাতাত্তর', 78 => 'আটাত্তর', 79 => 'ঊনআশি',
        81 => 'একাশি', 82 => 'বিরাশি', 83 => 'তিরাশি', 84 => 'চুরাশি', 85 => 'পঁচাশি',
        86 => 'ছিয়াশি', 87 => 'সাতাশি', 88 => 'আটাশি', 89 => 'ঊননব্বই',
        91 => 'একানব্বই', 92 => 'বিরানব্বই', 93 => 'তিরানব্বই', 94 => 'চুরানব্বই', 95 => 'পঁচানব্বই',
        96 => 'ছিয়ানব্বই', 97 => 'সাতানব্বই', 98 => 'আটানব্বই', 99 => 'নিরানব্বই'
    ];

$two = function($n) use ($ones, $teens, $tenth, $irregular) {
        if ($n < 10) return $ones[$n];
        if ($n < 20) return $teens[$n - 10];
        if (isset($irregular[$n])) return $irregular[$n];
        $d = $n % 10;
        return $tenth[(int)($n / 10) - 2] . ($d ? ' ' . $ones[$d] : '');
    };

    $words = '';
    if ($num >= 10000000) { $words .= $two((int)($num / 10000000)) . ' কোটি '; $num %= 10000000; }
    if ($num >= 100000) { $words .= $two((int)($num / 100000)) . ' লাখ '; $num %= 100000; }
    if ($num >= 1000) { $words .= $two((int)($num / 1000)) . ' হাজার '; $num %= 1000; }
    if ($num >= 100) { $words .= $two((int)($num / 100)) . 'শ '; $num %= 100; }
    if ($num > 0) {
        if ($words != '') $words .= '';
        $words .= $two($num);
    }
    return trim($words);
}

/**
 * Upload one tenant document (deed / NID). Images and PDF only, max 20 MB.
 * Images are resized/re-encoded with GD; PDFs are compressed via Ghostscript
 * when it is available (otherwise stored as-is).
 * Returns the relative path (e.g. "uploads/tenants/deed_3_1710000000.pdf"),
 * false on an invalid/unreadable file, or null when no file was provided.
 */
function uploadDocument($file, $kind, $ownerId) {
    if (!$file || !isset($file['error'])) {
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > MAX_UPLOAD_SIZE) {
        return false;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);

    if (!isset($allowed[$mime])) {
        return false;
    }

    // Additional: verify file extension matches MIME type
    $originalName = $file['name'] ?? '';
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $expectedExt = $allowed[$mime];
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    if (!in_array($ext, $allowedExts) || $ext !== $expectedExt && !($ext === 'jpeg' && $expectedExt === 'jpg')) {
        return false;
    }

    $filename = $kind . '_' . (int)$ownerId . '_' . time() . '.' . $expectedExt;
    $uploadDir = __DIR__ . '/../uploads/tenants/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filepath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return false;
    }

    $tmp = $uploadDir . 'tmp_' . $filename;
    if ($mime !== 'image/gif' && compressUploadedDocument($filepath, $tmp, $mime)) {
        @unlink($filepath);
        @rename($tmp, $filepath);
    } else {
        @unlink($tmp);
    }

    return 'uploads/tenants/' . $filename;
}

/**
 * Compress an uploaded document in place: images via GD, PDFs via Ghostscript.
 * Returns true on success (a smaller valid file at $destPath).
 */
function compressUploadedDocument($srcPath, $destPath, $mime) {
    if ($mime === 'application/pdf') {
        return compressPdfWithGhostscript($srcPath, $destPath);
    }
    return compressUploadedImage($srcPath, $destPath, $mime);
}

/**
 * Resize (if needed) and re-encode an image with GD.
 * ZIP transparent PNG/WebP kept; GIF is not processed here.
 */
function compressUploadedImage($srcPath, $destPath, $mime, $maxDim = null, $quality = 82) {
    if (!function_exists('imagecreatefromjpeg')) {
        return false;
    }
    $maxDim = $maxDim ?: MAX_IMAGE_DIM;

    $img = null;
    switch ($mime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($srcPath); break;
        case 'image/png':  $img = @imagecreatefrompng($srcPath); break;
        case 'image/webp': $img = @imagecreatefromwebp($srcPath); break;
    }
    if (!$img) {
        return false;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    if ($w > $maxDim || $h > $maxDim) {
        $ratio = min($maxDim / $w, $maxDim / $h);
        $nw = max(1, (int)round($w * $ratio));
        $nh = max(1, (int)round($h * $ratio));
        $resized = imagecreatetruecolor($nw, $nh);
        if (!$resized) {
            imagedestroy($img);
            return false;
        }
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $img = $resized;
    }

    $ok = false;
    switch ($mime) {
        case 'image/jpeg': $ok = imagejpeg($img, $destPath, $quality); break;
        case 'image/png':  $ok = imagepng($img, $destPath, 9); break;
        case 'image/webp': $ok = imagewebp($img, $destPath, $quality); break;
    }

    if (!$ok || !is_file($destPath) || filesize($destPath) >= filesize($srcPath)) {
        if (is_file($destPath)) {
            @unlink($destPath);
        }
        return false;
    }
    return true;
}

/**
 * Recompress a PDF with Ghostscript (/ebook ~150 DPI). Returns true only when
 * Ghostscript ran successfully AND produced a smaller file.
 */
function compressPdfWithGhostscript($srcPath, $destPath) {
    if (!function_exists('exec') || !defined('GS_PATH') || GS_PATH === '') {
        return false;
    }
    @exec('"' . GS_PATH . '" --version 2>&1', $availOut, $availCode);
    if ($availCode !== 0) {
        return false;
    }

    $cmd = '"' . GS_PATH . '" -sDEVICE=pdfwrite -dPDFSETTINGS=/ebook -dCompatibilityLevel=1.4'
         . ' -dNOPAUSE -dQUIET -dBATCH -dDetectDuplicateImages=true'
         . ' -sOutputFile="' . $destPath . '" "' . $srcPath . '" 2>&1';
    @exec($cmd, $out, $code);

    if ($code !== 0 || !is_file($destPath) || filesize($destPath) >= filesize($srcPath)) {
        if (is_file($destPath)) {
            @unlink($destPath);
        }
        return false;
    }
    return true;
}

/**
 * Delete a stored document file if it exists (path is repo-owned).
 */
function deleteUploadedDocument($relativePath) {
    if (!$relativePath) {
        return;
    }
    $full = __DIR__ . '/../' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * JSON-encode a PHP string as a clean JavaScript string literal
 * (forward slashes and Unicode kept readable).
 */
function jsQuote($str) {
    return json_encode((string)$str, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Inline colored action buttons for every table's Actions column —
 * same pattern as the reference transactions table: small colored icon
 * pills with a tooltip, no dropdown.
 *
 * Each item is an array:
 *   label   => translated label (shown as the tooltip / aria-label)
 *   icon    => Bootstrap icon class (e.g. 'bi bi-pencil')
 *   variant => button color: 'edit' (amber), 'delete' (red), 'view' (blue),
 *              'success' (green), 'info' (sky), 'primary' (theme)
 *   href    => optional link URL (renders an <a>)
 *   onclick => optional JS handler (renders a <button>)
 */
function actionButtons(array $items) {
    if (!$items) {
        return '<span class="text-muted">&mdash;</span>';
    }
    $html = '<div class="actions-cell">';
    foreach ($items as $it) {
        $variant = !empty($it['variant']) ? $it['variant'] : 'primary';
        $title   = !empty($it['label']) ? (string)$it['label'] : '';
        $icon    = !empty($it['icon']) ? '<i class="' . e($it['icon']) . '"></i>' : '';
        $cls     = 'action-btn action-btn-' . $variant;
        if (!empty($it['href'])) {
            $html .= '<a class="' . $cls . '" href="' . e($it['href']) . '"'
                  . ' title="' . e($title) . '" aria-label="' . e($title) . '">' . $icon . '</a>';
        } else {
            $html .= '<button type="button" class="' . $cls . '"'
                  . ' title="' . e($title) . '" aria-label="' . e($title) . '"'
                  . (!empty($it['onclick']) ? ' onclick="' . e($it['onclick']) . '"' : '')
                  . '>' . $icon . '</button>';
        }
    }
    $html .= '</div>';
    return $html;
}
