<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../admin_auth_check.php';

    $categories = aurora_fetch_all('SELECT cat_id, cat_name FROM category ORDER BY cat_name');
    $authors    = aurora_fetch_all('SELECT author_id, author_name FROM authors ORDER BY author_name');

    $errors = [];
    $flash  = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/../Admin Dashboard Module/_admin_sidebar.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!aurora_csrf_check($token)) {
            $errors[] = 'Invalid security token.';
        } else {
            $bookName  = trim((string)($_POST['book_name']   ?? ''));
            $authorId  = filter_var($_POST['author_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $catId     = filter_var($_POST['cat_id']    ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $bookNo    = trim((string)($_POST['book_no']  ?? ''));
            $bookPrice = trim((string)($_POST['book_price'] ?? ''));
            $bookQty   = aurora_validate_non_negative_int($_POST['book_qty'] ?? null, 100000);

            if ($bookName === '' || mb_strlen($bookName) > 255) {
                $errors[] = 'Please enter a valid book name (max 255 chars).';
            }
            if (!$authorId) {
                $errors[] = 'Please choose a valid author.';
            }
            if (!$catId) {
                $errors[] = 'Please choose a valid category.';
            }
            if ($bookNo === '' || mb_strlen($bookNo) > 50) {
                $errors[] = 'Please enter a valid book number (max 50 chars).';
            }
            $price = null;
            if ($bookPrice !== '') {
                $price = filter_var($bookPrice, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
                if ($price === false) {
                    $errors[] = 'Book price must be a non-negative number.';
                }
            }
            if ($bookQty === null || $bookQty === false) {
                $errors[] = 'Quantity must be a non-negative integer.';
            }

            // Duplicate book_no guard (keeps stock math honest).
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
                $conn = aurora_db();
                mysqli_report(MYSQLI_REPORT_OFF);
                mysqli_begin_transaction($conn);
                try {
                    $stmt = mysqli_prepare(
                        $conn,
                        'INSERT INTO books
                            (book_name, author_id, cat_id, book_no, book_price, book_qty, book_image, book_description)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $image = trim((string)($_POST['book_image'] ?? '')) ?: null;
                    $desc  = trim((string)($_POST['book_description'] ?? '')) ?: null;
                    mysqli_stmt_bind_param(
                        $stmt,
                        'siisdiss',
                        $bookName,
                        $authorId,
                        $catId,
                        $bookNo,
                        $price,
                        $bookQty,
                        $image,
                        $desc
                    );
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    mysqli_commit($conn);
                    $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Book added successfully.'];
                    header('Location: manage_book.php');
                    exit;
                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    // Log the real cause in dev; show a safe message to the user.
                    aurora_log_error('add_book', $e);
                    $errors[] = aurora_user_facing_error();
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Book — Aurora Library</title>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
      <style>
          .form-card { max-width: 720px; margin: 0 auto; }
      </style>
</head>
<body>
<div class="app-shell">
    <?php render_admin_sidebar($current); ?>

    <main class="app-main" id="app-main">
        <?php render_admin_header('Add Book'); ?>

        <div style="max-width:1100px;margin:0 auto;">
            <h4 style="margin:0 0 14px;">Add New Book</h4>

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

            <div class="form-card">
                <form method="POST" action="add_book.php">
                    <?php echo aurora_csrf_field(); ?>

                    <div class="form-group">
                        <label for="book_name">Book Name:</label>
                        <input type="text" name="book_name" id="book_name" class="form-control" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="author_id">Author:</label>
                        <select class="form-control" name="author_id" id="author_id" required>
                            <option value="">-Select author-</option>
                            <?php foreach ($authors as $a): ?>
                                <option value="<?php echo (int)$a['author_id']; ?>">
                                    <?php echo htmlspecialchars($a['author_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="cat_id">Category:</label>
                        <select class="form-control" name="cat_id" id="cat_id" required>
                            <option value="">-Select category-</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo (int)$c['cat_id']; ?>">
                                    <?php echo htmlspecialchars($c['cat_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="book_no">Book Number:</label>
                        <input type="text" name="book_no" id="book_no" class="form-control" required maxlength="50">
                    </div>

                    <div class="form-group">
                        <label for="book_price">Book Price (₹):</label>
                        <input type="number" step="0.01" min="0" name="book_price" id="book_price" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="book_qty">Quantity in Stock:</label>
                        <input type="number" min="0" max="100000" name="book_qty" id="book_qty" class="form-control" required value="1">
                        <small class="text-muted">Available copies. Will decrease when borrowed, increase when returned.</small>
                    </div>

                    <div class="form-group">
                        <label for="book_image">Cover Image URL (optional):</label>
                        <input type="url" name="book_image" id="book_image" class="form-control" maxlength="500" placeholder="https://...">
                    </div>

                    <div class="form-group">
                        <label for="book_description">Description (optional):</label>
                        <textarea name="book_description" id="book_description" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>

                    <button type="submit" name="add_book" class="btn btn-primary">Add Book</button>
                    <a href="manage_book.php" class="btn btn-default">Cancel</a>
                </form>
            </div>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>