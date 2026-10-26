<?php
    /**
     * Aurora Library — Admin Dashboard
     *
     * Sections:
     *   1. Users       — Total / Active / Suspended
     *   2. Books       — Total / Available / Issued / Overdue
     *   3. Requests    — Pending book requests / Pending purchase suggestions
     *   4. Fines       — Active (outstanding) fine amount
     *   5. Quick actions
     *   6. Recent activity (latest 5 issues + latest 5 users)
     */
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../admin_auth_check.php';
    require_once __DIR__ . '/functions.php';

    $stats = aurora_admin_dashboard_stats();

    $sidebar_manage = [
        ['label' => 'Dashboard',    'href' => 'admin_dashboard.php'],
        ['label' => 'Manage Users', 'href' => 'manage_users.php'],
    ];
    $sidebar_catalog = [
        ['label' => 'Add Book',        'href' => '../Add_books_lms/add_book.php'],
        ['label' => 'Manage Books',    'href' => '../Add_books_lms/manage_book.php'],
        ['label' => 'Categories',      'href' => '../Manage_Book_Categories/manage_cat.php'],
        ['label' => 'Authors',         'href' => '../Manage_Book_Categories/manage_authors.php'],
    ];
    $sidebar_circulation = [
        ['label' => 'Issue Book',     'href' => '../Issue_book_Module/issue_book.php'],
        ['label' => 'Return Book',    'href' => 'return_book.php'],
        ['label' => 'Issued Books',   'href' => 'view_issued_book.php'],
    ];
    $sidebar_requests = [
        ['label' => 'Book Requests',        'href' => 'manage_requests.php'],
        ['label' => 'Purchase Suggestions', 'href' => 'manage_purchase_requests.php'],
    ];
    $sidebar_fines = [
        ['label' => 'Manage Fines', 'href' => 'manage_fines.php'],
    ];
    $profile_links = [
        ['label' => 'View Profile',    'href' => 'view_profile.php'],
        ['label' => 'Edit Profile',    'href' => 'edit_profile.php'],
        ['label' => 'Change Password', 'href' => 'change_password.php'],
    ];

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_admin_sidebar.php';

    // ── Recent activity feeds ───────────────────────────────────────────
    $recentIssues = aurora_fetch_all(
        "SELECT i.id, i.book_name, i.book_no, i.issue_date, i.due_date,
                i.fine_amount, i.fine_status,
                u.name AS user_name
           FROM issued_books i
           LEFT JOIN users u ON u.id = i.student_id
       ORDER BY i.id DESC
          LIMIT 5"
    );
    $recentUsers = aurora_fetch_all(
        "SELECT id, name, email, created_at, is_active, deleted_at
           FROM users
       ORDER BY id DESC
          LIMIT 5"
    );
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Admin Dashboard — Aurora Library</title>
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
      <style>
        .overdue-link { color:#c0392b; font-weight:700; }
      </style>
</head>
<body>
<div class="app-shell">
    <?php render_admin_sidebar($current); ?>

    <main class="app-main" id="app-main">
        <?php render_admin_header('Admin Dashboard'); ?>

        <div style="max-width:1300px;margin:0 auto;">

            <!-- USERS -->
            <div class="section-title">Users</div>
            <div class="dash-grid-3">
                <div class="stat-card stat-blue">
                    <small>Total Users</small>
                    <h2><?php echo (int)$stats['users']['total']; ?></h2>
                    <small><a href="manage_users.php">Manage users →</a></small>
                </div>
                <div class="stat-card stat-green">
                    <small>Active Users</small>
                    <h2><?php echo (int)$stats['users']['active']; ?></h2>
                    <small>is_active = 1, not deleted</small>
                </div>
                <div class="stat-card stat-amber">
                    <small>Suspended Users</small>
                    <h2><?php echo (int)$stats['users']['suspended']; ?></h2>
                    <small>is_active = 0, not deleted</small>
                </div>
            </div>

            <!-- BOOKS -->
            <div class="section-title">Books</div>
            <div class="dash-grid-4">
                <div class="stat-card stat-purple">
                    <small>Total Books</small>
                    <h2><?php echo (int)$stats['books']['total']; ?></h2>
                    <small><a href="../Add_books_lms/manage_book.php">Manage books →</a></small>
                </div>
                <div class="stat-card stat-teal">
                    <small>Available Books</small>
                    <h2><?php echo (int)$stats['books']['available']; ?></h2>
                    <small>book_qty &gt; 0</small>
                </div>
                <div class="stat-card stat-blue">
                    <small>Issued Books</small>
                    <h2><?php echo (int)$stats['books']['issued']; ?></h2>
                    <small><a href="view_issued_book.php">View issued →</a></small>
                </div>
                <div class="stat-card stat-red">
                    <small>Overdue Books</small>
                    <h2><?php echo (int)$stats['books']['overdue']; ?></h2>
                    <small><a href="view_issued_book.php" class="overdue-link">Take action →</a></small>
                </div>
            </div>

            <!-- REQUESTS + ACTIVE FINES -->
            <div class="section-title">Requests &amp; Fines</div>
            <div class="dash-grid-3">
                <div class="stat-card stat-amber">
                    <small>Pending Book Requests</small>
                    <h2><?php echo (int)$stats['requests']['pending']; ?></h2>
                    <small><a href="manage_requests.php?filter=pending">Review →</a></small>
                </div>
                <div class="stat-card stat-purple">
                    <small>Pending Purchase Suggestions</small>
                    <h2><?php echo (int)$stats['requests']['pending_purchase_suggestions']; ?></h2>
                    <small><a href="manage_purchase_requests.php?filter=pending">Review →</a></small>
                </div>
                <div class="stat-card stat-red">
                    <small>Active Fines</small>
                    <h2>Rs. <?php echo number_format((float)$stats['fines']['outstanding'], 2); ?></h2>
                    <small><a href="manage_fines.php?filter=unpaid">View unpaid →</a></small>
                </div>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="section-title">Quick Actions</div>
            <div class="quick-actions">
                <a class="quick-btn" href="manage_users.php">👥 Manage Users</a>
                <a class="quick-btn" href="../Add_books_lms/add_book.php">➕ Add Book</a>
                <a class="quick-btn" href="../Add_books_lms/manage_book.php">📚 Manage Books</a>
                <a class="quick-btn" href="../Issue_book_Module/issue_book.php">📤 Issue Book</a>
                <a class="quick-btn" href="return_book.php">📥 Return Book</a>
                <a class="quick-btn" href="manage_requests.php">📨 Book Requests</a>
                <a class="quick-btn" href="manage_purchase_requests.php">💡 Purchase Suggestions</a>
                <a class="quick-btn" href="manage_fines.php">💸 Fine Management</a>
            </div>

            <!-- RECENT ACTIVITY (two columns) -->
            <div class="section-title">Recent Activity</div>
            <div class="dash-grid-2">
                <div class="card-bordered">
                    <h5 style="margin-top:0;">Latest Issued Books</h5>
                    <div class="table-responsive" style="box-shadow:none;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Book</th>
                                    <th>Issued</th>
                                    <th>Due</th>
                                    <th>Fine</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($recentIssues)): ?>
                                <tr><td colspan="5" class="empty-row">No issues recorded yet.</td></tr>
                            <?php else: foreach ($recentIssues as $r): ?>
                                <tr>
                                    <td class="wrap"><?php echo htmlspecialchars($r['user_name'] ?? '—'); ?></td>
                                    <td class="wrap"><?php echo htmlspecialchars($r['book_name']); ?>
                                        <br><small class="text-muted">No. <?php echo htmlspecialchars($r['book_no']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['issue_date'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($r['due_date'] ?? '—'); ?></td>
                                    <td>
                                        <?php if ((float)$r['fine_amount'] > 0): ?>
                                            Rs. <?php echo number_format((float)$r['fine_amount'], 2); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($r['fine_status']); ?></small>
                                        <?php else: ?>
                                            <span class="text-success">Rs. 0.00</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-bordered">
                    <h5 style="margin-top:0;">Latest Registered Users</h5>
                    <div class="table-responsive" style="box-shadow:none;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($recentUsers)): ?>
                                <tr><td colspan="5" class="empty-row">No users yet.</td></tr>
                            <?php else: foreach ($recentUsers as $r):
                                $statusLabel = !empty($r['deleted_at']) ? 'Deleted' : (((int)$r['is_active'] === 1) ? 'Active' : 'Suspended');
                                $statusClass = !empty($r['deleted_at']) ? 'badge-secondary' : (((int)$r['is_active'] === 1) ? 'badge-success' : 'badge-warning');
                            ?>
                                <tr>
                                    <td><?php echo (int)$r['id']; ?></td>
                                    <td class="wrap"><?php echo htmlspecialchars($r['name']); ?></td>
                                    <td class="wrap"><?php echo htmlspecialchars($r['email']); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                    <td class="wrap"><?php echo htmlspecialchars($r['created_at']); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>
