<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_validation.php';
    require_once __DIR__ . '/../user_auth_check.php';

    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: change_password.php');
        exit;
    }

    $oldPw = isset($_POST['old_password']) ? (string)$_POST['old_password'] : '';
    $newPw = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
    $conf  = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

    if ($oldPw === '') $errors[] = 'Please enter your current password.';
    if ($newPw !== $conf) $errors[] = 'New password and confirmation do not match.';
    $strong = aurora_validate_password_strength($newPw);
    if ($strong === null) {
        $errors[] = 'New password must be at least 8 characters with upper, lower, and a number.';
    }

    if (empty($errors)) {
        $me = aurora_fetch_one(
            'SELECT id, password FROM users WHERE id = ? LIMIT 1',
            'i',
            [(int)($_SESSION['id'] ?? 0)]
        );
        if (!$me || !password_verify($oldPw, (string)$me['password'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $hash = password_hash($strong, PASSWORD_DEFAULT);
            $r = aurora_with_transaction(function ($conn) use ($me, $hash) {
                $stmt = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'si', $hash, $me['id']);
                mysqli_stmt_execute($stmt);
                $ok = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                if ($ok < 0) throw new RuntimeException('Update failed.');
            });
            if ($r['ok']) {
                $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Password updated.'];
                header('Location: user-dashboard.php');
                exit;
            }
            $errors[] = $r['error'] ?: 'Could not update password.';
        }
    }

    $_SESSION['_flash'] = ['type' => 'error', 'msg' => implode(' ', $errors)];
    header('Location: change_password.php');
    exit;