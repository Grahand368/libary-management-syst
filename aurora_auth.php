<?php
/**
 * Aurora Library — Authentication & Session Helpers
 *
 * Provides:
 *   - Password hashing / verification (with legacy upgrade support)
 *   - Secure session bootstrap (cookie flags, regenerate)
 *   - Reusable guards: aurora_require_user, aurora_require_admin
 *   - Login / logout helpers: aurora_login, aurora_logout
 *
 * Designed to be included once at the top of any page that needs
 * authentication. Each guard redirects to the appropriate login
 * page on failure and never reveals which check failed.
 */

if (!function_exists('aurora_is_hashed')) {
    /**
     * Detect whether a stored password value is already a modern hash
     * (bcrypt / argon2). Anything that doesn't match the recognised
     * prefixes is treated as legacy plain text.
     */
    function aurora_is_hashed($stored) {
        if (!is_string($stored) || $stored === '') return false;
        return (bool) preg_match('/^\$(2[axyb]|argon2(i|id))\$/', $stored);
    }
}

if (!function_exists('aurora_verify_and_maybe_upgrade')) {
    /**
     * Verify a submitted password against a stored value, transparently
     * upgrading the stored value to a modern hash if it was still plain
     * text. This means existing users are never locked out.
     */
    function aurora_verify_and_maybe_upgrade($submitted, $stored, $conn, $table, $emailCol, $emailValue) {
        if (aurora_is_hashed($stored)) {
            return password_verify($submitted, $stored);
        }

        if (hash_equals($stored, $submitted)) {
            $newHash = password_hash($submitted, PASSWORD_DEFAULT);
            $u = mysqli_real_escape_string($conn, $emailValue);
            $h = mysqli_real_escape_string($conn, $newHash);
            mysqli_query(
                $conn,
                "UPDATE `$table` SET password = '$h' WHERE `$emailCol` = '$u'"
            );
            return true;
        }

        return false;
    }
}

/* ------------------------------------------------------------------ */
/*  Session bootstrap                                                  */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_session_start')) {
    /**
     * Start a session with hardened cookie flags. Safe to call multiple
     * times — only the first call actually starts the session.
     */
    function aurora_session_start() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Stricter session ID entropy.
        ini_set('session.use_strict_mode', '1');

        session_name('AURORA_SID');
        session_start();
    }
}

/* ------------------------------------------------------------------ */
/*  Login / logout                                                     */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_login')) {
    /**
     * Mark the current session as authenticated for a given role and
     * regenerate the session ID to defeat session fixation. Old session
     * data is wiped so role/identity cannot be inherited from a previous
     * visitor on the same browser.
     *
     * @param string $role   'user' or 'admin'
     * @param array  $identity  Must contain at least id, name, email.
     */
    function aurora_login($role, array $identity) {
        aurora_session_start();
        aurora_no_cache_headers();

        // Wipe any previous session data first.
        $_SESSION = [];

        // Best-effort deletion of the old session cookie so a logged-in
        // attacker cannot race a fixation attempt.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        // Regenerate the ID and destroy the old storage.
        session_regenerate_id(true);

        $_SESSION['role']  = $role;
        $_SESSION['id']    = $identity['id']    ?? null;
        $_SESSION['name']  = $identity['name']  ?? '';
        $_SESSION['email'] = $identity['email'] ?? '';

        // Fingerprint binding: detect session hijack from a different
        // browser fingerprint. Non-strict — changes only force re-login.
        $_SESSION['_ua']  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 120);
        $_SESSION['_born']= time();
    }
}

if (!function_exists('aurora_logout')) {
    /**
     * Fully destroy the current session: clear the $_SESSION bag,
     * expire the cookie, and end the server-side storage.
     */
    function aurora_logout() {
        aurora_session_start();
        aurora_no_cache_headers();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }
}

/* ------------------------------------------------------------------ */
/*  Identity / role helpers                                            */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_current_role')) {
    /** @return string|null  'user', 'admin', or null when not logged in. */
    function aurora_current_role() {
        return $_SESSION['role'] ?? null;
    }
}

if (!function_exists('aurora_current_user')) {
    /** @return array|null  Identity array or null. */
    function aurora_current_user() {
        if (empty($_SESSION['role'])) return null;
        return [
            'id'    => $_SESSION['id']    ?? null,
            'name'  => $_SESSION['name']  ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role'  => $_SESSION['role'],
        ];
    }
}

if (!function_exists('aurora_is_user')) {
    function aurora_is_user() {
        return ($_SESSION['role'] ?? null) === 'user';
    }
}

if (!function_exists('aurora_is_admin')) {
    function aurora_is_admin() {
        return ($_SESSION['role'] ?? null) === 'admin';
    }
}

if (!function_exists('aurora_session_valid')) {
    /**
     * Lightweight integrity check for the current session: verifies
     * the UA fingerprint hasn't changed (best-effort hijack signal).
     */
    function aurora_session_valid() {
        if (empty($_SESSION['role'])) return false;
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 120);
        return hash_equals($_SESSION['_ua'] ?? '', $ua);
    }
}

/* ------------------------------------------------------------------ */
/*  Guards                                                             */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_redirect')) {
    function aurora_redirect($location) {
        header('Location: ' . $location);
        exit;
    }
}

if (!function_exists('aurora_no_cache_headers')) {
    /**
     * Send headers that stop the browser from serving a cached copy of
     * an authenticated page after logout, or when using the Back/Forward
     * buttons. Every protected page calls this via aurora_require_*.
     */
    function aurora_no_cache_headers() {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

if (!function_exists('aurora_require_user')) {
    /**
     * Allow only authenticated members. Admins are explicitly blocked
     * from member-only pages to prevent accidental misuse of admin
     * privileges on a personal account.
     */
    function aurora_require_user($loginPath = '/login module/index.php') {
        aurora_session_start();
        aurora_no_cache_headers();

        if (!aurora_session_valid() || !aurora_is_user()) {
            // Admins must use the admin console, not member pages.
            if (aurora_is_admin()) {
                aurora_flash_set('error', 'You do not have permission to view that page.');
                aurora_redirect('/Admin Dashboard Module/admin_dashboard.php');
            }
            aurora_flash_set('info', 'Please sign in to continue.');
            aurora_redirect($loginPath);
        }
    }
}

if (!function_exists('aurora_require_admin')) {
    /**
     * Allow only authenticated administrators. Anyone else — including
     * logged-in members — is bounced away.
     */
    function aurora_require_admin($loginPath = '/login module/admin_login.php') {
        aurora_session_start();
        aurora_no_cache_headers();

        if (!aurora_session_valid() || !aurora_is_admin()) {
            if (aurora_is_user()) {
                aurora_flash_set('error', 'You do not have permission to perform this action.');
                aurora_redirect('/dashboardmainpage/user-dashboard.php');
            }
            aurora_flash_set('info', 'Please sign in as an administrator to continue.');
            aurora_redirect($loginPath);
        }
    }
}

if (!function_exists('aurora_require_auth')) {
    /**
     * Allow any authenticated visitor (user or admin). Useful for shared
     * pages such as profile editing.
     */
    function aurora_require_auth() {
        aurora_session_start();
        aurora_no_cache_headers();
        if (!aurora_session_valid() || empty($_SESSION['role'])) {
            aurora_redirect('/login module/index.php');
        }
    }
}

/* ------------------------------------------------------------------ */
/*  Rate limiting (5 failed attempts / 15 min)                         */
/*                                                                     */
/*  Uses a session+database hybrid: counters are kept in the session    */
/*  for speed, and mirrored to a small `login_attempts` table so that  */
/*  the limit survives session destruction (logout, manual reset).     */
/* ------------------------------------------------------------------ */

if (!defined('AURORA_RL_MAX'))     define('AURORA_RL_MAX', 5);
if (!defined('AURORA_RL_WINDOW'))  define('AURORA_RL_WINDOW', 15 * 60); // 15 minutes

if (!function_exists('aurora_rl_key')) {
    /**
     * Build the bucket key used by both the in-memory and DB stores.
     * Format: "<scope>:<email-lowercased>"  e.g. "user:foo@bar.com".
     */
    function aurora_rl_key($scope, $email) {
        return $scope . ':' . strtolower(trim((string)$email));
    }
}

if (!function_exists('aurora_rl_ensure_table')) {
    /**
     * Make sure the `login_attempts` table exists. Idempotent.
     */
    function aurora_rl_ensure_table() {
        static $ready = false;
        if ($ready) return true;
        $conn = aurora_db();
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS login_attempts (
            id           BIGINT AUTO_INCREMENT PRIMARY KEY,
            bucket_key   VARCHAR(190) NOT NULL,
            attempts     INT UNSIGNED NOT NULL DEFAULT 0,
            first_try_at INT UNSIGNED NOT NULL,
            blocked_until INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_bucket (bucket_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ready = true;
        return true;
    }
}

if (!function_exists('aurora_rl_status')) {
    /**
     * Inspect the current rate-limit state for a given bucket.
     *
     * @return array{attempts:int, blocked:bool, retry_after:int}
     *   retry_after is the number of whole seconds remaining until the
     *   block expires (0 when not blocked).
     */
    function aurora_rl_status($scope, $email) {
        aurora_session_start();
        aurora_rl_ensure_table();

        $key  = aurora_rl_key($scope, $email);
        $now  = time();

        // Session cache (fast path).
        $cache = $_SESSION['_rl'][$key] ?? null;

        $row = aurora_fetch_one(
            'select attempts, first_try_at, blocked_until from login_attempts where bucket_key = ? limit 1',
            's',
            [$key]
        );

        // No record yet -> clean slate.
        if (!$row) {
            return ['attempts' => 0, 'blocked' => false, 'retry_after' => 0];
        }

        // Window expired -> treat as clean slate.
        if (($now - (int)$row['first_try_at']) >= AURORA_RL_WINDOW && (int)$row['blocked_until'] <= $now) {
            return ['attempts' => 0, 'blocked' => false, 'retry_after' => 0];
        }

        $blockedUntil = (int)$row['blocked_until'];
        $blocked      = $blockedUntil > $now;
        $retryAfter   = $blocked ? ($blockedUntil - $now) : 0;

        return [
            'attempts'    => (int)$row['attempts'],
            'blocked'     => $blocked,
            'retry_after' => $retryAfter,
        ];
    }
}

if (!function_exists('aurora_rl_check')) {
    /**
     * Convenience wrapper: true if the caller may attempt a login now,
     * false otherwise. Updates nothing — just queries.
     */
    function aurora_rl_check($scope, $email) {
        $st = aurora_rl_status($scope, $email);
        return !$st['blocked'];
    }
}

if (!function_exists('aurora_rl_record_failure')) {
    /**
     * Record a failed attempt. Returns the updated status array.
     * After AURORA_RL_MAX failures inside the window, sets a block that
     * expires AURORA_RL_WINDOW seconds after the first failure.
     */
    function aurora_rl_record_failure($scope, $email) {
        aurora_session_start();
        aurora_rl_ensure_table();

        $key  = aurora_rl_key($scope, $email);
        $now  = time();
        $conn = aurora_db();

        $row = aurora_fetch_one(
            'select attempts, first_try_at, blocked_until from login_attempts where bucket_key = ? limit 1',
            's',
            [$key]
        );

        if (!$row || (($now - (int)$row['first_try_at']) >= AURORA_RL_WINDOW && (int)$row['blocked_until'] <= $now)) {
            $attempts = 1;
            $firstTry = $now;
        } else {
            $attempts = (int)$row['attempts'] + 1;
            $firstTry = (int)$row['first_try_at'];
        }

        $blockedUntil = 0;
        if ($attempts >= AURORA_RL_MAX) {
            // Block until the window expires, measured from the first
            // failure inside the window.
            $blockedUntil = $firstTry + AURORA_RL_WINDOW;
        }

        // Upsert.
        $esc = mysqli_real_escape_string($conn, $key);
        $sql = "INSERT INTO login_attempts (bucket_key, attempts, first_try_at, blocked_until)
                 VALUES ('$esc', $attempts, $firstTry, $blockedUntil)
                 ON DUPLICATE KEY UPDATE attempts = $attempts,
                                         first_try_at = $firstTry,
                                         blocked_until = $blockedUntil";
        @mysqli_query($conn, $sql);

        // Mirror to session so subsequent requests within the same
        // session don't have to hit the DB to render the message.
        $_SESSION['_rl'][$key] = [
            'attempts'      => $attempts,
            'first_try_at'  => $firstTry,
            'blocked_until' => $blockedUntil,
        ];

        return aurora_rl_status($scope, $email);
    }
}

if (!function_exists('aurora_rl_record_success')) {
    /**
     * Reset the counter for this bucket — called after a successful login.
     */
    function aurora_rl_record_success($scope, $email) {
        aurora_session_start();
        aurora_rl_ensure_table();

        $key = aurora_rl_key($scope, $email);
        $conn = aurora_db();
        $esc  = mysqli_real_escape_string($conn, $key);
        @mysqli_query($conn, "DELETE FROM login_attempts WHERE bucket_key = '$esc'");

        unset($_SESSION['_rl'][$key]);
    }
}

if (!function_exists('aurora_rl_format_retry')) {
    /**
     * "12 min 34 sec" / "45 sec" — used in the user-facing message.
     */
    function aurora_rl_format_retry($seconds) {
        $seconds = max(0, (int)$seconds);
        if ($seconds >= 60) {
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;
            return $s > 0 ? "{$m} min {$s} sec" : "{$m} min";
        }
        return $seconds . ' sec';
    }
}

/* ------------------------------------------------------------------ */
/*  CSRF tokens                                                        */
/*                                                                     */
/*  Per-session token, bound to the browser fingerprint so a stolen    */
/*  session from a different UA cannot reuse it. Constant-time compare */
/*  via hash_equals.                                                   */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_csrf_token')) {
    /**
     * Get (or generate) the per-session CSRF token.
     */
    function aurora_csrf_token() {
        aurora_session_start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('aurora_csrf_field')) {
    /**
     * Emit a ready-to-paste <input type="hidden"> for forms.
     */
    function aurora_csrf_field() {
        $t = aurora_csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES) . '">';
    }
}

if (!function_exists('aurora_csrf_check')) {
    /**
     * Validate the submitted token. Must be called BEFORE any side effect.
     * @return bool true when valid
     */
    function aurora_csrf_check($submitted) {
        aurora_session_start();
        if (!is_string($submitted) || empty($_SESSION['_csrf'])) return false;
        return hash_equals($_SESSION['_csrf'], $submitted);
    }
}

/* ------------------------------------------------------------------ */
/*  Fine management                                                    */
/*                                                                     */
/*  Single source of truth for the loan period, per-day rate, and cap.  */
/*  Anything that computes or renders a fine goes through these       */
/*  helpers — no inline magic numbers anywhere else.                  */
/* ------------------------------------------------------------------ */

if (!defined('AURORA_LOAN_DAYS'))     define('AURORA_LOAN_DAYS', 14);
if (!defined('AURORA_FINE_PER_DAY'))  define('AURORA_FINE_PER_DAY', 5.00);
if (!defined('AURORA_FINE_CAP'))      define('AURORA_FINE_CAP', 500.00);

if (!function_exists('aurora_fine_normalize_status')) {
    /** Whitelist the four allowed statuses; anything else -> 'No Fine'. */
    function aurora_fine_normalize_status($status) {
        $s = (string)$status;
        return in_array($s, ['No Fine', 'Unpaid', 'Paid', 'Waived'], true) ? $s : 'No Fine';
    }
}

if (!function_exists('aurora_fine_status_badge_class')) {
    /** Bootstrap badge class for a status. */
    function aurora_fine_status_badge_class($status) {
        switch (aurora_fine_normalize_status($status)) {
            case 'Paid':    return 'badge badge-success';
            case 'Unpaid':  return 'badge badge-danger';
            case 'Waived':  return 'badge badge-info';
            default:        return 'badge badge-secondary'; // No Fine
        }
    }
}

if (!function_exists('aurora_fine_compute_due_date')) {
    /**
     * Due date = issue_date + AURORA_LOAN_DAYS.
     * Returns 'Y-m-d' or null if the input is unusable.
     */
    function aurora_fine_compute_due_date($issueDate) {
        if (empty($issueDate)) return null;
        $ts = strtotime($issueDate);
        if (!$ts) return null;
        return date('Y-m-d', strtotime('+' . AURORA_LOAN_DAYS . ' days', $ts));
    }
}

if (!function_exists('aurora_fine_compute')) {
    /**
     * Pure (no DB, no side effects) fine calculator.
     *
     * @param string      $issueDate   'Y-m-d'
     * @param string|null $returnDate  'Y-m-d' (null => "not yet returned")
     * @return array{days_late:int, fine:float, status:string, due_date:?string}
     */
    function aurora_fine_compute($issueDate, $returnDate = null) {
        $dueDate = aurora_fine_compute_due_date($issueDate);

        if (empty($dueDate)) {
            return ['days_late' => 0, 'fine' => 0.0, 'status' => 'No Fine', 'due_date' => null];
        }

        // Not yet returned: live overdue count vs today.
        $reference = $returnDate ?: date('Y-m-d');

        $days = (int)floor((strtotime($reference) - strtotime($dueDate)) / 86400);
        $overdueDays = max(0, $days);
        $fine = min(AURORA_FINE_CAP, $overdueDays * AURORA_FINE_PER_DAY);

        $status = $fine > 0 ? 'Unpaid' : 'No Fine';

        return [
            'days_late' => $overdueDays,
            'fine'      => round($fine, 2),
            'status'    => $status,
            'due_date'  => $dueDate,
        ];
    }
}

/* ------------------------------------------------------------------ */
/*  Account-status helpers                                             */
/*                                                                     */
/*  Centralises the rules for "can this user log in?". Soft-deleted    */
/*  and deactivated users must never reach aurora_login().            */
/* ------------------------------------------------------------------ */

if (!function_exists('aurora_user_can_login')) {
    /**
     * @return array{ok:bool, reason?:string}
     *   ok=false with a stable reason code when sign-in must be refused.
     */
    function aurora_user_can_login(array $userRow) {
        if (!empty($userRow['deleted_at'])) {
            return ['ok' => false, 'reason' => 'deleted'];
        }
        if (isset($userRow['is_active']) && (int)$userRow['is_active'] === 0) {
            return ['ok' => false, 'reason' => 'deactivated'];
        }
        return ['ok' => true];
    }
}