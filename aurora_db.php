<?php
/**
 * Aurora Library — Database Connection Helper
 * Centralised mysqli connection. All pages should `require_once` this
 * and use the returned $conn rather than re-opening a new connection.
 */

if (!function_exists('aurora_db')) {
    function aurora_db() {
        static $conn = null;
        if ($conn === null) {
            $conn = mysqli_connect('localhost', 'root', '', 'lms');
            if (!$conn) {
                // Log the real error to the dev console, but never echo
                // it to the page — a raw mysqli string can leak schema
                // details. The page should render aurora_user_facing_error().
                if (function_exists('aurora_log_error')) {
                    aurora_log_error('db.connect', 'mysqli_connect_error=' . mysqli_connect_error());
                } else {
                    error_log('[Aurora] db.connect | ' . mysqli_connect_error());
                }
                die(aurora_user_facing_error());
            }
            mysqli_set_charset($conn, 'utf8mb4');
        }
        return $conn;
    }
}

/**
 * Execute a prepared statement and return the mysqli_stmt object.
 * Throws on failure in development; returns false silently otherwise.
 */
if (!function_exists('aurora_query')) {
    function aurora_query($sql, $types = '', $params = []) {
        $conn = aurora_db();
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            if (function_exists('aurora_log_error')) {
                aurora_log_error('sql.prepare', 'mysqli=' . mysqli_error($conn) . ' | sql=' . $sql);
            } else {
                error_log('SQL prepare failed: ' . mysqli_error($conn) . ' | ' . $sql);
            }
            return false;
        }
        if ($types !== '' && !empty($params)) {
            // Spread params as references for bind_param
            $bind_names[] = $types;
            for ($i = 0; $i < count($params); $i++) {
                $bind_name = 'bind' . $i;
                $$bind_name = $params[$i];
                $bind_names[] = &$$bind_name;
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_names);
        }
        if (!mysqli_stmt_execute($stmt)) {
            if (function_exists('aurora_log_error')) {
                aurora_log_error('sql.execute', 'mysqli=' . mysqli_stmt_error($stmt) . ' | sql=' . $sql);
            } else {
                error_log('SQL execute failed: ' . mysqli_stmt_error($stmt) . ' | ' . $sql);
            }
            return false;
        }
        return $stmt;
    }
}

/**
 * Fetch all rows as an associative array.
 * Usage: $rows = aurora_fetch_all('SELECT * FROM users WHERE id = ?', 'i', [5]);
 */
if (!function_exists('aurora_fetch_all')) {
    function aurora_fetch_all($sql, $types = '', $params = []) {
        $stmt = aurora_query($sql, $types, $params);
        if (!$stmt) return [];
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

/**
 * Fetch a single row.
 * Usage: $user = aurora_fetch_one('SELECT * FROM users WHERE id = ?', 'i', [5]);
 */
if (!function_exists('aurora_fetch_one')) {
    function aurora_fetch_one($sql, $types = '', $params = []) {
        $rows = aurora_fetch_all($sql, $types, $params);
        return $rows[0] ?? null;
    }
}

/**
 * Execute a non-result query (INSERT/UPDATE/DELETE) and return affected rows.
 * For INSERT, the new auto_increment id is available via aurora_insert_id().
 */
if (!function_exists('aurora_execute')) {
    function aurora_execute($sql, $types = '', $params = []) {
        $stmt = aurora_query($sql, $types, $params);
        if (!$stmt) return 0;
        return mysqli_stmt_affected_rows($stmt);
    }
}

if (!function_exists('aurora_insert_id')) {
    function aurora_insert_id() {
        return mysqli_insert_id(aurora_db());
    }
}

/**
 * Run $work() inside a mysqli transaction.
 *
 *   - Disables MYSQLI_REPORT so exceptions stay inside the worker.
 *   - Turns mysqli_sql_exception into a normal exception so the worker can
 *     catch one type.
 *   - If $work() returns normally, COMMITs and returns the value.
 *   - If $work() throws, ROLLBACKs and re-throws.
 *
 * Usage:
 *   $id = aurora_transaction(function ($conn) {
 *       $stmt = mysqli_prepare($conn, '...');
 *       mysqli_stmt_execute($stmt);
 *       return mysqli_insert_id($conn);
 *   });
 */
if (!function_exists('aurora_transaction')) {
    function aurora_transaction(callable $work) {
        $conn = aurora_db();
        mysqli_report(MYSQLI_REPORT_OFF);

        mysqli_begin_transaction($conn);
        try {
            $result = $work($conn);
            mysqli_commit($conn);
            return $result;
        } catch (\Throwable $e) {
            @mysqli_rollback($conn);
            throw $e;
        }
    }
}

/**
 * Try $work(); on failure ROLLBACK + flash $redirect with $errorKey.
 * Returns ['ok'=>bool, 'error'=>string] so callers can short-circuit
 * their own logic.
 *
 * Usage:
 *   $r = aurora_with_transaction(function () use ($conn, $bid) {
 *       $stmt = mysqli_prepare($conn, 'UPDATE ...');
 *       mysqli_stmt_execute($stmt);
 *       if (mysqli_stmt_affected_rows($stmt) !== 1) {
 *           throw new RuntimeException('AlreadyStock not affected');
 *       }
 *   });
 *   if (!$r['ok']) { $_SESSION['_flash'] = ['type'=>'error','msg'=>$r['error']]; ... }
 */
if (!function_exists('aurora_with_transaction')) {
    function aurora_with_transaction(callable $work) {
        try {
            aurora_transaction($work);
            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
