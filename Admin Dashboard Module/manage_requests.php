<?php
    /**
     * Aurora Library — Admin: Manage Book Requests
     *
     * Two tabs on the same page:
     *   ?view=active   — pending + recent admin action (default)
     *   ?view=history  — full audit trail (approved / rejected / cancelled)
     *
     * Both tabs support search (user name/email OR book name/no).
     */

    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';
    require_once __DIR__ . '/../aurora_validation.php';
    require_once __DIR__ . '/../admin_auth_check.php';

    if (!defined('AURORA_BORROW_LIMIT')) define('AURORA_BORROW_LIMIT', 3);

    $flash = aurora_flash_pull();
    if (!$flash && !empty($_SESSION['_flash'])) {
        // Backwards-compat: any handler still writing to the legacy key
        // still gets rendered. Remove the entry so it isn't double-shown.
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
    }

    $view = $_GET['view'] ?? 'active';
    if (!in_array($view, ['active', 'history'], true)) $view = 'active';

    $q = aurora_get_query_string('q');

    // ── WHERE ─────────────────────────────────────────────────────────
    $where  = [];
    $types  = '';
    $args   = [];

    if ($view === 'active') {
        $where[] = "r.status = 'Pending'";
    } else {
        // History: everything that's been decided (or cancelled) but NOT
        // pending — the active tab already covers Pending.
        $where[] = "r.status <> 'Pending'";
    }

    if ($q !== '') {
        $where[] = '(u.name LIKE ? OR u.email LIKE ? OR b.book_name LIKE ? OR b.book_no LIKE ?)';
        $like    = '%' . $q . '%';
        $types  .= 'ssss';
        $args[]  = $like; $args[] = $like; $args[] = $like; $args[] = $like;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // ── Stats for the active tab ──────────────────────────────────────
    $stats = aurora_fetch_one(
        "select
            sum(case when r.status = 'Pending'   then 1 else 0 end) as pending_count,
            sum(case when r.status = 'Approved'  then 1 else 0 end) as approved_count,
            sum(case when r.status = 'Declined'  then 1 else 0 end) as declined_count,
            sum(case when r.status = 'Cancelled' then 1 else 0 end) as cancelled_count
           from book_requests r"
    ) ?? ['pending_count' => 0, 'approved_count' => 0, 'declined_count' => 0, 'cancelled_count' => 0];

    $sql = "
        select r.request_id, r.user_id, r.book_id, r.request_date,
               r.status, r.admin_response_date, r.admin_response_note,
               r.due_date,
               u.name as user_name, u.email as user_email,
               b.book_name, b.book_no, b.book_image, b.book_qty,
               (select count(*) from issued_books i
                 where i.student_id = r.user_id and i.status = 1) as user_active_count
          from book_requests r
          join users u on u.id = r.user_id
          join books b on b.book_id = r.book_id
          $whereSql
      order by (r.status = 'Pending') desc, r.request_date desc";

    $rows = aurora_fetch_all($sql, $types, $args);

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_admin_sidebar.php';

    function aurora_admin_request_badge($status) {
        switch ($status) {
            case 'Pending':   return '<span class="badge badge-warning">Pending</span>';
            case 'Approved':  return '<span class="badge badge-success">Approved</span>';
            case 'Declined':  return '<span class="badge badge-danger">Declined</span>';
            case 'Cancelled': return '<span class="badge badge-secondary">Cancelled</span>';
            default:          return '<span class="badge badge-light">' . htmlspecialchars($status) . '</span>';
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Book Requests — Aurora Library</title>
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
        <?php render_admin_header('Book Requests'); ?>
        <div style="max-width:1300px;margin:0 auto;">
            <h3 class="text-center">Book Requests</h3>

        <?php if ($flash): ?>
            <?php echo aurora_toast_inline($flash['type'], $flash['msg']); ?>
        <?php endif; ?>

        <div class="text-center" style="margin-bottom:12px;">
            <span class="stat-mini pending">Pending: <?php echo (int)$stats['pending_count']; ?></span>
            <span class="stat-mini approved">Approved: <?php echo (int)$stats['approved_count']; ?></span>
            <span class="stat-mini declined">Declined: <?php echo (int)$stats['declined_count']; ?></span>
            <span class="stat-mini cancelled">Cancelled: <?php echo (int)$stats['cancelled_count']; ?></span>
        </div>

        <div class="tab-bar text-center" style="margin-bottom:12px;">
            <a class="<?php echo $view === 'active'  ? 'active' : ''; ?>"
               href="?view=active&q=<?php echo urlencode($q); ?>">Active (Pending)</a>
            <a class="<?php echo $view === 'history' ? 'active' : ''; ?>"
               href="?view=history&q=<?php echo urlencode($q); ?>">Request History</a>
        </div>

        <form method="GET" action="manage_requests.php" class="form-inline" style="justify-content:center;gap:8px;margin-bottom:12px;">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
                   class="form-control" placeholder="Search by user (name/email) or book (name/no.)" style="min-width:360px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="manage_requests.php?view=<?php echo htmlspecialchars($view); ?>" class="btn btn-default">Reset</a>
        </form>

        <p class="text-center text-muted">
            <?php echo count($rows); ?> record<?php echo count($rows) === 1 ? '' : 's'; ?>
            <?php if ($q !== ''): ?>
                for "<strong><?php echo htmlspecialchars($q); ?></strong>"
            <?php endif; ?>
        </p>

        <table class="table-bordered" width="100%" style="text-align:center;background:#fff;">
            <tr>
                <th>User</th>
                <th>Book</th>
                <th>Stock / User Active</th>
                <th>Requested</th>
                <th>Status</th>
                <th>Due Date</th>
                <th>Admin Response</th>
                <th>Action</th>
            </tr>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8">No requests in this view.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($r['user_name']); ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($r['user_email']); ?></small>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($r['book_name']); ?>
                        <br><small class="text-muted">No. <?php echo htmlspecialchars($r['book_no']); ?></small>
                    </td>
                    <td>
                        Stock: <strong><?php echo (int)$r['book_qty']; ?></strong><br>
                        User active: <strong><?php echo (int)$r['user_active_count']; ?></strong> / <?php echo AURORA_BORROW_LIMIT; ?>
                    </td>
                    <td><?php echo htmlspecialchars($r['request_date']); ?></td>
                    <td><?php echo aurora_admin_request_badge($r['status']); ?></td>
                    <td><?php echo $r['due_date'] ? htmlspecialchars($r['due_date']) : '—'; ?></td>
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
                    <td>
                        <?php if ($r['status'] === 'Pending'): ?>
                            <?php
                                $canApprove =
                                    (int)$r['book_qty'] > 0 &&
                                    (int)$r['user_active_count'] < AURORA_BORROW_LIMIT;
                            ?>
                            <form method="POST" action="respond_request.php" style="margin-bottom:6px;" onsubmit="return confirm('Approve this request and issue the book?');">
                                <?php echo aurora_csrf_field(); ?>
                                <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="text" name="note" class="form-control" placeholder="Optional note" style="margin-bottom:6px;font-size:12px;">
                                <button type="submit" class="btn btn-sm btn-success btn-block" <?php echo $canApprove ? '' : 'disabled title="Cannot approve — see Stock / User Active counts"'; ?>>
                                    Approve
                                </button>
                            </form>
                            <form method="POST" action="respond_request.php" onsubmit="return confirm('Decline this request?');">
                                <?php echo aurora_csrf_field(); ?>
                                <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-sm btn-danger btn-block">Decline</button>
                            </form>
                            <?php if (!$canApprove): ?>
                                <small class="text-danger" style="display:block;margin-top:4px;">
                                    <?php echo (int)$r['book_qty'] <= 0
                                        ? 'Out of stock.'
                                        : 'User at borrow limit.'; ?>
                                </small>
                            <?php endif; ?>
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