<?php
/**
 * Aurora Library — Manage Books (Admin)
 */
require_once __DIR__ . '/../aurora_auth.php';
require_once __DIR__ . '/../aurora_db.php';
require_once __DIR__ . '/../aurora_error.php';
require_once __DIR__ . '/../admin_auth_check.php';

$conn = aurora_db();
$flash = aurora_flash_pull();
if (!$flash && !empty($_SESSION['_flash'])) {
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
}
$csrf = aurora_csrf_token();

$current = basename($_SERVER['SCRIPT_NAME']);
require __DIR__ . '/../Admin Dashboard Module/_admin_sidebar.php';

$books = aurora_fetch_all(
    'SELECT b.book_id, b.book_name, b.book_no, b.book_price,
            a.author_name, c.cat_name
       FROM books b
       JOIN authors a ON a.author_id = b.author_id
       JOIN category c ON c.cat_id = b.cat_id
   ORDER BY b.book_id DESC'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Books — Aurora Library</title>
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
        <a href="add_book.php">Add Book</a>
        <a class="active" href="manage_book.php">Manage Books</a>
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
                <div class="header-title">Manage Books</div>
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
                            <th>Author</th>
                            <th>Category</th>
                            <th>ISBN No.</th>
                            <th>Price</th>
                            <th style="width: 160px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($books)): ?>
                            <tr>
                                <td colspan="6" class="empty-row">No books found.</td>
                            </tr>
                        <?php else: foreach ($books as $book): ?>
                            <tr>
                                <td><?= htmlspecialchars($book['book_name']) ?></td>
                                <td><?= htmlspecialchars($book['author_name']) ?></td>
                                <td><?= htmlspecialchars($book['cat_name']) ?></td>
                                <td><?= htmlspecialchars($book['book_no']) ?></td>
                                <td>₹<?= htmlspecialchars((string)$book['book_price']) ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="edit_book.php?bn=<?= (int)$book['book_id'] ?>">Edit</a>
                                    <form method="POST" action="delete_book.php"
                                          onsubmit="return confirm('Delete this book?');"
                                          style="display:inline;">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                        <input type="hidden" name="book_id"
                                               value="<?= (int)$book['book_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
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