<?php
/**
 * Aurora Library — Admin Authentication Check
 *
 * Include at the very top of every protected admin page (before any HTML):
 *
 *     require_once __DIR__ . '/../admin_auth_check.php';
 *
 * Checks (in order):
 *   1. Starts the session with hardened cookie params
 *   2. Sends no-cache headers so Back/Forward cannot serve stale pages
 *   3. Validates the session: role == 'admin', id is set, UA fingerprint matches, not expired
 *   4. If any check fails → destroys the session, clears the cookie,
 *      and redirects to the admin login page
 *
 * This is the PRIMARY defence — it runs on the server for every request,
 * regardless of what the browser does with Back/Forward.
 */

require_once __DIR__ . '/aurora_auth.php';

/* Start session (idempotent, hardened cookies) */
aurora_session_start();

/* Prevent ALL browser caching - redundant with aurora_no_cache_headers but safe */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

/* Validate admin session using centralized guard */
if (!aurora_session_valid() || !aurora_is_admin()) {
    /* Clear everything so Back/Forward cannot reach a cached page */
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
    session_write_close();

    /* Redirect to admin login using absolute path */
    header("Location: /lms/login module/admin_login.php");
    exit();
}
