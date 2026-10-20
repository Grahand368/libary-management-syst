<?php
/**
 * Aurora Library — Add Book (Admin)
 */
require_once __DIR__ . '/../aurora_auth.php';
require_once __DIR__ . '/../aurora_db.php';
require_once __DIR__ . '/../aurora_error.php';
require_once __DIR__ . '/../aurora_validation.php';
require_once __DIR__ . '/../admin_auth_check.php';

$authors    = aurora_fetch_all('SELECT author_id, author_name FROM authors ORDER BY author_name');
$categories = aurora_fetch_all('SELECT cat_id, cat_name FROM category ORDER BY cat_name');

$errors = [];
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

$current = basename($_SERVER['SCRIPT_NAME']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!aurora_csrf_check($token)) {
        $errors[] = 'Invalid security token.';
    } else {
        $bookName  = aurora_validate_length($_POST['book_name'] ?? '', 1, 255);
        if ($bookName === null) $errors[] = 'Book name must be 1–255 characters.';

        $authorId = aurora_validate_positive_int($_POST['author_id'] ?? null);
        if ($authorId === null) $errors[] = 'Please choose a valid author.';

        $catId = aurora_validate_positive_int($_POST['cat_id'] ?? null);
        if ($catId === null) $errors[] = 'Please choose a valid category.';

        $bookNo = aurora_validate_length($_POST['book_no'] ?? '', 1, 50);
        if ($bookNo === null) $errors[] = 'Book number must be 1–50 characters.';

        $price = null;
        $rawPrice = trim((string)($_POST['book_price'] ?? ''));
        if ($rawPrice !== '') {
            $price = aurora_validate_non_negative_float($rawPrice, 10000000);
            if ($price === null) $errors[] = 'Book price must be a non-negative number.';
        }

        $bookQty = aurora_validate_non_negative_int($_POST['book_qty'] ?? null, 100000);
        if ($bookQty === null) $errors[] = 'Quantity must be 0–100000.';

        $image = trim((string)($_POST['book_image'] ?? '')) ?: null;
        $desc  = trim((string)($_POST['book_description'] ?? '')) ?: null;

        // Duplicate book_no guard
        if (empty($errors)) {
            $dup = aurora_fetch_one(
                'SELECT book_id FROM books WHERE book_no = ? LIMIT 1',
                's',
                [$bookNo]
            );
            if ($dup) {
                $errors[] = 'A book with that number already exists.';
            }
        }

        if (empty($errors)) {
            $r = aurora_with_transaction(function ($conn) use ($bookName, $authorId, $catId, $bookNo, $price, $bookQty, $image, $desc) {
                $stmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO books
                        (book_name, author_id, cat_id, book_no, book_price, book_qty, book_image, book_description)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'siisdiss',
                    $bookName, $authorId, $catId, $bookNo, $price, $bookQty, $image, $desc
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            });

            if ($r['ok']) {
                aurora_flash_set('success', 'Book added successfully.');
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Add Book — Aurora Library</title>
    <link rel="stylesheet" href="../bootstrap-4.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar" data-sidebar aria-label="Admin navigation">
        <div class="brand">⚜ Aurora Admin</div>

        <div class="section-label">Manage</div>
        <a href="../Admin Dashboard Module/admin_dashboard.php">Dashboard</a>
        <a href="../Admin Dashboard Module/manage_users.php">Manage Users</a>

        <div class="section-label">Catalog</div>
        <a class="active" href="add_book.php">Add Book</a>
        <a href="manage_book.php">Manage Books</a>
        <a href="../Manage_Book_Categories/manage_cat.php">Categories</a>
        <a href="../Manage_Book_Categories/manage_authors.php">Authors</a>

        <div class="section-label">Circulation</div>
        <a href="../Issue_book_Module/issue_book.php">Issue Book</a>
        <a href="return_book.php">Return Book</a>
        <a href="view_issued_book.php">Issued Books</a>

        <div class="section-label">Account</div>
        <a href="../Admin Dashboard Module/view_profile.php">View Profile</a>
        <a href="../Admin Dashboard Module/change_password.php">Change Password</a>

        <div class="section-label">Session</div>
        <a href="../logout.php">Logout</a>
    </aside>

    <div class="sidebar-overlay" data-sidebar-overlay aria-hidden="true"></div>

    <main class="app-main" id="app-main">
        <header class="app-header">
            <div class="header-left">
                <button class="menu-toggle" data-menu-toggle aria-label="Open menu">☰</button>
                <div class="header-title">Add Book</div>
            </div>
            <div class="header-right">
                <span class="text-muted" style="font-size: 13px;">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name'] ?? '') ?></strong>
                </span>
            </div>
        </header>

        <div class="card-bordered" style="max-width: 720px; margin: 0 auto;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 style="margin: 0;">Add New Book</h4>
            </div>

            <?php if ($flash): ?>
                <?= aurora_toast_inline($flash['type'], $flash['msg']) ?>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="aurora-alert aurora-alert-danger">
                    <div class="alert-content">
                        <?php foreach ($errors as $e): ?>
                            <div><?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="add_book.php">
                <?= aurora_csrf_field() ?>

                <div class="form-group">
                    <label for="book_name">Book Name</label>
                    <input type="text" name="book_name" id="book_name" class="form-control" required maxlength="255">
                </div>

                <div class="form-group">
                    <label for="author_id">Author</label>
                    <select class="form-control" name="author_id" id="author_id" required>
                        <option value="">Select author</option>
                        <?php foreach ($authors as $a): ?>
                            <option value="<?= (int)$a['author_id'] ?>">
                                <?= htmlspecialchars($a['author_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cat_id">Category</label>
                    <select class="form-control" name="cat_id" id="cat_id" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['cat_id'] ?>">
                                <?= htmlspecialchars($c['cat_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="book_no">Book Number</label>
                    <input type="text" name="book_no" id="book_no" class="form-control" required maxlength="50">
                </div>

                <div class="form-group">
                    <label for="book_price">Book Price (₹)</label>
                    <input type="number" step="0.01" min="0" name="book_price" id="book_price" class="form-control">
                </div>

                <div class="form-group">
                    <label for="book_qty">Quantity in Stock</label>
                    <input type="number" min="0" max="100000" name="book_qty" id="book_qty" class="form-control" required value="1">
                    <small class="help-text">Available copies. Decreases when borrowed, increases when returned.</small>
                </div>

                <div class="form-group">
                    <label for="book_image">Cover Image URL (optional)</label>
                    <input type="url" name="book_image" id="book_image" class="form-control" maxlength="500" placeholder="https://...">
                </div>

                <div class="form-group">
                    <label for="book_description">Description (optional)</label>
                    <textarea name="book_description" id="book_description" class="form-control" rows="3" maxlength="2000"></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Add Book</button>
                    <a href="manage_book.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>