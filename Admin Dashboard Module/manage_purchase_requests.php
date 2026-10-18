<?php
    /**
     * Aurora Library — Admin: Manage Purchase Suggestions
     *
     * Tabs: Pending (default), Approved, Purchased, Rejected, All
     * Search across title / author / isbn / submitter name.
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

    $filter = $_GET['filter'] ?? 'pending';
    $allowed = ['pending', 'approved', 'purchased', 'declined', 'rejected', 'all'];
    if (!in_array($filter, $allowed, true)) $filter = 'pending';

    $q = aurora_get_query_string('q');

    $where = [];
    $types = '';
    $args  = [];
    if ($filter !== 'all') {
        $where[] = 'p.status = ?';
        $types  .= 's';
        $args[]  = ucfirst($filter);
    }
    if ($q !== '') {
        $where[] = '(p.book_title LIKE ? OR p.author_name LIKE ? OR p.isbn LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
        $like    = '%' . $q . '%';
        $types  .= 'sssss';
        $args[]  = $like; $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stats = aurora_fetch_one(
        "select
            sum(case when status = 'Pending'   then 1 else 0 end) as pending_count,
            sum(case when status = 'Approved'  then 1 else 0 end) as approved_count,
            sum(case when status = 'Purchased' then 1 else 0 end) as purchased_count,
            sum(case when status = 'Declined'  then 1 else 0 end) as declined_count,
            sum(case when status = 'Rejected'  then 1 else 0 end) as rejected_count,
            coalesce(sum(estimated_price), 0) as total_estimated
           from purchase_requests"
    ) ?? ['pending_count'=>0,'approved_count'=>0,'purchased_count'=>0,'declined_count'=>0,'rejected_count'=>0,'total_estimated'=>0];

    $rows = aurora_fetch_all(
        "select p.purchase_request_id, p.book_title, p.author_name, p.isbn, p.reason,
                p.estimated_price, p.status, p.created_at, p.updated_at,
                p.admin_response_note,
                u.id as user_id, u.name as user_name, u.email as user_email
           from purchase_requests p
           join users u on u.id = p.user_id
           $whereSql
       order by (p.status = 'Pending') desc, p.created_at desc",
        $types,
        $args
    );

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_admin_sidebar.php';

    function aurora_admin_pr_badge($status) {
        switch ($status) {
            case 'Pending':   return '<span class="badge badge-warning">Pending</span>';
            case 'Approved':  return '<span class="badge badge-info">Approved</span>';
            case 'Purchased': return '<span class="badge badge-success">Purchased</span>';
            case 'Declined':  return '<span class="badge badge-danger">Declined</span>';
            case 'Rejected':  return '<span class="badge badge-danger">Declined</span>';
            default:          return '<span class="badge badge-light">' . htmlspecialchars($status) . '</span>';
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1">
    <title>Purchase Suggestions</title>
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <?php render_admin_sidebar($current); ?>
    <main class="app-main" id="app-main">
        <?php render_admin_header('Purchase Suggestions'); ?>
        <div style="max-width:1300px;margin:0 auto;">
        <h3 class="text-center">Book Purchase Suggestions</h3>
        <p class="text-center text-muted">
            Members can ask the library to consider buying a book. Review each suggestion and decide whether to approve, mark as purchased, or reject. This is <strong>not</strong> a payment flow.
        </p>

        <?php if ($flash): ?>
            <?php echo aurora_toast_inline($flash['type'], $flash['msg']); ?>
        <?php endif; ?>

        <div class="text-center" style="margin-bottom:12px;">
            <span class="stat-mini pending">Pending: <?php echo (int)$stats['pending_count']; ?></span>
            <span class="stat-mini approved">Approved: <?php echo (int)$stats['approved_count']; ?></span>
            <span class="stat-mini purchased">Purchased: <?php echo (int)$stats['purchased_count']; ?></span>
            <span class="stat-mini declined">Declined: <?php echo (int)$stats['declined_count']; ?></span>
            <span class="stat-mini" style="background:#34495e;">Total est.: Rs. <?php echo number_format((float)$stats['total_estimated'], 2); ?></span>
        </div>

        <div class="tab-bar text-center" style="margin-bottom:12px;">
            <?php foreach ($allowed as $key):
                $cls = ($filter === $key) ? 'active' : '';
                $url = 'manage_purchase_requests.php?filter=' . $key . '&q=' . urlencode($q); ?>
                <a class="<?php echo $cls; ?>" href="<?php echo $url; ?>"><?php echo ucfirst($key); ?></a>
            <?php endforeach; ?>
        </div>

        <form method="GET" action="manage_purchase_requests.php" class="form-inline" style="justify-content:center;gap:8px;margin-bottom:12px;">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
                   class="form-control" placeholder="Search title, author, ISBN, or member name" style="min-width:360px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="manage_purchase_requests.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn-default">Reset</a>
        </form>

        <p class="text-center text-muted">
            <?php echo count($rows); ?> record<?php echo count($rows) === 1 ? '' : 's'; ?>
            <?php if ($q !== ''): ?>
                for "<strong><?php echo htmlspecialchars($q); ?></strong>"
            <?php endif; ?>
        </p>

        <table class="table-bordered" width="100%" style="text-align:center;background:#fff;">
            <tr>
                <th>Submitted By</th>
                <th>Title</th>
                <th>Author</th>
                <th>ISBN</th>
                <th>Est. Price</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8">No suggestions in this view.</td></tr>
            <?php else: foreach ($rows as $r):
                $status = $r['status'];
            ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($r['user_name']); ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($r['user_email']); ?></small>
                        <br><small class="text-muted"><?php echo htmlspecialchars($r['created_at']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($r['book_title']); ?></td>
                    <td><?php echo htmlspecialchars($r['author_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['isbn'] ?? '—'); ?></td>
                    <td><?php echo $r['estimated_price'] !== null ? 'Rs. ' . number_format((float)$r['estimated_price'], 2) : '—'; ?></td>
                    <td style="max-width:240px;">
                        <?php if (!empty($r['reason'])): ?>
                            <small><?php echo nl2br(htmlspecialchars($r['reason'])); ?></small>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                        <?php if (!empty($r['admin_response_note'])): ?>
                            <hr style="margin:6px 0;">
                            <small class="text-muted"><strong>Notes:</strong><br><?php echo nl2br(htmlspecialchars($r['admin_response_note'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo aurora_admin_pr_badge($status); ?></td>
                    <td>
                        <?php if ($status === 'Pending'): ?>
                            <form method="POST" action="respond_purchase_request.php" style="margin-bottom:6px;" onsubmit="return confirm('Approve this suggestion?');">
                                <?php echo aurora_csrf_field(); ?>
                                <input type="hidden" name="request_id" value="<?php echo (int)$r['purchase_request_id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="text" name="note" class="form-control" placeholder="Optional note" style="margin-bottom:6px;font-size:12px;">
                                <button type="submit" class="btn btn-sm btn-info btn-block">Approve</button>
                            </form>
                            <form method="POST" action="respond_purchase_request.php" onsubmit="return confirm('Decline this suggestion?');">
                                <?php echo aurora_csrf_field(); ?>
                                <input type="hidden" name="request_id" value="<?php echo (int)$r['purchase_request_id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-sm btn-danger btn-block">Decline</button>
                            </form>
                        <?php elseif ($status === 'Approved'): ?>
                            <form method="POST" action="respond_purchase_request.php" style="margin-bottom:6px;" onsubmit="return confirm('Mark as Purchased?');">
                                <?php echo aurora_csrf_field(); ?>
                                <input type="hidden" name="request_id" value="<?php echo (int)$r['purchase_request_id']; ?>">
                                <input type="hidden" name="action" value="purchase">
                                <input type="text" name="note" class="form-control" placeholder="Optional note" style="margin-bottom:6px;font-size:12px;">
                                <button type="submit" class="btn btn-sm btn-success btn-block">Mark Purchased</button>
                            </form>
                            <form method="POST" action="respond_purchase_request.php" onsubmit="return confirm('Decline this suggestion?');">
                                <?php echo aurora_csrf_field(); ?>
                                <input type="hidden" name="request_id" value="<?php echo (int)$r['purchase_request_id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-sm btn-warning btn-block">Decline</button>
                            </form>
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