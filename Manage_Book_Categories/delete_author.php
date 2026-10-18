<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';
    require_once __DIR__ . '/../aurora_validation.php';
    require_once __DIR__ . '/../admin_auth_check.php';

    $authorId = aurora_validate_positive_int($_GET['aid'] ?? null);
    if (!$authorId) {
        aurora_flash_set('error', 'Invalid author.');
        header('Location: manage_authors.php');
        exit;
    }

    $author = aurora_fetch_one(
        'SELECT author_name FROM authors WHERE author_id = ? LIMIT 1',
        'i',
        [$authorId]
    );

    if (!$author) {
        aurora_flash_set('error', 'Author not found.');
        header('Location: manage_authors.php');
        exit;
    }

    $bookCount = (int) aurora_fetch_one(
        'SELECT COUNT(*) AS c FROM books WHERE author_id = ? LIMIT 1',
        'i',
        [$authorId]
    )['c'];

    if ($bookCount > 0) {
        aurora_flash_set('error', 'Cannot delete author — ' . $bookCount . ' book(s) use this author. Reassign them first.');
        header('Location: manage_authors.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!aurora_csrf_check($token)) {
            aurora_flash_set('error', 'Invalid security token.');
            header('Location: manage_authors.php');
            exit;
        }

        $result = aurora_safe_handle(function () use ($authorId) {
            return aurora_execute(
                'DELETE FROM authors WHERE author_id = ?',
                'i',
                [$authorId]
            );
        }, 'delete_author');

        if (!empty($result['ok']) && ($result['value'] ?? 0) > 0) {
            aurora_flash_set('success', 'Author deleted successfully.');
        } else {
            aurora_flash_set('error', $result['ok'] ? 'Failed to delete author.' : aurora_user_facing_error());
        }
        header('Location: manage_authors.php');
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Delete Author — Aurora Library</title>
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
                <div class="header-title">Delete Author</div>
            </div>
            <div class="text-muted" style="font-size:13px;">
                Welcome, <strong><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></strong>
            </div>
        </header>

        <div style="max-width:1000px;margin:0 auto;">
            <?php echo aurora_toast_render('info'); ?>

            <div class="card-bordered" style="max-width:600px;">
                <div class="alert alert-warning">
                    Are you sure you want to delete the author <strong><?php echo htmlspecialchars($author['author_name']); ?></strong>?
                </div>
                <form method="POST" action="delete_author.php?aid=<?php echo $authorId; ?>">
                    <?php echo aurora_csrf_field(); ?>
                    <button type="submit" name="delete_author" class="btn btn-danger">Yes, Delete Author</button>
                    <a href="manage_authors.php" class="btn btn-default">Cancel</a>
                </form>
            </div>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>
