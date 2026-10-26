<?php
/**
 * Aurora Library — Error & Flash-message Helpers
 *
 * Three concerns live here, kept apart on purpose:
 *
 *   1. Error logging
 *      aurora_log_error($context, $e) records the full exception to PHP's
 *      error_log when AURORA_DEV_MODE is on. In production it stays silent
 *      (so the user never sees a raw mysqli error). Callers should show
 *      only the generic "Something went wrong. Please try again." text.
 *
 *   2. Flash messages
 *      A tiny session-backed channel for one-shot messages that survive
 *      a single redirect (e.g. "Book issued." after a POST -> redirect).
 *
 *      aurora_flash_set('success', 'Book issued.')  - stash
 *      aurora_flash_get()                            - read & clear
 *      aurora_flash_pull()                           - read only (does not clear)
 *
 *   3. Toast rendering
 *      aurora_toast_render() emits a <div data-toast="..."> block that
 *      aurora.js auto-dismisses. Pages call it in their <body> to surface
 *      a flash message (or a hard-coded error) on the next page load.
 */

if (!defined('AURORA_DEV_MODE')) {
    define('AURORA_DEV_MODE', false);
}

/* ------------------------------------------------------------------ */
/*  1. Error logging                                                    */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_log_error')) {
    /**
     * Record a detailed error to PHP's error_log. The full context, file,
     * line, and message are kept for development. In production this
     * function intentionally does nothing.
     *
     * Callers should *also* display a generic message to the user
     * (see aurora_user_facing_error()).
     *
     * @param string                $context  A short tag, e.g. 'issue_book.rollback'
     * @param \Throwable|string     $error    Exception, or a string for simple cases.
     */
    function aurora_log_error($context, $error = null) {
        if (!AURORA_DEV_MODE) return;

        $message = (string)$context;
        if ($error instanceof \Throwable) {
            $message .= ' | ' . get_class($error) . ': ' . $error->getMessage();
            $message .= ' @ ' . $error->getFile() . ':' . $error->getLine();
        } elseif (is_string($error) && $error !== '') {
            $message .= ' | ' . $error;
        }

        error_log('[Aurora] ' . $message);
    }
}

if (!function_exists('aurora_user_facing_error')) {
    /**
     * The single safe string shown to a user when something went wrong
     * behind the scenes. Hard-coded so it can never leak DB internals.
     */
    function aurora_user_facing_error() {
        return 'Something went wrong. Please try again.';
    }
}

if (!function_exists('aurora_safe_handle')) {
    /**
     * Run $work(); on any throwable, log it (dev) and return
     * ['ok' => false, 'error' => '...generic...'].
     * On success, return ['ok' => true, 'value' => $result].
     *
     * Callers should NOT use the underlying exception message in their
     * UI — only the generic error string is safe to echo.
     *
     * @param callable $work
     * @param string   $context  Short tag for the log line.
     * @return array{ok:bool, value:mixed, error?:string}
     */
    function aurora_safe_handle(callable $work, $context = 'aurora') {
        try {
            $value = $work();
            return ['ok' => true, 'value' => $value];
        } catch (\Throwable $e) {
            aurora_log_error($context, $e);
            return ['ok' => false, 'error' => aurora_user_facing_error()];
        }
    }
}

if (!function_exists('aurora_handle_exception')) {
    /**
     * Single-line wrapper that logs a Throwable (dev) and converts it to a
     * safe user-facing string. Use this in any `catch (Throwable $e)` block
     * to avoid leaking raw mysqli text into flash toasts or error pages.
     *
     *   } catch (Throwable $e) {
     *       $err = aurora_handle_exception('context.tag', $e);
     *   }
     *
     * @param string          $context  Short log tag (e.g. 'request_book').
     * @param \Throwable|null $e        The caught exception, if any.
     * @param string|null     $fallback Optional safe message to return instead of
     *                                   the generic one (e.g. 'Book not found.').
     * @return string  Always a safe, user-displayable string.
     */
    function aurora_handle_exception($context, $e = null, $fallback = null) {
        if ($e instanceof \Throwable) {
            aurora_log_error($context, $e);
        } elseif (is_string($e) && $e !== '') {
            aurora_log_error($context, $e);
        }
        return $fallback !== null ? $fallback : aurora_user_facing_error();
    }
}

/* ------------------------------------------------------------------ */
/*  2. Flash messages (session-backed)                                  */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_flash_key')) {
    function aurora_flash_key() { return '_aurora_flash'; }
}

if (!function_exists('aurora_flash_normalize_type')) {
    /**
     * Whitelist allowed toast types. Anything else collapses to 'info'.
     */
    function aurora_flash_normalize_type($type) {
        $t = strtolower(trim((string)$type));
        return in_array($t, ['success', 'error', 'info', 'warning'], true) ? $t : 'info';
    }
}

if (!function_exists('aurora_flash_set')) {
    /**
     * Stash a one-shot message. Survives exactly one aurora_flash_get() call.
     *
     * @param string $type  'success' | 'error' | 'info' | 'warning'
     * @param string $msg   Plain-text message (callers may include HTML they trust).
     */
    function aurora_flash_set($type, $msg) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            aurora_session_start();
        }
        $_SESSION[aurora_flash_key()] = [
            'type' => aurora_flash_normalize_type($type),
            'msg'  => (string)$msg,
        ];
    }
}

if (!function_exists('aurora_flash_pull')) {
    /**
     * Read the current flash message WITHOUT removing it.
     * Returns null when there is nothing to show.
     */
    function aurora_flash_pull() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            aurora_session_start();
        }
        return $_SESSION[aurora_flash_key()] ?? null;
    }
}

if (!function_exists('aurora_flash_get')) {
    /**
     * Read and remove the current flash message. Use this when rendering
     * exactly one toast at the top of the page.
     */
    function aurora_flash_get() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            aurora_session_start();
        }
        $f = $_SESSION[aurora_flash_key()] ?? null;
        unset($_SESSION[aurora_flash_key()]);
        return $f;
    }
}

if (!function_exists('aurora_flash_clear')) {
    function aurora_flash_clear() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            aurora_session_start();
        }
        unset($_SESSION[aurora_flash_key()]);
    }
}

/* ------------------------------------------------------------------ */
/*  3. Toast rendering                                                  */
/*                                                                     */
/*  aurora.js already auto-dismisses any [data-toast] block on the     */
/*  page (initInlineToasts). We just emit the markup. Two variants:    */
/*                                                                     */
/*    aurora_toast_render()        - renders the current flash, then   */
/*                                   clears it. Call once near <body>. */
/*    aurora_toast_inline($type,   - render a specific message now     */
/*      $msg)                        (does not touch the flash).        */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_toast_label')) {
    function aurora_toast_label($type) {
        switch (aurora_flash_normalize_type($type)) {
            case 'success': return '✓';
            case 'error':   return '✕';
            case 'warning': return '!';
            default:        return 'ⓘ';
        }
    }
}

if (!function_exists('aurora_toast_style')) {
    /**
     * Inline styles for the toast. We avoid touching aurora.css so the
     * toast works on any page — even those that haven't loaded the
     * stylesheet yet.
     */
    function aurora_toast_style($type) {
        switch (aurora_flash_normalize_type($type)) {
            case 'success':
                return 'background:rgba(15,157,88,0.10);color:#0f7a44;';
            case 'error':
                return 'color:var(--crimson);text-align:center;margin-bottom:16px;font-weight:500;'
                     . 'padding:10px;background:rgba(155,28,28,0.08);border-radius:10px;';
            case 'warning':
                return 'background:rgba(217,119,6,0.10);color:#8a4a00;';
            default:
                return 'background:rgba(37,99,235,0.08);color:#1d3a8a;';
        }
    }
}

if (!function_exists('aurora_toast_inline')) {
    /**
     * Render a single toast for a given type+message. Returns the HTML
     * string; call inside a <?php echo ?> in your template.
     */
    function aurora_toast_inline($type, $msg, $ms = 4500) {
        $type = aurora_flash_normalize_type($type);
        $msg  = (string)$msg;
        if ($msg === '') return '';
        $style = aurora_toast_style($type);
        $label = aurora_toast_label($type);
        $safe  = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
        $ms    = max(1000, (int)$ms);
        return '<div data-toast="' . $type . '" data-toast-time="' . $ms . '"'
             . ' class="aurora-flash aurora-flash--' . $type . '"'
             . ' role="' . ($type === 'error' ? 'alert' : 'status') . '"'
             . ' style="' . $style . '">'
             . $label . ' ' . $safe
             . '</div>';
    }
}

if (!function_exists('aurora_toast_render')) {
    /**
     * Render (and consume) the current session flash as a toast. Place
     * this call right inside <body> on every page that wants to show
     * a post-redirect message.
     *
     * Pass a $fallback type to control the default colour when no flash
     * is set (typically 'info' or 'success' for landing pages).
     */
    function aurora_toast_render($fallbackType = null) {
        $flash = aurora_flash_get();
        if (!$flash) {
            if ($fallbackType === null) return '';
            return aurora_toast_inline($fallbackType, '');
        }
        return aurora_toast_inline($flash['type'], $flash['msg']);
    }
}

if (!function_exists('aurora_toast_bootstrap')) {
    /**
     * One-line boilerplate for legacy pages. Echoes the standard toast
     * host markup (data-toast-host) plus aurora.js. Use when a page
     * is *not* already loading the Aurora assets.
     */
    function aurora_toast_bootstrap($cssHref = null, $jsHref = null) {
        $cssHref = $cssHref ?: '/static/css/aurora.css';
        $jsHref  = $jsHref  ?: '/static/js/aurora.js';
        return '<div data-toast-host></div>' . "\n"
             . '<link rel="stylesheet" href="' . htmlspecialchars($cssHref, ENT_QUOTES) . '">' . "\n"
             . '<script src="' . htmlspecialchars($jsHref, ENT_QUOTES) . '"></script>';
    }
}
