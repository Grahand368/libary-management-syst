<?php
/**
 * Aurora Library — Session & Authentication Security
 * Centralized session configuration, authentication checks, and logout handling.
 * Must be included at the very top of every protected PHP file, before any output.
 */

// ─── Secure Session Configuration ───
if (session_status() === PHP_SESSION_NONE) {
    // Configure session cookies BEFORE session_start()
    $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    session_set_cookie_params([
        'lifetime' => 0,           // Session cookie (expires on browser close)
        'path'     => '/',         // Available on all paths
        'domain'   => '',          // Current domain
        'secure'   => $secure,     // Secure flag only on HTTPS
        'httponly' => true,        // Prevent XSS access to session cookie
        'samesite' => 'Lax',       // CSRF protection
    ]);
    session_start();
}

/**
 * Regenerate session ID to prevent fixation.
 * Call immediately after successful authentication.
 */
function aurora_session_regenerate(): void {
    session_regenerate_id(true);
}

/**
 * Destroy the current session completely, including the cookie.
 * Call on logout.
 */
function aurora_session_destroy(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Clear session data
        $_SESSION = [];
        // Delete session cookie
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
        // Destroy server-side session
        session_destroy();
    }
}

/**
 * Apply no-cache headers to prevent browser caching of sensitive pages.
 * Must be called before ANY output.
 */
function aurora_no_cache_headers(): void {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

/**
 * Require an authenticated member (user) session.
 * Redirects to member login if not authenticated.
 */
function aurora_require_user(): void {
    aurora_no_cache_headers();
    if (empty($_SESSION['user_id']) || empty($_SESSION['email']) || empty($_SESSION['role']) || $_SESSION['role'] !== 'user') {
        header('Location: /login_module/index.php');
        exit;
    }
}

/**
 * Require an authenticated admin session.
 * Redirects to admin login if not authenticated or not admin.
 */
function aurora_require_admin(): void {
    aurora_no_cache_headers();
    if (empty($_SESSION['user_id']) || empty($_SESSION['email']) || empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: /login_module/admin_login.php');
        exit;
    }
}

/**
 * Get current user ID from session (0 if not authenticated).
 */
function aurora_current_user_id(): int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

/**
 * Get current user role from session ('user', 'admin', or '').
 */
function aurora_current_role(): string {
    return $_SESSION['role'] ?? '';
}

/**
 * Check if current session is authenticated as a user.
 */
function aurora_is_user(): bool {
    return (!empty($_SESSION['user_id']) && !empty($_SESSION['email']) && ($_SESSION['role'] ?? '') === 'user');
}

/**
 * Check if current session is authenticated as an admin.
 */
function aurora_is_admin(): bool {
    return (!empty($_SESSION['user_id']) && !empty($_SESSION['email']) && ($_SESSION['role'] ?? '') === 'admin');
}

/**
 * Check if current session is authenticated (either user or admin).
 */
function aurora_is_authenticated(): bool {
    return aurora_is_user() || aurora_is_admin();
}

/**
 * Log the current user out completely.
 * Destroys session and redirects to appropriate login page.
 */
function aurora_logout(string $redirectTo = '/login_module/index.php'): void {
    aurora_session_destroy();
    header('Location: ' . $redirectTo);
    exit;
}

/**
 * Initialize a new authenticated session for a user.
 * Call after successful password verification.
 */
function aurora_login_user(int $userId, string $name, string $email, string $role = 'user'): void {
    aurora_session_regenerate(); // Prevent session fixation
    $_SESSION['user_id'] = $userId;
    $_SESSION['name']    = $name;
    $_SESSION['email']   = $email;
    $_SESSION['role']    = $role;
    $_SESSION['login_time'] = time();
    // Generate fresh CSRF token
    if (function_exists('aurora_csrf_rotate')) {
        aurora_csrf_rotate();
    }
}

/**
 * Check if session has expired due to inactivity (optional timeout).
 * Default: 2 hours of inactivity.
 */
function aurora_check_session_timeout(int $maxIdleSeconds = 7200): void {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $maxIdleSeconds)) {
        aurora_session_destroy();
        header('Location: /login_module/index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Validate that the current session still corresponds to a valid database record.
 * Optional but recommended for high-security routes.
 */
function aurora_validate_session_against_db(): bool {
    if (!aurora_is_authenticated()) {
        return false;
    }
    $conn = aurora_db();
    $table = ($_SESSION['role'] === 'admin') ? 'admins' : 'users';
    $stmt = $conn->prepare("SELECT id FROM `$table` WHERE id = ? AND email = ? LIMIT 1");
    $stmt->bind_param('is', $_SESSION['user_id'], $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result && $result->num_rows > 0;
}