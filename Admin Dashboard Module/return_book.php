<?php
    /**
     * Aurora Library — Admin Return Book Handler
     * Same logic as the member copy, without the ownership check.
     */
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: view_issued_book.php');
        exit;
    }
    require_once __DIR__ . '/../admin_auth_check.php';

    $token = $_POST['csrf_token'] ?? '';
    if (!aurora_csrf_check($token)) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid security token. Please retry.'];
        header('Location: view_issued_book.php');
        exit;
    }

    $issueId = filter_var($_POST['issue_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if (!$issueId) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid issue reference.'];
        header('Location: view_issued_book.php');
        exit;
    }

    $returnDate = null;
    if (!empty($_POST['return_date'])) {
        $ts = strtotime($_POST['return_date']);
        if ($ts) $returnDate = date('Y-m-d', $ts);
    }

    $conn = aurora_db();
    mysqli_report(MYSQLI_REPORT_OFF);

    $ok  = false;
    $err = '';
    $fine = 0.0;
    $daysLate = 0;

    try {
        $result = aurora_transaction(function ($conn) use ($issueId, $returnDate) {
            // Lock the issue row.
            $stmt = mysqli_prepare(
                $conn,
                'SELECT id, book_no, status, issue_date
                   FROM issued_books WHERE id = ? FOR UPDATE'
            );
            if (!$stmt) throw new RuntimeException('Could not read issue record.');
            mysqli_stmt_bind_param($stmt, 'i', $issueId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $issue = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (!$issue) throw new RuntimeException('Issue record not found.');
            if ((int)$issue['status'] !== 1) throw new RuntimeException('This book has already been returned.');

            $bookNo = (string)$issue['book_no'];

            // Lock the book row.
            $stmt = mysqli_prepare($conn, 'SELECT book_qty FROM books WHERE book_no = ? FOR UPDATE');
            mysqli_stmt_bind_param($stmt, 's', $bookNo);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $bookRow = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (!$bookRow) throw new RuntimeException('Book record could not be located.');

            $effective = $returnDate ?: date('Y-m-d');
            $calc      = aurora_fine_compute($issue['issue_date'], $effective);
            $dueDate   = $calc['due_date'];
            $daysLate  = $calc['days_late'];
            $fine      = $calc['fine'];
            $newStatus = $fine > 0 ? 'Unpaid' : 'No Fine';

            // Flip status (guarded by status=1).
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE issued_books
                    SET status = 0,
                        return_date = ?,
                        due_date = COALESCE(due_date, ?),
                        fine_amount = ?,
                        fine_status = ?
              WHERE id = ? AND status = 1'
            );
            if (!$stmt) throw new RuntimeException('Could not update issue.');
            mysqli_stmt_bind_param($stmt, 'ssdsi', $effective, $dueDate, $fine, $newStatus, $issueId);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($affected !== 1) throw new RuntimeException('This book has already been returned.');

            if ($bookNo !== '') {
                $stmt = mysqli_prepare(
                    $conn,
                    'UPDATE books SET book_qty = book_qty + 1
                      WHERE book_no = ? AND book_qty IS NOT NULL'
                );
                if (!$stmt) throw new RuntimeException('Could not update stock.');
                mysqli_stmt_bind_param($stmt, 's', $bookNo);
                mysqli_stmt_execute($stmt);
                $stockAffected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                if ($stockAffected !== 1) {
                    throw new RuntimeException('Stock update did not affect a row.');
                }
            }

            return ['days_late' => $daysLate, 'fine' => $fine];
        });
        $daysLate = $result['days_late'];
        $fine     = $result['fine'];
        $ok = true;
    } catch (Throwable $e) {
        // Log the real cause in dev; show a safe message to the user.
        aurora_log_error('admin_return_book', $e);
        $err = aurora_user_facing_error();
    }

    if ($ok) {
        $msg = 'Book returned successfully.';
        if ($fine > 0) {
            $msg .= ' Fine: Rs. ' . number_format($fine, 2);
            $msg .= " ({$daysLate} day" . ($daysLate === 1 ? '' : 's') . ' overdue)';
            $msg .= ' — Status: Unpaid.';
        } else {
            $msg .= ' No fine.';
        }
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => $msg];
    } else {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => $err ?: 'Unable to return book.'];
    }

    header('Location: view_issued_book.php');
    exit;