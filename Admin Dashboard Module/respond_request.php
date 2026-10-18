<?php
    /**
     * Aurora Library — Admin: Respond to a Book Request
     *
     * POST only, CSRF protected, admin only.
     *
     * Actions:
     *   approve — within one mysqli transaction, with row locks:
     *              1.  Lock the request row (must be Pending)
     *              2.  Lock the book row
     *              3.  Lock the user row (must be active + not deleted)
     *              4.  Verify book_qty > 0 (no negative stock)
     *              5.  Verify user does not already have this book issued
     *              6.  Verify user is below the borrowing limit
     *              7.  Verify no duplicate Active request for the same
     *                  (user, book) pair (handled by the unique index on
     *                  Pending, but double-check here for the issued state)
     *              8.  INSERT into issued_books (status=1, due_date)
     *              9.  UPDATE books SET book_qty = book_qty - 1
     *                  (guarded with book_qty > 0 — never goes negative)
     *              10. UPDATE request -> status='Approved', due_date,
     *                  response_date, response_by, response_note
     *              11. COMMIT
     *
     *   reject — flip status to 'Declined' (still must be Pending).
     *
     * Any failure -> ROLLBACK + flash message back on manage_requests.php.
     */

    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';

    if (!defined('AURORA_BORROW_LIMIT')) define('AURORA_BORROW_LIMIT', 3);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: manage_requests.php');
        exit;
    }
    require_once __DIR__ . '/../admin_auth_check.php';

    // ── CSRF ──────────────────────────────────────────────────────────
    $token = $_POST['csrf_token'] ?? '';
    if (!aurora_csrf_check($token)) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid security token.'];
        header('Location: manage_requests.php');
        exit;
    }

    // ── Validate inputs ───────────────────────────────────────────────
    $reqId  = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $_POST['action'] ?? '';
    $note   = trim((string)($_POST['note'] ?? ''));

    if (!$reqId || !in_array($action, ['approve', 'reject'], true)) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid request.'];
        header('Location: manage_requests.php');
        exit;
    }

    $adminId = (int)($_SESSION['id'] ?? 0);
    $note    = mb_substr($note, 0, 500);

    $conn = aurora_db();

    $ok  = false;
    $err = '';
    $dueDate = null;

    try {
        // APPROVE PATH ─────────────────────────────────────────────────
        //   1. Lock the request row (must be Pending)
        //   2. Lock the book row
        //   3. Lock the user row
        //   4. Verify book_qty > 0 (no negative stock)
        //   5. Verify user has no duplicate active loan
        //   6. Verify user is below the borrowing limit
        //   7. Verify no duplicate Approved request for the same book
        //   8. INSERT issued_books (status=1, due_date)
        //   9. UPDATE books SET book_qty = book_qty - 1 (guarded)
        //  10. UPDATE book_requests SET status='Approved'
        //  11. COMMIT
        $dueDate = aurora_transaction(function ($conn) use ($reqId, $adminId, $note, $action) {
            // 1. Lock the request row.
            $stmt = mysqli_prepare(
                $conn,
                'SELECT request_id, user_id, book_id, status
                   FROM book_requests WHERE request_id = ? FOR UPDATE'
            );
            mysqli_stmt_bind_param($stmt, 'i', $reqId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $req = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);

            if (!$req) throw new RuntimeException('Request not found.');
            if ($req['status'] !== 'Pending') {
                throw new RuntimeException('This request has already been ' . strtolower($req['status']) . ' and cannot be modified again.');
            }

            if ($action === 'reject') {
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE book_requests
                        SET status = 'Declined',
                            admin_response_date = NOW(),
                            admin_response_by = ?,
                            admin_response_note = ?
                  WHERE request_id = ? AND status = 'Pending'"
                );
                mysqli_stmt_bind_param($stmt, 'isi', $adminId, $note, $reqId);
                mysqli_stmt_execute($stmt);
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                if ($affected !== 1) {
                    throw new RuntimeException('This request was processed by another admin. Please refresh.');
                }
                return null; // reject returns no due_date
            }

            // 2. Lock the book row (and its author, for the issued_books row).
            $stmt = mysqli_prepare(
                $conn,
                'SELECT b.book_id, b.book_name, b.book_no, b.book_qty, b.author_id,
                        a.author_name
                   FROM books b
              LEFT JOIN authors a ON a.author_id = b.author_id
                  WHERE b.book_id = ? FOR UPDATE'
            );
            mysqli_stmt_bind_param($stmt, 'i', $req['book_id']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $book = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (!$book) throw new RuntimeException('Book no longer exists.');

            // 4. Quantity guard.
            if ((int)$book['book_qty'] <= 0) {
                throw new RuntimeException('Book has no available copies — cannot approve.');
            }

            // 3. Lock the user row.
            $stmt = mysqli_prepare(
                $conn,
                'SELECT id, deleted_at, is_active
                   FROM users WHERE id = ? FOR UPDATE'
            );
            mysqli_stmt_bind_param($stmt, 'i', $req['user_id']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $member = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (!$member) throw new RuntimeException('Requesting user no longer exists.');
            if (!empty($member['deleted_at']) || (int)$member['is_active'] === 0) {
                throw new RuntimeException('Requesting user is deactivated or deleted.');
            }

            // 5. Duplicate-active-loan guard.
            $stmt = mysqli_prepare(
                $conn,
                'SELECT COUNT(*) AS c FROM issued_books
                  WHERE student_id = ? AND book_no = ? AND status = 1'
            );
            mysqli_stmt_bind_param($stmt, 'is', $req['user_id'], $book['book_no']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $dupLoan = $res ? (int)mysqli_fetch_assoc($res)['c'] : 0;
            mysqli_stmt_close($stmt);
            if ($dupLoan > 0) {
                throw new RuntimeException('This user already has this book issued. Please ask them to return it first.');
            }

            // 6. Borrowing-limit guard.
            $stmt = mysqli_prepare(
                $conn,
                'SELECT COUNT(*) AS c FROM issued_books
                  WHERE student_id = ? AND status = 1'
            );
            mysqli_stmt_bind_param($stmt, 'i', $req['user_id']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $active = $res ? (int)mysqli_fetch_assoc($res)['c'] : 0;
            mysqli_stmt_close($stmt);
            if ($active >= AURORA_BORROW_LIMIT) {
                throw new RuntimeException(
                    'User has reached the borrowing limit (' . AURORA_BORROW_LIMIT . ' active books). ' .
                    'Ask the user to return a book before approving more.'
                );
            }

            // 7. Cross-request duplicate guard.
            $stmt = mysqli_prepare(
                $conn,
                "SELECT request_id FROM book_requests
                  WHERE user_id = ? AND book_id = ?
                    AND status = 'Approved'
                  LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt, 'ii', $req['user_id'], $req['book_id']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $approvedDup = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($approvedDup) {
                throw new RuntimeException('A previous approved request for this book already exists for this user.');
            }

            // 8. Compute dates + insert issue row.
            $issueDate = date('Y-m-d');
            $dueDate   = date('Y-m-d', strtotime('+' . + AURORA_LOAN_DAYS . ' days'));
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO issued_books
                    (book_no, book_name, book_author, student_id, status,
                     issue_date, due_date, return_date, fine_amount, fine_status)
                 VALUES (?, ?, ?, ?, 1, ?, ?, NULL, 0, 'No Fine')"
            );
            $authorName = (string)($book['author_name'] ?? '');
            mysqli_stmt_bind_param(
                $stmt, 'sssiss',
                $book['book_no'], $book['book_name'], $authorName,
                $req['user_id'], $issueDate, $dueDate
            );
            mysqli_stmt_execute($stmt);
            $issuedId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            if (!$issuedId) throw new RuntimeException('Could not create issue record.');

            // 9. Decrement stock (guarded — never negative).
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE books SET book_qty = book_qty - 1
                  WHERE book_id = ? AND book_qty > 0'
            );
            mysqli_stmt_bind_param($stmt, 'i', $req['book_id']);
            mysqli_stmt_execute($stmt);
            $decremented = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($decremented !== 1) {
                throw new RuntimeException('Stock changed underneath us; please retry.');
            }

            // 10. Mark request approved.
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE book_requests
                    SET status = 'Approved',
                        admin_response_date = NOW(),
                        admin_response_by = ?,
                        admin_response_note = ?,
                        due_date = ?
                  WHERE request_id = ? AND status = 'Pending'"
                );
            mysqli_stmt_bind_param($stmt, 'issi', $adminId, $note, $dueDate, $reqId);
            mysqli_stmt_execute($stmt);
            $marked = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($marked !== 1) {
                throw new RuntimeException('Request was modified by another admin. Please refresh.');
            }

            return $dueDate;
        });
        $ok = true;
    } catch (Throwable $e) {
        // Always log the raw cause in dev; never echo it to the user.
        aurora_log_error('respond_request.' . $action, $e);
        // Prefer the worker's own message when it was a controlled
        // RuntimeException with safe text; fall back to a generic
        // message for anything that escaped from a low-level mysqli error.
        $err = ($e instanceof RuntimeException)
            ? $e->getMessage()
            : aurora_user_facing_error();
    }

    if ($ok) {
        $msg = $action === 'approve'
            ? 'Book request approved. Book issued (due ' . ($dueDate ?: '') . ').'
            : 'Book request declined.';
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => $msg];
    } else {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => $err ?: aurora_user_facing_error()];
    }

    header('Location: manage_requests.php');
    exit;