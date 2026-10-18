<?php
    /**
     * Aurora Library — Member: My Purchase Suggestions
     */
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../user_auth_check.php';

    $user  = aurora_current_user();
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    $rows = aurora_fetch_all(
        'select purchase_request_id, book_title, author_name, isbn, reason,
                estimated_price, status, created_at, updated_at,
                admin_response_note
           from purchase_requests
          where user_id = ?
       order by created_at desc',
        'i',
        [(int)$user['id']]
    );

    function aurora_pr_badge($status) {
        switch ($status) {
            case 'Pending':   return '<span class="badge badge-warning">Pending</span>';
            case 'Approved':  return '<span class="badge badge-info">Approved</span>';
            case 'Purchased': return '<span class="badge badge-success">Purchased</span>';
            case 'Declined':  return '<span class="badge badge-danger">Declined</span>';
            case 'Rejected':  return '<span class="badge badge-danger">Declined</span>';
            default:          return '<span class="badge badge-light">' . htmlspecialchars($status) . '</span>';
        }
    }
    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>My Book Suggestions — Aurora Library</title>
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <?php render_user_sidebar($current); ?>
    <main class="app-main" id="app-main">
        <?php render_user_header('My Book Suggestions'); ?>
        <div style="max-width:1100px;margin:0 auto;">
            <p class="text-muted">
                These are titles you've asked the librarian to consider purchasing for the library.
            </p>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($flash['msg']); ?>
                </div>
            <?php endif; ?>

            <div style="margin-bottom:12px;">
                <a href="suggest_book.php" class="btn btn-primary">+ Suggest Another Book</a>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Est. Price</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Librarian Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="7" style="text-align:center;color:#7f8c8d;padding:14px;">No suggestions yet.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr>
                                <td class="wrap"><?php echo htmlspecialchars($r['book_title']); ?></td>
                                <td><?php echo htmlspecialchars($r['author_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($r['isbn'] ?? '—'); ?></td>
                                <td><?php echo $r['estimated_price'] !== null ? 'Rs. ' . number_format((float)$r['estimated_price'], 2) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                                <td><?php echo aurora_pr_badge($r['status']); ?></td>
                                <td class="wrap">
                                    <?php if (!empty($r['admin_response_note'])): ?>
                                        <?php echo htmlspecialchars($r['admin_response_note']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
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