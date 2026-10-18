<?php
    /**
     * Aurora Library — Member: Cancel Pending Request
     */
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: my_requests.php');
        exit;
    }
    require_once __DIR__ . '/../user_auth_check.php';

    $token = $_POST['csrf_token'] ?? '';
    if (!aurora_csrf_check($token)) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid security token.'];
        header('Location: my_requests.php');
        exit;
    }

    $userId = (int)($_SESSION['id'] ?? 0);
    $reqId  = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if (!$reqId || !$userId) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid request.'];
        header('Location: my_requests.php');
        exit;
    }

    $conn = aurora_db();
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE book_requests
            SET status = 'Cancelled',
                admin_response_date = NOW(),
                admin_response_by = ?
          WHERE request_id = ? AND user_id = ? AND status = 'Pending'"
    );
    mysqli_stmt_bind_param($stmt, 'iii', $userId, $reqId, $userId);
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected === 1) {
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Request cancelled.'];
    } else {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Only pending requests can be cancelled.'];
    }

    header('Location: my_requests.php');
    exit;