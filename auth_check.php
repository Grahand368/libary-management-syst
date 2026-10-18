<?php
/**
 * Aurora Library — Universal Authentication Check
 *
 * Include this file at the TOP of every protected page (admin or user):
 *
 *     require_once __DIR__ . '/auth_check.php';
 *     // optionally: require_once __DIR__ . '/../admin_auth_check.php';   // before the require
 *
 * What it does (in order):
 *   1. Starts the session with hardened cookie params (aurora_session_start)
 *   2. Sends no-cache headers so the browser can NEVER serve a stale
 *      authenticated page via Back/Forward navigation or bfcache
 *   3. Validates the session: existence + UA fingerprint match
 *   4. Checks the required role (admin/user) against $_SESSION['role']
 *   5. If anything fails → clears the session, deletes the cookie,
 *      and redirects to the appropriate login page
 *
 * This is the single entry point for authentication. It does NOT rely
 * on JavaScript — every request is validated server-side.
 */

require_once __DIR__ . '/aurora_auth.php';
require_once __DIR__ . '/aurora_db.php';
require_once __DIR__ . '/aurora_validation.php';
require_once __DIR__ . '/aurora_error.php';

/* ------------------------------------------------------------------ *
 * 1. Start the session (idempotent, hardened cookies)                 *
 * ------------------------------------------------------------------ */
aurora_session_start();

/* ------------------------------------------------------------------ *
 * 2. Prevent ALL caching so Back/Forward can't serve stale pages      *
 * ------------------------------------------------------------------ */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

/* ------------------------------------------------------------------ *
 * 3. Validate session integrity                                       *
 * ------------------------------------------------------------------ */
$sessionOk = isset($_SESSION['role'])
    && isset($_SESSION['id'])
    && aurora_session_valid();   // checks UA fingerprint via hash_equals

if (!$sessionOk) {
    /* 3a. Tear down whatever partial session exists so the browser
     *     can't reuse it on the next back/forward hit.                  */
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    session_write_close();
}

/* ------------------------------------------------------------------ *
 * 4. Enforce authentication                                           *
 * ------------------------------------------------------------------ */
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'user'], true)) {
    header('Location: login module/' . ((isset($required_role) && $required_role === 'admin')
        ? 'admin_login.php'
        : 'index.php'));
    exit;
}

/* ------------------------------------------------------------------ *
 * 5. (Optional) enforce a specific role                              *
 * ------------------------------------------------------------------ */
if (isset($required_role)) {
    if ($_SESSION['role'] !== $required_role) {
        if ($_SESSION['role'] === 'user' && $required_role === 'admin') {
            /* Logged-in members hitting admin pages go to their own dashboard */
            header('Location: dashboardmainpage/user-dashboard.php');
        } else {
            /* Admins hitting user-only pages, or any mismatch, go to login */
            header('Location: login module/index.php');
        }
        exit;
    }
}
