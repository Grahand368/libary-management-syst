<?php
    /**
     * Aurora Library — Admin: Manage Users
     *
     * Lists every user with the spec columns. Search composes via GET
     * (q matches name OR email). Soft-deleted users are hidden by default
     * but can be revealed with ?show_deleted=1.
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

    $q           = aurora_get_query_string('q');
    $showDeleted = !empty($_GET['show_deleted']);

    $where  = [];
    $types  = '';
    $args   = [];

    if (!$showDeleted) {
        $where[] = 'u.deleted_at IS NULL';
    }
    if ($q !== '') {
        $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
        $like    = '%' . $q . '%';
        $types  .= 'ss';
        $args[]  = $like;
        $args[]  = $like;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT u.id, u.name, u.email, u.mobile, u.address,
               u.is_active, u.created_at, u.deleted_at,
               (SELECT COUNT(*) FROM issued_books i WHERE i.student_id = u.id) AS total_issued,
               (SELECT COUNT(*) FROM issued_books i WHERE i.student_id = u.id AND i.status = 1) AS active_issued,
               (SELECT COALESCE(SUM(fine_amount), 0) FROM issued_books i WHERE i.student_id = u.id AND i.fine_status = 'Unpaid') AS outstanding_fine
          FROM users u
          $whereSql
      ORDER BY u.deleted_at IS NOT NULL, u.id DESC";

    $rows = aurora_fetch_all($sql, $types, $args);

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Users — Aurora Library</title>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <?php render_admin_sidebar($current); ?>
    <main class="app-main" id="app-main">
        <?php render_admin_header('User Management'); ?>
        <div style="max-width:1200px;margin:0 auto;">

            <?php if ($flash): ?>
                <?php echo aurora_toast_inline($flash['type'], $flash['msg']); ?>
            <?php endif; ?>

            <form method="GET" action="manage_users.php" class="form-inline" style="gap:8px;margin-bottom:12px;">
                <input type="text" name="q" class="form-control" placeholder="Search by name or email"
                       value="<?php echo htmlspecialchars($q); ?>" style="max-width:320px;">
                <label class="checkbox-inline" style="font-weight:600;">
                    <input type="checkbox" name="show_deleted" value="1" <?php echo $showDeleted ? 'checked' : ''; ?>>
                    Show deleted
                </label>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="manage_users.php" class="btn btn-default">Clear</a>
            </form>
            <p class="text-muted">
                Showing <?php echo count($rows); ?> user<?php echo count($rows) === 1 ? '' : 's'; ?>.
            </p>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Books Issued</th>
                            <th>Active Books</th>
                            <th>Outstanding Fine (Rs.)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" style="text-align:center;color:#7f8c8d;padding:14px;">No users match your search.</td></tr>
                    <?php else: foreach ($rows as $u):
                        $isDeleted = !empty($u['deleted_at']);
                        $isActive  = !$isDeleted && (int)$u['is_active'] === 1;
                        if ($isDeleted)        { $badge = '<span class="badge badge-secondary">Deleted</span>'; }
                        elseif ($isActive)     { $badge = '<span class="badge badge-success">Active</span>'; }
                        else                   { $badge = '<span class="badge badge-warning">Deactivated</span>'; }
                    ?>
                        <tr class="<?php echo $isDeleted ? 'row-deleted' : ''; ?>">
                            <td><?php echo (int)$u['id']; ?></td>
                            <td class="wrap"><?php echo htmlspecialchars($u['name']); ?></td>
                            <td class="wrap"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                            <td><?php echo $badge; ?></td>
                            <td><?php echo (int)$u['total_issued']; ?></td>
                            <td><?php echo (int)$u['active_issued']; ?></td>
                            <td><?php echo number_format((float)$u['outstanding_fine'], 2); ?></td>
                            <td>
                                <a href="view_user.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-sm btn-info">View</a>
                                <?php if (!$isDeleted): ?>
                                    <?php if ($isActive): ?>
                                        <form method="POST" action="user_action.php" style="display:inline;margin:0;" onsubmit="return confirm('Deactivate this user?');">
                                            <?php echo aurora_csrf_field(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="btn btn-sm btn-warning">Deactivate</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="user_action.php" style="display:inline;margin:0;" onsubmit="return confirm('Reactivate this user?');">
                                            <?php echo aurora_csrf_field(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="btn btn-sm btn-success">Activate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ((int)$u['active_issued'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-danger" disabled title="User must return all books before deletion">
                                            Delete (<?php echo (int)$u['active_issued']; ?> active)
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" action="user_action.php" style="display:inline;margin:0;" onsubmit="return confirm('Soft-delete this user? This hides them but preserves history.');">
                                            <?php echo aurora_csrf_field(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="POST" action="user_action.php" style="display:inline;margin:0;" onsubmit="return confirm('Restore this user?');">
                                        <?php echo aurora_csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <button type="submit" class="btn btn-sm btn-primary">Restore</button>
                                    </form>
                                <?php endif; ?>
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