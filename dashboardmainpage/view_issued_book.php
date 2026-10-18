<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../user_auth_check.php';
    require_once __DIR__ . '/../aurora_db.php';

    $user = aurora_current_user();
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    $rows = aurora_fetch_all(
        'select id, book_name, book_author, book_no, issue_date, due_date
           from issued_books
          where student_id = ? and status = 1
       order by issue_date desc, id desc',
        'i',
        [(int)$user['id']]
    );

    foreach ($rows as &$r) {
        $calc = aurora_fine_compute($r['issue_date'], null);
        $r['_days_late'] = $calc['days_late'];
        $r['_fine']      = $calc['fine'];
        $r['_status']    = $calc['status'];
    }
    unset($r);

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Borrowed Books — Aurora Library</title>
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
        <?php render_user_header('My Borrowed Books'); ?>

        <div style="max-width:1100px;margin:0 auto;">
            <div class="d-flex justify-content-between align-items-center" style="margin-bottom:12px;">
                <a href="books.php" class="btn btn-primary">Browse Catalogue</a>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($flash['msg']); ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Author</th>
                            <th>Number</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th>Fine (Rs.)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" style="text-align:center;color:#7f8c8d;padding:14px;">No books currently issued to you. <a href="books.php">Browse the catalogue</a> to request one.</td></tr>
                    <?php else: foreach ($rows as $row):
                        $badge = aurora_fine_status_badge_class($row['_status']);
                        $isOverdue = (int)$row['_days_late'] > 0;
                    ?>
                        <tr class="<?php echo $isOverdue ? 'row-overdue' : ''; ?>">
                            <td class="wrap"><?php echo htmlspecialchars($row['book_name']);?></td>
                            <td><?php echo htmlspecialchars($row['book_author']);?></td>
                            <td><?php echo htmlspecialchars($row['book_no']);?></td>
                            <td><?php echo htmlspecialchars($row['issue_date'] ?? '');?></td>
                            <td><?php echo htmlspecialchars($row['due_date'] ?? aurora_fine_compute_due_date($row['issue_date']) ?? '—');?></td>
                            <td class="<?php echo $isOverdue ? 'days-late-cell' : ''; ?>"><?php echo (int)$row['_days_late']; ?></td>
                            <td>
                                Rs. <?php echo number_format((float)$row['_fine'], 2); ?>
                                <br><span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($row['_status']); ?></span>
                            </td>
                            <td>
                                <form method="POST" action="return_book.php" style="margin:0;" onsubmit="return confirm('Return this book?');">
                                    <?php echo aurora_csrf_field(); ?>
                                    <input type="hidden" name="issue_id" value="<?php echo (int)$row['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Return</button>
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