<?php
    /**
     * Aurora Library — Admin: User Actions Endpoint
     *
     * POST only, CSRF protected, admin only. Handles:
     *   - activate      (is_active = 1)
     *   - deactivate    (is_active = 0)
     *   - delete        (soft delete: deleted_at = NOW())
     *                   refuses if any active borrow (status=1) exists
     *   - restore       (clears deleted_at, sets is_active = 1)
     *
     * All actions are transactional. "delete" refuses hard when the user
     * still has active borrowed books (status=1), so no orphan issued
     * rows can ever reference a hidden user.
     */

    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: manage_users.php');
        exit;
    }
    require_once __DIR__ . '/../admin_auth_check.php';

    // CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!aurora_csrf_check($token)) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid security token.'];
        header('Location: manage_users.php');
        exit;
    }

    // Validate
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $_POST['action'] ?? '';
    $allowed = ['activate', 'deactivate', 'delete', 'restore'];
    if (!$userId || !in_array($action, $allowed, true)) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid request.'];
        header('Location: manage_users.php');
        exit;
    }

    $conn = aurora_db();
    mysqli_report(MYSQLI_REPORT_OFF);

    $ok = false;
    $err = '';
    $msg = '';

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, is_active, deleted_at FROM users WHERE id = ? FOR UPDATE'
        );
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res  = mysqli_stmt_get_result($stmt);
        $user = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$user) throw new Exception('User not found.');

        switch ($action) {
            case 'activate':
                if (!empty($user['deleted_at'])) throw new Exception('Restore the deleted user before activating.');
                if ((int)$user['is_active'] === 1) throw new Exception('User is already active.');
                $stmt = mysqli_prepare($conn, 'UPDATE users SET is_active = 1 WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = 'User activated.';
                break;

            case 'deactivate':
                if (!empty($user['deleted_at'])) throw new Exception('Cannot deactivate a deleted user.');
                if ((int)$user['is_active'] === 0) throw new Exception('User is already deactivated.');
                $stmt = mysqli_prepare($conn, 'UPDATE users SET is_active = 0 WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = 'User deactivated.';
                break;

            case 'delete':
                if (!empty($user['deleted_at'])) throw new Exception('User is already deleted.');

                // Block soft-delete when active borrowed books exist.
                $stmt = mysqli_prepare(
                    $conn,
                    'SELECT COUNT(*) AS c FROM issued_books WHERE student_id = ? AND status = 1'
                );
                mysqli_stmt_bind_param($stmt, 'i', $userId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $cnt = $res ? (int)mysqli_fetch_assoc($res)['c'] : 0;
                mysqli_stmt_close($stmt);
                if ($cnt > 0) {
                    throw new Exception("User still has {$cnt} active borrowed book" . ($cnt === 1 ? '' : 's') . '. They must be returned before this user can be deleted.');
                }

                // Soft-delete: hide the row but keep history intact.
                $stmt = mysqli_prepare(
                    $conn,
                    'UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = ? AND deleted_at IS NULL'
                );
                mysqli_stmt_bind_param($stmt, 'i', $userId);
                mysqli_stmt_execute($stmt);
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                if ($affected !== 1) throw new Exception('User could not be deleted.');
                $msg = 'User soft-deleted. Their issue history is preserved.';
                break;

            case 'restore':
                if (empty($user['deleted_at'])) throw new Exception('User is not deleted.');
                $stmt = mysqli_prepare(
                    $conn,
                    'UPDATE users SET deleted_at = NULL, is_active = 1 WHERE id = ?'
                );
                mysqli_stmt_bind_param($stmt, 'i', $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = 'User restored.';
                break;
        }

        mysqli_commit($conn);
        $ok = true;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        // Log the real cause in dev; never echo it to the user.
        aurora_log_error('user_action.' . $action, $e);
        if ($err === '') $err = aurora_user_facing_error();
    }

    $_SESSION['_flash'] = [
        'type' => $ok ? 'success' : 'error',
        'msg'  => $ok ? $msg : ($err ?: 'Action failed.'),
    ];

    // Return to the page that issued the request when possible.
    $back = $_POST['return_to'] ?? 'manage_users.php';
    if (!preg_match('#^(manage_users\.php|view_user\.php(\?id=\d+)?)$#', $back)) {
        $back = 'manage_users.php';
    }
    header('Location: ' . $back);
    exit;