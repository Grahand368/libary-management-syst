<?php
/**
 * Aurora Library — Manage Authors (Admin)
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

$authors = aurora_fetch_all(
    'SELECT author_id, author_name FROM authors ORDER BY author_name'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Manage Authors — Aurora Library</title>
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
        <a href="../Admin Dashboard Module/manage_fines.php">Manage Fines</a>
        <a href="../Admin Dashboard Module/manage_requests.php">Book Requests</a>
        <a href="../Admin Dashboard Module/manage_purchase_requests.php">Purchase Suggestions</a>

        <div class="section-label">Catalog</div>
        <a href="../Add_books_lms/add_book.php">Add Book</a>
        <a href="../Add_books_lms/manage_book.php">Manage Books</a>
        <a href="manage_cat.php">Categories</a>
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
            <div class="header-left">
                <button class="menu-toggle" data-menu-toggle aria-label="Open menu">☰</button>
                <div class="header-title">Manage Authors</div>
            </div>
            <div class="header-right">
                <span class="text-muted" style="font-size: 13px;">
                    Welcome, <strong><?= htmlspecialchars($_SESSION['name'] ?? '') ?></strong>
                </span>
            </div>
        </header>

        <div style="max-width: 1000px; margin: 0 auto;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="text-muted mb-0">All authors in the catalogue.</p>
                <a href="add_author.php" class="btn btn-primary btn-sm">Add New Author</a>
            </div>

            <?php if ($flash): ?>
                <?= aurora_toast_inline($flash['type'], $flash['msg']) ?>
            <?php endif; ?>

            <div class="card-bordered">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Author Name</th>
                                <th style="width: 160px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($authors)): ?>
                                <tr>
                                    <td colspan="3" class="empty-row">No authors yet.</td>
                                </tr>
                            <?php else: foreach ($authors as $a): ?>
                                <tr>
                                    <td><?= (int)$a['author_id'] ?></td>
                                    <td class="wrap"><?= htmlspecialchars($a['author_name']) ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="edit_author.php?aid=<?= (int)$a['author_id'] ?>">Edit</a>
                                        <a class="btn btn-sm btn-outline-danger"
                                           href="delete_author.php?aid=<?= (int)$a['author_id'] ?>"
                                           onclick="return confirm('Delete this author?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>