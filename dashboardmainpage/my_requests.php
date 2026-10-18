<?php
    /**
     * Aurora Library — Member: My Book Requests
     */
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../user_auth_check.php';

    $user  = aurora_current_user();
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    $rows = aurora_fetch_all(
        "select r.request_id, r.request_date, r.status, r.admin_response_date,
                r.due_date, r.admin_response_note,
                b.book_id, b.book_name, b.book_no, b.book_image
           from book_requests r
           join books b on b.book_id = r.book_id
          where r.user_id = ?
       order by (r.status = 'Pending') desc, r.request_date desc",
        'i',
        [(int)$user['id']]
    );

    function aurora_request_badge($status) {
        switch ($status) {
            case 'Pending':   return '<span class="badge badge-warning">Pending</span>';
            case 'Approved':  return '<span class="badge badge-success">Approved</span>';
            case 'Declined':  return '<span class="badge badge-danger">Declined</span>';
            case 'Cancelled': return '<span class="badge badge-secondary">Cancelled</span>';
            default:          return '<span class="badge badge-light">' . htmlspecialchars($status) . '</span>';
        }
    }
    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Book Requests — Aurora Library</title>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <?php render_user_sidebar($current); ?>
    <main class="app-main" id="app-main">
        <?php render_user_header('My Book Requests'); ?>
        <div style="max-width:1100px;margin:0 auto;">
            <p class="text-muted">
                Browse the catalogue to ask the librarian for one. Pending requests can be cancelled.
            </p>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                <?php echo htmlspecialchars($flash['msg']); ?>
            </div>
        <?php endif; ?>

        <div class="text-center" style="margin-bottom:12px;">
            <a href="books.php" class="btn btn-primary">Browse Catalogue</a>
        </div>

        <table class="table-bordered" width="100%" style="text-align:center;background:#fff;">
            <tr>
                <th>Book</th>
                <th>Book No.</th>
                <th>Requested</th>
                <th>Status</th>
                <th>Admin Response</th>
                <th>Due Date</th>
                <th>Action</th>
            </tr>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7">No requests yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['book_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['book_no']); ?></td>
                    <td><?php echo htmlspecialchars($r['request_date']); ?></td>
                    <td><?php echo aurora_request_badge($r['status']); ?></td>
                    <td>
                        <?php if ($r['admin_response_date']): ?>
                            <?php echo htmlspecialchars($r['admin_response_date']); ?>
                            <?php if (!empty($r['admin_response_note'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($r['admin_response_note']); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Awaiting</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $r['due_date'] ? htmlspecialchars($r['due_date']) : '—'; ?></td>
                    <td>
                        <?php if ($r['status'] === 'Pending'): ?>
                            <form method="POST" action="cancel_request.php" style="margin:0;" onsubmit="return confirm('Cancel this request?');">
                                <?php echo aurora_csrf_field(); ?>
                                <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">Cancel</button>
                            </form>
                        <?php elseif ($r['status'] === 'Approved'): ?>
                            <a class="btn btn-sm btn-info" href="view_issued_book.php">My Books</a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>