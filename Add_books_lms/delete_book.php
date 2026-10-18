<?php
    /**
     * Aurora Library — Admin: Delete Book
     *
     * POST-only, CSRF-protected, admin-only. Refuses to delete a book
     * while any copies are currently issued (status = 1) to avoid
     * orphan rows in issued_books.
     */
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: manage_book.php');
        exit;
    }
    require_once __DIR__ . '/../admin_auth_check.php';

    if (!aurora_csrf_check($_POST['csrf_token'] ?? '')) {
        aurora_flash_set('error', 'Invalid security token.');
        header('Location: manage_book.php');
        exit;
    }

    $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if (!$bookId) {
        aurora_flash_set('error', 'Invalid book reference.');
        header('Location: manage_book.php');
        exit;
    }

    $conn = aurora_db();
    mysqli_report(MYSQLI_REPORT_OFF);

    $ok  = false;
    $err = '';

    mysqli_begin_transaction($conn);
    try {
        // Lock the book row and check the denormalised book_no.
        $stmt = mysqli_prepare(
            $conn,
            'SELECT book_id, book_no FROM books WHERE book_id = ? FOR UPDATE'
        );
        mysqli_stmt_bind_param($stmt, 'i', $bookId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $book = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$book) {
            throw new RuntimeException('Book not found.');
        }

        // Refuse if any copies are currently issued.
        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) AS c FROM issued_books
              WHERE book_no = ? AND status = 1'
        );
        mysqli_stmt_bind_param($stmt, 's', $book['book_no']);
        mysqli_stmt_execute($stmt);
        $res  = mysqli_stmt_get_result($stmt);
        $open = $res ? (int)mysqli_fetch_assoc($res)['c'] : 0;
        mysqli_stmt_close($stmt);

        if ($open > 0) {
            throw new RuntimeException(
                'Cannot delete: ' . $open . ' copy/copies of this book are currently issued.'
            );
        }

        $stmt = mysqli_prepare($conn, 'DELETE FROM books WHERE book_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $bookId);
        mysqli_stmt_execute($stmt);
        $deleted = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($deleted !== 1) {
            throw new RuntimeException('Book could not be deleted.');
        }

        mysqli_commit($conn);
        $ok = true;
    } catch (Throwable $e) {
        @mysqli_rollback($conn);
        aurora_log_error('delete_book', $e);
        $err = ($e instanceof RuntimeException) ? $e->getMessage() : aurora_user_facing_error();
    }

    if ($ok) {
        aurora_flash_set('success', 'Book deleted.');
    } else {
        aurora_flash_set('error', $err ?: aurora_user_facing_error());
    }
    header('Location: manage_book.php');
    exit;
