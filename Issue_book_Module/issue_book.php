<?php
    /**
     * Aurora Library — Admin: Issue Book
     *
     * Transactional flow (matches Part 22 spec):
     *   1. Lock the book row
     *   2. Check book_qty > 0
     *   3. Check user has no active loan for the same book_no
     *   4. Compute due_date = issue_date + AURORA_LOAN_DAYS
     *   5. INSERT into issued_books (status=1)
     *   6. UPDATE books SET book_qty = book_qty - 1  (guarded: book_qty > 0)
     *   7. COMMIT  (any failure -> ROLLBACK)
     */
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../admin_auth_check.php';

    $conn   = aurora_db();
    $flash  = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    $errors = [];
    $values = [
        'book_name'  => '',
        'book_no'    => '',
        'student_id' => '',
        'issue_date' => date('Y-m-d'),
        'author_id'  => '',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!aurora_csrf_check($token)) {
            $errors[] = 'Invalid security token.';
        } else {
            $values['book_name']  = trim((string)($_POST['book_name']  ?? ''));
            $values['book_no']    = trim((string)($_POST['book_no']    ?? ''));
            $values['student_id'] = trim((string)($_POST['student_id'] ?? ''));
            $values['issue_date'] = trim((string)($_POST['issue_date'] ?? date('Y-m-d')));
            $values['author_id']  = trim((string)($_POST['author_id']  ?? ''));

            if ($values['book_name'] === '' || mb_strlen($values['book_name']) > 255) {
                $errors[] = 'Book name is required (max 255 chars).';
            }
            if ($values['book_no'] === '' || mb_strlen($values['book_no']) > 50) {
                $errors[] = 'Book number is required (max 50 chars).';
            }
            if (!ctype_digit($values['student_id'])) {
                $errors[] = 'Student ID must be a positive integer.';
            }
            $studentId = (int)$values['student_id'];
            if ($studentId < 1) {
                $errors[] = 'Student ID must be a positive integer.';
            }
            $ts = strtotime($values['issue_date']);
            if (!$ts) {
                $errors[] = 'Issue date is invalid.';
            }
            $issueDate = $ts ? date('Y-m-d', $ts) : date('Y-m-d');

            if (empty($errors)) {
                $studentId = (int)$values['student_id'];
                $bookNo    = $values['book_no'];
                $bookName  = $values['book_name'];
                $author    = $values['author_id']; // legacy column 'book_author'
                $dueDate   = date('Y-m-d', strtotime('+' . + AURORA_LOAN_DAYS . ' days', strtotime($issueDate)));

                $r = aurora_with_transaction(function ($conn) use (
                    $studentId, $bookNo, $bookName, $author, $issueDate, $dueDate
                ) {
                    // 1. Lock the book row.
                    $stmt = mysqli_prepare(
                        $conn,
                        'SELECT book_id, book_qty FROM books WHERE book_no = ? FOR UPDATE'
                    );
                    if (!$stmt) throw new RuntimeException('Could not lock the book.');
                    mysqli_stmt_bind_param($stmt, 's', $bookNo);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $book = $res ? mysqli_fetch_assoc($res) : null;
                    mysqli_stmt_close($stmt);
                    if (!$book) throw new RuntimeException('No book with that number exists.');

                    // 2. Quantity guard.
                    if ((int)$book['book_qty'] <= 0) {
                        throw new RuntimeException('Book has no available copies.');
                    }

                    // 3. Lock & validate the user.
                    $stmt = mysqli_prepare(
                        $conn,
                        'SELECT id, deleted_at, is_active FROM users WHERE id = ? FOR UPDATE'
                    );
                    mysqli_stmt_bind_param($stmt, 'i', $studentId);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $user = $res ? mysqli_fetch_assoc($res) : null;
                    mysqli_stmt_close($stmt);
                    if (!$user) throw new RuntimeException('Student not found.');
                    if (!empty($user['deleted_at']) || (int)$user['is_active'] === 0) {
                        throw new RuntimeException('Student is deactivated or deleted.');
                    }

                    // 4. No duplicate active loan for the same book_no.
                    $stmt = mysqli_prepare(
                        $conn,
                        'SELECT COUNT(*) AS c FROM issued_books
                          WHERE student_id = ? AND book_no = ? AND status = 1'
                    );
                    mysqli_stmt_bind_param($stmt, 'is', $studentId, $bookNo);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $active = $res ? (int)mysqli_fetch_assoc($res)['c'] : 0;
                    mysqli_stmt_close($stmt);
                    if ($active > 0) {
                        throw new RuntimeException('This student already has this book issued.');
                    }

                    // 5. INSERT the issue record.
                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO issued_books
                            (book_no, book_name, book_author, student_id, status,
                             issue_date, due_date, return_date, fine_amount, fine_status)
                         VALUES (?, ?, ?, ?, 1, ?, ?, NULL, 0, 'No Fine')"
                    );
                    mysqli_stmt_bind_param(
                        $stmt, 'sssiss',
                        $bookNo, $bookName, $author, $studentId, $issueDate, $dueDate
                    );
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    // 6. Decrement stock (guarded — never negative).
                    $stmt = mysqli_prepare(
                        $conn,
                        'UPDATE books SET book_qty = book_qty - 1
                          WHERE book_no = ? AND book_qty > 0'
                    );
                    mysqli_stmt_bind_param($stmt, 's', $bookNo);
                    mysqli_stmt_execute($stmt);
                    $affected = mysqli_stmt_affected_rows($stmt);
                    mysqli_stmt_close($stmt);
                    if ($affected !== 1) {
                        throw new RuntimeException('Stock could not be decremented.');
                    }

                    return ['due_date' => $dueDate];
                });

                if ($r['ok']) {
                    $_SESSION['_flash'] = [
                        'type' => 'success',
                        'msg'  => 'Book issued successfully. Due ' . $r['due_date'] . '.',
                    ];
                    header('Location: admin_dashboard.php');
                    exit;
                }
                $errors[] = $r['error'] ?: 'Could not issue book.';
            }
        }
    }

    $authors = aurora_fetch_all('SELECT author_id, author_name FROM authors ORDER BY author_name');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Issue Book</title>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar" data-sidebar aria-label="Main navigation">
        <div class="brand">⚜ Aurora Admin</div>
        <div class="section-label">Manage</div>
        <a href="../Admin Dashboard Module/admin_dashboard.php">Dashboard</a>
        <a href="../Admin Dashboard Module/manage_users.php">Manage Users</a>
        <a href="../Admin Dashboard Module/manage_fines.php">Manage Fines</a>
        <a href="../Admin Dashboard Module/manage_requests.php">Book Requests</a>
        <a href="../Admin Dashboard Module/manage_purchase_requests.php">PurchaseSuggestions</a>
        <div class="section-label">Catalog</div>
        <a href="../Add_books_lms/add_book.php">Add Book</a>
        <a href="../Add_books_lms/manage_book.php">Manage Books</a>
        <a class="active" href="issue_book.php">Issue Book</a>
        <a href="../Admin Dashboard Module/view_issued_book.php">Issued Books</a>
    </aside>
    <div class="sidebar-overlay" data-sidebar-overlay aria-hidden="true"></div>

    <main class="app-main">
        <header class="app-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <button class="menu-toggle" data-menu-toggle aria-label="Open menu">☰</button>
                <div class="header-title">Issue Book</div>
            </div>
            <div class="text-muted" style="font-size:13px;">Loan period: <?php echo AURORA_LOAN_DAYS; ?> days</div>
        </header>

        <div style="max-width:760px;margin:0 auto;">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($flash['msg']); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e): ?>
                        <div><?php echo htmlspecialchars($e); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card-bordered">
                <form method="POST" action="issue_book.php" class="form">
                    <?php echo aurora_csrf_field(); ?>

                    <div class="form-group">
                        <label for="book_name">Book Name</label>
                        <input type="text" name="book_name" id="book_name" class="form-control"
                               required maxlength="255" value="<?php echo htmlspecialchars($values['book_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="book_no">Book Number</label>
                        <input type="text" name="book_no" id="book_no" class="form-control"
                               required maxlength="50" value="<?php echo htmlspecialchars($values['book_no']); ?>">
                        <small class="text-muted">Must match the <code>book_no</code> on the books table.</small>
                    </div>

                    <div class="form-group">
                        <label for="author_id">Author</label>
                        <select name="author_id" id="author_id" class="form-control">
                            <option value="">-Select author-</option>
                            <?php foreach ($authors as $a): ?>
                                <option value="<?php echo htmlspecialchars($a['author_name']); ?>">
                                    <?php echo htmlspecialchars($a['author_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input type="number" min="1" name="student_id" id="student_id" class="form-control"
                               required value="<?php echo htmlspecialchars($values['student_id']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="issue_date">Issue Date</label>
                        <input type="date" name="issue_date" id="issue_date" class="form-control"
                               required value="<?php echo htmlspecialchars($values['issue_date']); ?>">
                    </div>

                    <button type="submit" name="issue_book" class="btn btn-primary">Issue Book</button>
                    <a href="../Admin Dashboard Module/admin_dashboard.php" class="btn btn-default">Cancel</a>
                </form>
            </div>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>