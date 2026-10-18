<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';
    require_once __DIR__ . '/../aurora_validation.php';
    require_once __DIR__ . '/../admin_auth_check.php';

    $errors = [];
    $authorName = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';

        if (!aurora_csrf_check($token)) {
            $errors[] = 'Invalid security token. Please try again.';
        } else {
            $authorName = trim((string)($_POST['author_name'] ?? ''));

            $validName = aurora_validate_name($authorName);
            if ($validName === null) {
                $errors[] = 'Please enter a valid author name (2-100 characters, at least one letter).';
            } else {
                $authorName = $validName;

                $existing = aurora_fetch_one(
                    'SELECT author_id FROM authors WHERE author_name = ? LIMIT 1',
                    's',
                    [$authorName]
                );
                if ($existing) {
                    $errors[] = 'An author with that name already exists.';
                }
            }

            if (empty($errors)) {
                $result = aurora_safe_handle(function () use ($authorName) {
                    return aurora_execute(
                        'INSERT INTO authors (author_name) VALUES (?)',
                        's',
                        [$authorName]
                    );
                }, 'add_author');

                if (!empty($result['ok']) && ($result['value'] ?? 0) > 0) {
                    aurora_flash_set('success', 'Author added successfully.');
                    header('Location: manage_authors.php');
                    exit;
                } else {
                    $errors[] = $result['ok'] ? 'Failed to add author. Please try again.' : aurora_user_facing_error();
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" name="viewport" content="width=device-width,intial-scale=1,viewport-fit=cover">
    <title>Add Author — Aurora Library</title>
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
        <a href="../Admin Dashboard Module/manage_purchase_requests.php">Purchase Suggestions</a>
        <div class="section-label">Catalog</div>
        <a href="../Add_books_lms/add_book.php">Add Book</a>
        <a href="../Add_books_lms/manage_book.php">Manage Books</a>
        <a class="active" href="manage_cat.php">Categories</a>
        <a class="active" href="manage_authors.php">Authors</a>
        <a href="../Admin Dashboard Module/return_book.php">Issue / Return Books</a>
        <a href="../Admin Dashboard Module/view_issued_book.php">Issued Books</a>
        <div class="section-label">Account</div>
        <a href="../Admin Dashboard Module/view_profile.php">View Profile</a>
        <a href="../Admin Dashboard Module/edit_profile.php">Edit Profile</a>
        <a href="../Admin Dashboard Module/change_password.php">Change Password</a>
        <div class="section-label">Session</div>
        <a href="../logout.php">Logout</a>
    </aside>

    <div class="sidebar-overlay" data-sidebar-overlay aria-hidden="true"></div>

    <main class="app-main" id="app-main">
        <header class="app-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <button class="menu-toggle" data-menu-toggle aria-label="Open menu">☰</button>
                <div class="header-title">Add Author</div>
            </div>
            <div class="text-muted" style="font-size:13px;">
                Welcome, <strong><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></strong>
            </div>
        </header>

        <div style="max-width:1000px;margin:0 auto;">
            <p class="text-muted">Add a new author to the catalogue.</p>

            <?php echo aurora_toast_render('error'); ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e): ?>
                        <div><?php echo htmlspecialchars($e); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card-bordered" style="max-width:600px;">
                <form method="POST" action="add_author.php">
                    <?php echo aurora_csrf_field(); ?>

                    <div class="form-group">
                        <label for="author_name">Author Name:</label>
                        <input type="text" class="form-control" id="author_name" name="author_name" required maxlength="100" value="<?php echo htmlspecialchars($authorName); ?>">
                    </div>

                    <button type="submit" name="add_author" class="btn btn-primary">Add Author</button>
                    <a href="manage_authors.php" class="btn btn-default">Cancel</a>
                </form>
            </div>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>
