<?php
/**
 * Aurora Library — Input validation helpers
 *
 * One place for all server-side validation. Each helper returns either
 * a normalised value (on success) or null/false (on failure), and an
 * accompanying `aurora_validate_*_errors` array accumulates messages
 * keyed by field name for form repopulation.
 */

if (!function_exists('aurora_normalize_email')) {
    /**
     * Lowercase + trim an email address.
     * Email local parts are technically case-sensitive per RFC 5321 §2.4,
     * but in practice virtually every provider treats them as case-insensitive.
     * Normalising to lowercase avoids duplicate accounts caused by casing.
     */
    function aurora_normalize_email($raw) {
        return strtolower(trim((string)$raw));
    }
}

if (!function_exists('aurora_validate_email')) {
    function aurora_validate_email($raw) {
        $email = aurora_normalize_email($raw);
        if ($email === '' || strlen($email) > 254) return false;
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) return false;
        $at = strrpos($email, '@');
        if ($at === false) return false;
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        if ($local === '' || $domain === '') return false;
        if (strpos($domain, '.') === false) return false;
        if ($domain[0] === '.' || $domain[0] === '-' || substr($domain, -1) === '-') return false;
        if ($local[0] === '.' || substr($local, -1) === '.') return false;
        return true;
    }
}

if (!function_exists('aurora_validate_mobile')) {
    function aurora_validate_mobile($raw) {
        $m = trim((string)$raw);
        if ($m === '') return true; // optional
        return (bool) preg_match('/^\+?[0-9\-\s()]{6,20}$/', $m);
    }
}

/* ------------------------------------------------------------------ */
/*  String / name / text validation                                     */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_validate_length')) {
    /**
     * Returns the trimmed value if it falls within [$min, $max] chars,
     * or null otherwise. Empty values are accepted when $allowEmpty.
     */
    function aurora_validate_length($raw, $min, $max, $allowEmpty = false) {
        $s = trim((string)$raw);
        if ($s === '') return $allowEmpty ? '' : null;
        // Use mb_strlen for to length when possible
        $len = function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
        if ($len < $min || $len > $max) return null;
        return $s;
    }
}

if (!function_exists('aurora_validate_name')) {
    /**
     * A "name" field: required, between 2 and 100 chars, must contain at
         * least one letter. Allows letters (Unicode), spaces, dot, hyphen,
     * apostrophe — i.e. real-world stuff like "St. John's-Smith".
     *
     * Returns the normalised value or null.
     */
    function aurora_validate_name($raw) {
        $s = trim((string)$raw);
        if ($s === '') return null;
        $len = function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
        if ($len < 2 || $len > 100) return null;
        // Must contain at least one letter (Unicode-aware when possible).
        $hasLetter = preg_match('/\p{L}/u', $s) === 1;
        if (!$hasLetter) return null;
        // Allowed characters: letters, marks, numbers, spaces, hyphen, apostrophe, dot, comma.
        if (!preg_match("/^[\p{L}\p{M}\p{N} .'\-,]+$/u", $s)) return null;
        return $s;
    }
}

if (!function_exists('aurora_validate_isbn')) {
    /**
     * ISBN-10 or ISBN-13 (with or without dashes/spaces). Optional field.
     * Returns the digits-only canonical form on success, '' null.
     */
    function aurora_validate_isbn($raw, $allowEmpty = true) {
        $s = strtoupper(trim((string)$raw));
        if ($s === '') return $allowEmpty ? '' : null;
        // Strip spaces and dashes; keep X for ISBN-10.
        $clean = preg_replace('/[\s\-]/', '', $s);
        if (!preg_match('/^[0-9]{9}[0-9X]$|^[0-9]{13}$/', $clean)) return null;
        // Modulus checks: ISBN-10 (sum weighted 10..1) and ISBN-13 (sum weighted 1..3 alternating).
        if (strlen($clean) === 10) {
            $sum = 0;
            for ($i = 0; $i < 10; $i++) {
                $d = ($clean[$i] === 'X') ? 10 : (int)$clean[$i];
                $sum += $d * (10 - $i);
            }
            return ($sum % 11 === 0) ? $clean : null;
        }
        // ISBN-13
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int)$clean[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return ($sum % 10 === 0) ? $clean : null;
    }
}

if (!function_exists('aurora_validate_date')) {
    /**
     * Returns 'Y-m-d' on success, '' null. Accepts Y-m-d and common
     * human formats that strtotime() recognises.
     */
    function aurora_validate_date($raw, $allowEmpty = true) {
        $s = trim((string)$raw);
        if ($s === '') return $allowEmpty ? '' : null;
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}

/* ------------------------------------------------------------------ */
/*  Password validation                                                 */
/* ------------------------------------------------------------------ */

if (!defined('AURORA_PASSWORD_MIN')) define('AURORA_PASSWORD_MIN', 8);

if (!function_exists('aurora_validate_password_strength')) {
    /**
     * Returns null when the password satisfies the policy:
     *   - at least 8 chars
     *   - at least one lowercase letter
     *   - at least one uppercase letter
     *   - at least one digit
     *
     * Returns the password string when valid.
     */
    function aurora_validate_password_strength($raw) {
        $p = (string)$raw;
        if (strlen($p) < AURORA_PASSWORD_MIN) return null;
        if (!preg_match('/[a-z]/', $p)) return null;
        if (!preg_match('/[A-Z]/', $p)) return null;
        if (!preg_match('/[0-9]/', $p)) return null;
        return $p;
    }

    function aurora_password_strength_rules() {
        return [
            'Minimum ' . AURORA_PASSWORD_MIN . ' characters',
            'At least one lowercase letter',
            'At least one uppercase letter',
            'At least one number',
        ];
    }
}

/* ------------------------------------------------------------------ */
/*  ID / numeric validation                                            */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_validate_positive_int')) {
    /**
     * Accepts anything filter_input() / ctype_digit() would accept as a
     * positive integer, normalises to int. Returns null when invalid.
     */
    function aurora_validate_positive_int($raw, $max = null) {
        if ($raw === null || $raw === '') return null;
        // Accept string-of-digits or already-int.
        if (is_int($raw)) {
            if ($raw < 1) return null;
            if ($max !== null && $raw > $max) return null;
            return $raw;
        }
        if (!is_string($raw)) return null;
        if (!ctype_digit($raw)) return null;
        $n = (int)$raw;
        if ($n < 1) return null;
        if ($max !== null && $n > $max) return null;
        return $n;
    }
}

if (!function_exists('aurora_validate_non_negative_int')) {
    /**
     * Integer >= 0. Returns null when invalid.
     */
    function aurora_validate_non_negative_int($raw, $max = null) {
        if ($raw === null || $raw === '') return null;
        if (is_int($raw)) {
            if ($raw < 0) return null;
            if ($max !== null && $raw > $max) return null;
            return $raw;
        }
        if (!is_string($raw)) return null;
        if ($raw !== '0' && !ctype_digit($raw)) return null;
        $n = (int)$raw;
        if ($n < 0) return null;
        if ($max !== null && $n > $max) return null;
        return $n;
    }
}

if (!function_exists('aurora_validate_non_negative_float')) {
    /**
     * Float >= 0. Accepts decimal notation only. Returns null when invalid.
     */
    function aurora_validate_non_negative_float($raw, $max = null) {
        if ($raw === null || $raw === '') return null;
        if (!is_numeric($raw)) return null;
        $f = (float)$raw;
        if ($f < 0) return null;
        if ($max !== null && $f > $max) return null;
        return $f;
    }
}

/* ------------------------------------------------------------------ */
/*  Cross-cutting input helpers                                         */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_get_post_string')) {
    /**
     * Trim a string POST field safely. Returns '' when missing.
     * Never echoes the value back into HTML — callers must htmlspecialchars.
     */
    function aurora_get_post_string($key, $default = '') {
        if (!isset($_POST[$key])) return $default;
        return trim((string)$_POST[$key]);
    }
}

if (!function_exists('aurora_get_query_string')) {
    function aurora_get_query_string($key, $default = '') {
        if (!isset($_GET[$key])) return $default;
        return trim((string)$_GET[$key]);
    }
}

if (!function_exists('aurora_sanitize_textarea')) {
    /**
     * For multi-line input. Trims, caps length, returns '' when empty.
     * Never echoes directly.
     */
    function aurora_sanitize_textarea($raw, $maxLength = 2000) {
        $s = trim((string)$raw);
        if ($s === '') return '';
        if (function_exists('mb_substr')) {
            $s = mb_substr($s, 0, $maxLength);
        } else {
            $s = substr($s, 0, $maxLength);
        }
        return $s;
    }
}