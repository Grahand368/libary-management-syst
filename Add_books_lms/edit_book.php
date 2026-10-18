<?php
    /**
     * Aurora Library — Admin: Edit Book
     *
     * GET  -> load the book (admin only) and show the form
     * POST -> validate + update in a transaction (admin + CSRF)
     *
     * Hardened: prepared statements, CSRF, server-side validation,
     * transaction with row lock, generic user-facing error message
     * via the aurora_error pipeline.
     */
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';
    require_once __DIR__ . '/../aurora_validation.php';

    require_once __DIR__ . '/../admin_auth_check.php';

    $bookId = aurora_validate_positive_int($_GET['bn'] ?? null);
    if (!$bookId) {
        aurora_flash_set('error', 'Invalid book reference.');
        header('Location: manage_book.php');
        exit;
    }

    $authors    = aurora_fetch_all('SELECT author_id, author_name FROM authors ORDER BY author_name');
    $categories = aurora_fetch_all('SELECT cat_id, cat_name FROM category ORDER BY cat_name');

    $book = aurora_fetch_one(
        'SELECT book_id, book_name, book_no, author_id, cat_id, book_price, book_qty
           FROM books WHERE book_id = ? LIMIT 1',
        'i',
        [$bookId]
    );
    if (!$book) {
        aurora_flash_set('error', 'Book not found.');
        header('Location: manage_book.php');
        exit;
    }

    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!aurora_csrf_check($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token. Please retry.';
        } else {
            $bookName = aurora_validate_length($_POST['book_name'] ?? '', 1, 255);
            if ($bookName === null) $errors[] = 'Book name must be 1–255 characters.';

            $authorId = aurora_validate_positive_int($_POST['author_id'] ?? null);
            if ($authorId === null) $errors[] = 'Please choose a valid author.';

            $catId = aurora_validate_positive_int($_POST['cat_id'] ?? null);
            if ($catId === null) $errors[] = 'Please choose a valid category.';

            $price = null;
            $rawPrice = trim((string)($_POST['book_price'] ?? ''));
            if ($rawPrice !== '') {
                $price = aurora_validate_non_negative_float($rawPrice, 10000000);
                if ($price === null) $errors[] = 'Book price must be a non-negative number.';
            }

            $bookQty = aurora_validate_non_negative_int($_POST['book_qty'] ?? null, 100000);
            if ($bookQty === null) $errors[] = 'Quantity must be 0–100000.';

            if (empty($errors)) {
                $r = aurora_with_transaction(function ($conn) use ($bookId, $bookName, $authorId, $catId, $price, $bookQty) {
                    $stmt = mysqli_prepare(
                        $conn,
                        'SELECT book_id, book_qty FROM books WHERE book_id = ? FOR UPDATE'
                    );
                    mysqli_stmt_bind_param($stmt, 'i', $bookId);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $row = $res ? mysqli_fetch_assoc($res) : null;
                    mysqli_stmt_close($stmt);
                    if (!$row) throw new RuntimeException('Book disappeared while editing.');

                    $stmt = mysqli_prepare(
                        $conn,
                        'UPDATE books
                            SET book_name = ?, author_id = ?, cat_id = ?,
                                book_price = ?, book_qty = ?
                          WHERE book_id = ?'
                    );
                    mysqli_stmt_bind_param(
                        $stmt,
                        'siidii',
                        $bookName, $authorId, $catId, $price, $bookQty, $bookId
                    );
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                });

                if ($r['ok']) {
                    aurora_flash_set('success', 'Book updated successfully.');
                    header('Location: manage_book.php');
                    exit;
                }
                $errors[] = aurora_user_facing_error();
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Book — Aurora Library</title>
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
        <div class="section-label">Catalog</div>
        <a href="add_book.php">Add Book</a>
        <a class="active" href="manage_book.php">Manage Books</a>
        <a href="../Manage_Book_Categories/manage_cat.php">Categories</a>
        <a href="../Manage_Book_Categories/manage_authors.php">Authors</a>
        <div class="section-label">Account</div>
        <a href="../Admin Dashboard Module/view_profile.php">View Profile</a>
        <a href="../Admin Dashboard Module/change_password.php">Change Password</a>
        <div class="section-label">Session</div>
        <a href="../logout.php">Logout</a>
    </aside>
    <div class="sidebar-overlay" data-sidebar-overlay aria-hidden="true"></div>

    <main class="app-main" id="app-main">
        <header class="app-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <button class="menu-toggle" data-menu-toggle aria-label="Open menu">☰</button>
                <div class="header-title">Edit Book</div>
            </div>
        </header>

        <div style="max-width:640px;margin:0 auto;">
            <?php foreach ($errors as $e): ?>
                <?php echo aurora_toast_inline('error', $e); ?>
            <?php endforeach; ?>

            <form method="POST" action="edit_book.php?bn=<?php echo (int)$bookId; ?>" class="card-bordered" style="padding:18px;">
                <?php echo aurora_csrf_field(); ?>

                <div class="form-group">
                    <label for="book_no">Book Number (read-only)</label>
                    <input type="text" id="book_no" class="form-control"
                           value="<?php echo htmlspecialchars($book['book_no']); ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="book_name">Book Name</label>
                    <input type="text" name="book_name" id="book_name" class="form-control" required maxlength="255"
                           value="<?php echo htmlspecialchars($book['book_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="author_id">Author</label>
                    <select name="author_id" id="author_id" class="form-control" required>
                        <?php foreach ($authors as $a): ?>
                            <option value="<?php echo (int)$a['author_id']; ?>"
                                <?php echo ((int)$a['author_id'] === (int)$book['author_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['author_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cat_id">Category</label>
                    <select name="cat_id" id="cat_id" class="form-control" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo (int)$c['cat_id']; ?>"
                                <?php echo ((int)$c['cat_id'] === (int)$book['cat_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['cat_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="book_price">Price (Rs.)</label>
                    <input type="number" step="0.01" min="0" name="book_price" id="book_price" class="form-control"
                           value="<?php echo htmlspecialchars((string)$book['book_price']); ?>">
                </div>

                <div class="form-group">
                    <label for="book_qty">Quantity</label>
                    <input type="number" min="0" max="100000" name="book_qty" id="book_qty" class="form-control" required
                           value="<?php echo (int)$book['book_qty']; ?>">
                </div>

                <button type="submit" class="btn btn-primary">Update Book</button>
                <a href="manage_book.php" class="btn btn-default">Cancel</a>
            </form>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>
