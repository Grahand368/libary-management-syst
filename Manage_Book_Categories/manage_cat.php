<?php
/**
 * Aurora Library — Manage Categories (Admin)
 */
require_once __DIR__ . '/../aurora_auth.php';
require_once __DIR__ . '/../aurora_db.php';
require_once __DIR__ . '/../aurora_error.php';
require_once __DIR__ . '/../admin_auth_check.php';

$flash = aurora_flash_pull();
if (!$flash && !empty($_SESSION['_flash'])) {
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
}

$categories = aurora_fetch_all('SELECT cat_id, cat_name FROM category ORDER BY cat_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Categories — Aurora Library</title>
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
        <a href="../Add_books_lms/add_book.php">Add Book</a>
        <a href="../Add_books_lms/manage_book.php">Manage Books</a>
        <a class="active" href="manage_cat.php">Categories</a>
        <a href="manage_authors.php">Authors</a>

        <div class="section-label">Circulation</div>
        <a href="../Issue_book_Module/issue_book.php">Issue Book</a>
        <a href="../Admin Dashboard Module/return_book.php">Return Book</a>
        <a href="../Admin Dashboard Module/view_issued_book.php">Issued Books</a>

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
                <div class="header-title">Manage Categories</div>
            </div>
            <div class="header-right">
                <span class="text-muted" style="font-size: 13px;">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name'] ?? '') ?></strong>
                </span>
            </div>
        </header>

        <?php if ($flash): ?>
            <?= aurora_toast_inline($flash['type'], $flash['msg']) ?>
        <?php endif; ?>

        <div class="card-bordered">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th style="width: 160px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="2" class="empty-row">No categories yet.</td>
                            </tr>
                        <?php else: foreach ($categories as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['cat_name']) ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="edit_cat.php?cid=<?= (int)$c['cat_id'] ?>">Edit</a>
                                    <a class="btn btn-sm btn-outline-danger"
                                       href="delete_cat.php?cid=<?= (int)$c['cat_id'] ?>"
                                       onclick="return confirm('Delete this category?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>