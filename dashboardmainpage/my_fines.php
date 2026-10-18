<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../user_auth_check.php';
    require_once __DIR__ . '/../aurora_db.php';

    $user  = aurora_current_user();
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    $rows = aurora_fetch_all(
        "select id, book_name, book_no, issue_date, due_date, return_date,
                fine_amount, fine_status, status
           from issued_books
          where student_id = ?
       order by status desc, (fine_status = 'Unpaid') desc, issue_date desc",
        'i',
        [(int)$user['id']]
    );

    $totals = aurora_fetch_one(
        "select
            sum(case when fine_status = 'Unpaid' then fine_amount else 0 end) as unpaid_total,
            sum(case when fine_status = 'Paid'   then fine_amount else 0 end) as paid_total
           from issued_books
          where student_id = ?",
        'i',
        [(int)$user['id']]
    ) ?? ['unpaid_total' => 0, 'paid_total' => 0];

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Fines — Aurora Library</title>
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
        <?php render_user_header('My Fines'); ?>
        <div style="max-width:1100px;margin:0 auto;">
            <p class="text-muted">
                Loan period: <?php echo AURORA_LOAN_DAYS; ?> days ·
                Fine: ₹<?php echo number_format(AURORA_FINE_PER_DAY, 2); ?> per overdue day
                (cap ₹<?php echo number_format(AURORA_FINE_CAP, 2); ?>)
            </p>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($flash['msg']); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-danger" style="margin-bottom:8px;">
                        <strong>Outstanding fines:</strong>
                        ₹<?php echo number_format((float)($totals['unpaid_total'] ?? 0), 2); ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-success" style="margin-bottom:8px;">
                        <strong>Paid fines:</strong>
                        ₹<?php echo number_format((float)($totals['paid_total'] ?? 0), 2); ?>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Returned On</th>
                            <th>Fine (₹)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="6" style="text-align:center;color:#7f8c8d;padding:14px;">No records.</td></tr>
                        <?php else: foreach ($rows as $r):
                            $status = aurora_fine_normalize_status($r['fine_status']);
                            $badge  = aurora_fine_status_badge_class($status);
                            $open   = ((int)$r['status'] === 1);
                        ?>
                            <tr>
                                <td class="wrap"><?php echo htmlspecialchars($r['book_name']); ?>
                                    <?php if ($open): ?>
                                        <span class="badge badge-info">Issued</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($r['issue_date'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($r['due_date'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($r['return_date'] ?? '—'); ?></td>
                                <td>₹<?php echo number_format((float)$r['fine_amount'], 2); ?></td>
                                <td><span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($status); ?></span></td>
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