<?php
    /**
     * Aurora Library — Admin: View User Detail
     */
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../admin_auth_check.php';

    $userId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$userId) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid user id.'];
        header('Location: manage_users.php');
        exit;
    }

    $user = aurora_fetch_one(
        'select id, name, email, mobile, address, is_active, created_at, deleted_at
           from users where id = ? limit 1',
        'i',
        [$userId]
    );
    if (!$user) {
        $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'User not found.'];
        header('Location: manage_users.php');
        exit;
    }

    $stats = aurora_fetch_one(
        "select
            (select count(*) from issued_books where student_id = ?) as total_issued,
            (select count(*) from issued_books where student_id = ? and status = 1) as active_issued,
            coalesce((select sum(fine_amount) from issued_books where student_id = ? and fine_status = 'Unpaid'), 0) as unpaid_total,
            coalesce((select sum(fine_amount) from issued_books where student_id = ? and fine_status = 'Paid'), 0) as paid_total",
        'iiii',
        [$userId, $userId, $userId, $userId]
    ) ?? ['total_issued' => 0, 'active_issued' => 0, 'unpaid_total' => 0, 'paid_total' => 0];

    $issued = aurora_fetch_all(
        'select id, book_name, book_no, issue_date, due_date, return_date,
                fine_amount, fine_status, status
           from issued_books
          where student_id = ?
       order by status desc, (fine_status = \'Unpaid\') desc, issue_date desc',
        'i',
        [$userId]
    );

    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    $isDeleted = !empty($user['deleted_at']);
    $isActive  = !$isDeleted && (int)$user['is_active'] === 1;

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>User: <?php echo htmlspecialchars($user['name']); ?> — Aurora Library</title>
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
        <?php render_admin_header('User Detail — ' . $user['name']); ?>
        <div style="max-width:1100px;margin:0 auto;">
            <a href="manage_users.php">&laquo; Back to user list</a>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($flash['msg']); ?>
                </div>
            <?php endif; ?>

            <div style="display:flex;gap:14px;flex-wrap:wrap;">
                <div style="flex:1 1 320px;min-width:0;">
                    <div class="card-bordered">
                        <h4 style="margin-top:0;"><?php echo htmlspecialchars($user['name']); ?>
                            <?php if ($isDeleted): ?>
                                <span class="badge badge-secondary">Deleted</span>
                            <?php elseif ($isActive): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Deactivated</span>
                            <?php endif; ?>
                        </h4>
                        <table class="table table-borderless" style="margin-bottom:0;">
                            <tr><th style="width:140px;">User ID</th><td>#<?php echo (int)$user['id']; ?></td></tr>
                            <tr><th>Email</th><td><?php echo htmlspecialchars($user['email']); ?></td></tr>
                            <tr><th>Mobile</th><td><?php echo htmlspecialchars($user['mobile'] ?? ''); ?></td></tr>
                            <tr><th>Address</th><td><?php echo htmlspecialchars($user['address'] ?? ''); ?></td></tr>
                            <tr><th>Registered</th><td><?php echo htmlspecialchars($user['created_at']); ?></td></tr>
                            <?php if ($isDeleted): ?>
                                <tr><th>Deleted At</th><td><?php echo htmlspecialchars($user['deleted_at']); ?></td></tr>
                            <?php endif; ?>
                        </table>
                        <div class="text-center" style="margin-top:12px;">
                            <?php if (!$isDeleted): ?>
                                <?php if ($isActive): ?>
                                    <form method="POST" action="user_action.php" style="display:inline;" onsubmit="return confirm('Deactivate this user?');">
                                        <?php echo aurora_csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                        <input type="hidden" name="action" value="deactivate">
                                        <button type="submit" class="btn btn-warning">Deactivate</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="user_action.php" style="display:inline;" onsubmit="return confirm('Reactivate this user?');">
                                        <?php echo aurora_csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="btn btn-success">Activate</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ((int)$stats['active_issued'] > 0): ?>
                                    <button type="button" class="btn btn-danger" disabled title="User must return all books before deletion">
                                        Delete (<?php echo (int)$stats['active_issued']; ?> active)
                                    </button>
                                <?php else: ?>
                                    <form method="POST" action="user_action.php" style="display:inline;" onsubmit="return confirm('Soft-delete this user?');">
                                        <?php echo aurora_csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="POST" action="user_action.php" style="display:inline;" onsubmit="return confirm('Restore this user?');">
                                    <?php echo aurora_csrf_field(); ?>
                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <button type="submit" class="btn btn-primary">Restore</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="flex:1 1 480px;min-width:0;">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <div class="alert alert-info" style="flex:1 1 220px;margin-bottom:8px;">
                            <strong>Total books issued:</strong> <?php echo (int)$stats['total_issued']; ?><br>
                            <strong>Active books:</strong> <?php echo (int)$stats['active_issued']; ?>
                        </div>
                        <div class="alert alert-danger" style="flex:1 1 220px;margin-bottom:8px;">
                            <strong>Outstanding fine:</strong> Rs. <?php echo number_format((float)$stats['unpaid_total'], 2); ?><br>
                            <strong>Paid fines:</strong> Rs. <?php echo number_format((float)$stats['paid_total'], 2); ?>
                        </div>
                    </div>
                    <h5 style="margin-top:8px;">Issued books</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Issue</th>
                                    <th>Due</th>
                                    <th>Returned</th>
                                    <th>Fine</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($issued)): ?>
                                <tr><td colspan="6" style="text-align:center;color:#7f8c8d;padding:14px;">No issued books.</td></tr>
                            <?php else: foreach ($issued as $row):
                                $status = aurora_fine_normalize_status($row['fine_status']);
                                $badge  = aurora_fine_status_badge_class($status);
                            ?>
                                <tr>
                                    <td class="wrap"><?php echo htmlspecialchars($row['book_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['issue_date'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['due_date'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($row['return_date'] ?? '—'); ?></td>
                                    <td><?php echo number_format((float)$row['fine_amount'], 2); ?></td>
                                    <td><span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($status); ?></span>
                                        <?php if ((int)$row['status'] === 1): ?>
                                            <br><small class="text-info">Still issued</small>
                                        <?php endif; ?>
                                    </td>
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