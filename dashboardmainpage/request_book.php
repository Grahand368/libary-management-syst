<?php
    /**
     * Aurora Library — Member: Submit Book Request
     *
     * POST only, CSRF protected. Creates a Pending request for the
     * currently signed-in member. Refuses if:
     *   - book doesn't exist / isn't available
     *   - the member already has a Pending request for the same book
     *   - the member already has this book issued (status=1)
     */

    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: books.php');
        exit;
    }
    require_once __DIR__ . '/../user_auth_check.php';

    $token = $_POST['csrf_token'] ?? '';
    if (!aurora_csrf_check($token)) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid security token.'];
        header('Location: books.php');
        exit;
    }

    $userId = (int)($_SESSION['id'] ?? 0);
    if (!$userId) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Sign in required.'];
        header('Location: ../login module/index.php');
        exit;
    }

    $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if (!$bookId) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid book selection.'];
        header('Location: books.php');
        exit;
    }

    $conn = aurora_db();
    mysqli_report(MYSQLI_REPORT_OFF);

    $ok  = false;
    $err = '';

    mysqli_begin_transaction($conn);
    try {
        // 1. Lock the book row.
        $stmt = mysqli_prepare(
            $conn,
            'SELECT book_id, book_name, book_no, book_qty
               FROM books WHERE book_id = ? FOR UPDATE'
        );
        mysqli_stmt_bind_param($stmt, 'i', $bookId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $book = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$book) throw new Exception('Book not found.');
        // Members may request a book even when stock is zero — the admin
        // decides whether to waitlist or decline. The hard guard is only
        // "you already have it issued".

        // 2. Refuse if user already has this book issued.
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM issued_books WHERE student_id = ? AND book_no = ? AND status = 1 LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'is', $userId, $book['book_no']);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $existingIssue = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($existingIssue) throw new Exception('You currently have this book issued.');

        // 3. Refuse duplicate ACTIVE request (Pending OR Approved).
        //    Declined/Cancelled are terminal — a fresh request is allowed.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT request_id, status FROM book_requests
              WHERE user_id = ? AND book_id = ? AND status IN ('Pending','Approved')
              LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $bookId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $existingReq = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($existingReq) {
            $msg = $existingReq['status'] === 'Pending'
                ? 'You already have a pending request for this book.'
                : 'Your previous request for this book was approved — please pick up / return it first.';
            throw new Exception($msg);
        }

        // 4. Insert Pending request.
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO book_requests (user_id, book_id, status)
             VALUES (?, ?, 'Pending')"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $bookId);
        mysqli_stmt_execute($stmt);
        $reqId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        if (!$reqId) throw new Exception('Could not create request.');

        mysqli_commit($conn);
        $ok = true;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        // Log the real cause in dev; show a safe message to the user.
        aurora_log_error('request_book', $e);
        $err = aurora_user_facing_error();
    }

    if ($ok) {
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Book request submitted successfully.'];
    } else {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => $err ?: 'Could not submit request.'];
    }

    header('Location: my_requests.php');
    exit;