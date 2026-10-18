<?php
/**
 * Aurora Library — Admin: Manage Fines
 *
 * Single screen for the librarian to see every fine ever recorded
 * (i.e. every returned book) with the totals and counts required by
 * the spec. Filters and searches compose via GET.
 *
 *   ?filter=all|unpaid|paid|waived|nofine
 *   ?q_user=...   (matches users.name OR users.email LIKE)
 *   ?q_book=...    (matches book_name OR book_no LIKE)
 */

require_once __DIR__ . '/../aurora_auth.php';
require_once __DIR__ . '/../aurora_db.php';

require_once __DIR__ . '/../admin_auth_check.php';

$conn = aurora_db();

$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

// ── 1. Sanitise inputs ────────────────────────────────────────────────
$filter  = $_GET['filter'] ?? 'all';
$allowed = ['all', 'unpaid', 'paid', 'waived', 'nofine'];
if (!in_array($filter, $allowed, true)) $filter = 'all';

$qUser = trim((string)($_GET['q_user'] ?? ''));
$qBook = trim((string)($_GET['q_book'] ?? ''));

// ── 2. Build WHERE dynamically with bound params ──────────────────────
$where = ['1=1'];
$types = '';
$args  = [];

if ($filter === 'unpaid') $where[] = "i.fine_status = 'Unpaid'";
if ($filter === 'paid')   $where[] = "i.fine_status = 'Paid'";
if ($filter === 'waived') $where[] = "i.fine_status = 'Waived'";
if ($filter === 'nofine') $where[] = "i.fine_status = 'No Fine'";

if ($qUser !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $like    = '%' . $qUser . '%';
    $types  .= 'ss';
    $args[]  = $like;
    $args[]  = $like;
}
if ($qBook !== '') {
    $where[] = '(i.book_name LIKE ? OR i.book_no LIKE ?)';
    $like    = '%' . $qBook . '%';
    $types  .= 'ss';
    $args[]  = $like;
    $args[]  = $like;
}

$whereSql = implode(' AND ', $where);

// ── 3. Totals & counts (independent of current filters) ──────────────
$totals = aurora_fetch_one(
    "select
        coalesce(sum(case when i.fine_status = 'Unpaid' then i.fine_amount else 0 end), 0) as unpaid_total,
        coalesce(sum(case when i.fine_status = 'Paid'   then i.fine_amount else 0 end), 0) as paid_total,
        coalesce(sum(case when i.fine_status = 'Waived' then i.fine_amount else 0 end), 0) as waived_total,
        coalesce(sum(i.fine_amount), 0) as all_total,
        sum(case when i.fine_status = 'Unpaid' then 1 else 0 end) as unpaid_count,
        sum(case when i.fine_status = 'Paid'   then 1 else 0 end) as paid_count,
        sum(case when i.fine_status = 'Waived' then 1 else 0 end) as waived_count
       from issued_books i"
) ?? [
    'unpaid_total' => 0, 'paid_total' => 0, 'waived_total' => 0, 'all_total' => 0,
    'unpaid_count' => 0, 'paid_count' => 0, 'waived_count' => 0,
];

// ── 4. Page rows ──────────────────────────────────────────────────────
$sql = "select i.id, i.book_name, i.book_no, i.issue_date, i.due_date, i.return_date,
               i.fine_amount, i.fine_status,
               u.id as user_id, u.name as user_name, u.email as user_email
          from issued_books i
          left join users u on i.student_id = u.id
         where $whereSql
      order by (i.fine_status = 'Unpaid') desc,
               (i.fine_status = 'Waived') desc,
               i.return_date desc, i.id desc";

$rows = aurora_fetch_all($sql, $types, $args);

$listedCount = count($rows);

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Fines — Aurora Library</title>
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
        <?php render_admin_header('Manage Fines'); ?>
        <div style="max-width:1300px;margin:0 auto;">
        <small class="text-muted">
            Loan period: <?php echo AURORA_LOAN_DAYS; ?> days ·
            Fine: Rs. <?php echo number_format(AURORA_FINE_PER_DAY, 2); ?>/day
            (cap Rs. <?php echo number_format(AURORA_FINE_CAP, 2); ?>)
        </small>
    </center><br>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> text-center" style="max-width:1200px;margin:0 auto 16px;">
            <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- ── Totals ──────────────────────────────────────────────────── -->
    <div class="row" style="max-width:1200px;margin:0 auto 16px;">
        <div class="col-md-3">
            <div class="stat-card stat-unpaid">
                <small>Total Unpaid Fine</small>
                <h2>Rs. <?php echo number_format((float)$totals['unpaid_total'], 2); ?></h2>
                <small><?php echo (int)$totals['unpaid_count']; ?> unpaid fines</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-paid">
                <small>Total Fine Collected</small>
                <h2>Rs. <?php echo number_format((float)$totals['paid_total'], 2); ?></h2>
                <small><?php echo (int)$totals['paid_count']; ?> paid fines</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-waived">
                <small>Total Waived Fine</small>
                <h2>Rs. <?php echo number_format((float)$totals['waived_total'], 2); ?></h2>
                <small><?php echo (int)$totals['waived_count']; ?> waived fines</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-total">
                <small>Total Fine Amount</small>
                <h2>Rs. <?php echo number_format((float)$totals['all_total'], 2); ?></h2>
                <small>all fines ever recorded</small>
            </div>
        </div>
    </div>

    <!-- ── Filters & search ──────────────────────────────────────── -->
    <div style="max-width:1200px;margin:0 auto 16px;">
        <form method="GET" action="manage_fines.php" class="form-inline" style="justify-content:center;gap:8px;">
            <input type="text" name="q_user" class="form-control" placeholder="Search by user (name or email)"
                   value="<?php echo htmlspecialchars($qUser); ?>" style="max-width:240px;">
            <input type="text" name="q_book" class="form-control" placeholder="Search by book (name or no.)"
                   value="<?php echo htmlspecialchars($qBook); ?>" style="max-width:240px;">
            <select name="filter" class="form-control">
                <?php foreach (['all'=>'All','unpaid'=>'Unpaid','paid'=>'Paid','waived'=>'Waived','nofine'=>'No Fine'] as $k => $lbl): ?>
                    <option value="<?php echo $k; ?>" <?php echo $filter === $k ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="manage_fines.php" class="btn btn-default">Clear</a>
        </form>
        <div class="filter-bar text-center" style="margin-top:8px;">
            <?php
            $qsArgs = ['q_user' => $qUser, 'q_book' => $qBook];
            foreach (['all'=>'All','unpaid'=>'Unpaid','paid'=>'Paid','waived'=>'Waived','nofine'=>'No Fine'] as $key => $label):
                $args2 = array_merge($qsArgs, ['filter' => $key]);
                $url = 'manage_fines.php?' . http_build_query($args2);
                $cls = ($filter === $key) ? 'active' : ''; ?>
                <a class="<?php echo $cls; ?>" href="<?php echo htmlspecialchars($url); ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>
        <p class="text-center text-muted" style="margin-top:6px;">
            Showing <?php echo $listedCount; ?> record<?php echo $listedCount === 1 ? '' : 's'; ?>.
        </p>
    </div>

    <!-- ── Table ─────────────────────────────────────────────────── -->
    <div class="row">
        <div class="col-md-1"></div>
        <div class="col-md-10">
            <table class="table-bordered" width="100%" style="text-align: center">
                <tr>
                    <th>User</th>
                    <th>Book</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Return Date</th>
                    <th>Days Late</th>
                    <th>Fine (Rs.)</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="empty-row">No records match your filters.</td></tr>
                <?php else: foreach ($rows as $r):
                    $status = aurora_fine_normalize_status($r['fine_status']);
                    $badge  = aurora_fine_status_badge_class($status);
                    // Days-late: prefer the actual return-vs-due gap, fall back
                    // to today-vs-due for records still flagged Unpaid/Waived.
                    $daysLate = 0;
                    if (!empty($r['due_date']) && !empty($r['return_date'])) {
                        $daysLate = max(0, (int)floor((strtotime($r['return_date']) - strtotime($r['due_date'])) / 86400));
                    } elseif (!empty($r['due_date']) && in_array($status, ['Unpaid', 'Waived'], true)) {
                        $daysLate = max(0, (int)floor((strtotime(date('Y-m-d')) - strtotime($r['due_date'])) / 86400));
                    }
                    $userName  = $r['user_name']  ?: ('User #' . (int)$r['user_id']);
                    $userEmail = $r['user_email'] ?: '';
                ?>
                    <tr>
                        <td class="wrap">
                            <strong><?php echo htmlspecialchars($userName); ?></strong>
                            <?php if ($userEmail !== ''): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($userEmail); ?></small>
                                <br><small class="text-muted">ID #<?php echo (int)$r['user_id']; ?></small>
                            <?php else: ?>
                                <br><small class="text-muted">ID #<?php echo (int)$r['user_id']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="wrap">
                            <?php echo htmlspecialchars($r['book_name']); ?>
                            <br><small class="text-muted">No. <?php echo htmlspecialchars($r['book_no']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($r['issue_date'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['due_date'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['return_date'] ?? '—'); ?></td>
                        <td><?php echo $daysLate; ?></td>
                        <td><?php echo number_format((float)$r['fine_amount'], 2); ?></td>
                        <td><span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                        <td class="actions-cell">
                            <?php if ($status === 'Unpaid'): ?>
                                <form method="POST" action="update_fine_status.php" onsubmit="return confirm('Mark this fine as Paid?');">
                                    <?php echo aurora_csrf_field(); ?>
                                    <input type="hidden" name="issue_id" value="<?php echo (int)$r['id']; ?>">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <button type="submit" class="btn btn-sm btn-success">Mark Paid</button>
                                </form>
                                <form method="POST" action="update_fine_status.php" onsubmit="return confirm('Waive this fine? The member will no longer be liable.');">
                                    <?php echo aurora_csrf_field(); ?>
                                    <input type="hidden" name="issue_id" value="<?php echo (int)$r['id']; ?>">
                                    <input type="hidden" name="action" value="waive">
                                    <button type="submit" class="btn btn-sm btn-info">Waive</button>
                                </form>
                                <form method="POST" action="update_fine_status.php" class="amount-edit-form" onsubmit="return confirm('Update fine amount?');">
                                    <?php echo aurora_csrf_field(); ?>
                                    <input type="hidden" name="issue_id" value="<?php echo (int)$r['id']; ?>">
                                    <input type="hidden" name="action" value="update_amount">
                                    <input type="number" step="0.01" min="0" max="<?php echo number_format(AURORA_FINE_CAP, 2, '.', ''); ?>" name="fine_amount" value="<?php echo htmlspecialchars((string)$r['fine_amount']); ?>" required>
                                    <button type="submit" class="btn btn-sm btn-warning">Set</button>
                                </form>
                            <?php elseif ($status === 'Paid'): ?>
                                <form method="POST" action="update_fine_status.php" onsubmit="return confirm('Reopen this fine as Unpaid?');">
                                    <?php echo aurora_csrf_field(); ?>
                                    <input type="hidden" name="issue_id" value="<?php echo (int)$r['id']; ?>">
                                    <input type="hidden" name="action" value="mark_unpaid">
                                    <button type="submit" class="btn btn-sm btn-warning">Reopen</button>
                                </form>
                                <form method="POST" action="update_fine_status.php" onsubmit="return confirm('Waive this fine? The member will no longer be liable.');">
                                    <?php echo aurora_csrf_field(); ?>
                                    <input type="hidden" name="issue_id" value="<?php echo (int)$r['id']; ?>">
                                    <input type="hidden" name="action" value="waive">
                                    <button type="submit" class="btn btn-sm btn-info">Waive</button>
                                </form>
                            <?php elseif ($status === 'Waived'): ?>
                                <form method="POST" action="update_fine_status.php" onsubmit="return confirm('Un-waive this fine? It will return to Unpaid.');">
                                    <?php echo aurora_csrf_field(); ?>
                                    <input type="hidden" name="issue_id" value="<?php echo (int)$r['id']; ?>">
                                    <input type="hidden" name="action" value="unwave">
                                    <button type="submit" class="btn btn-sm btn-warning">Un-waive</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
        <br>
        <p class="text-muted text-center">
            Tip: edit a fine amount inline via the "Set" button on Unpaid rows.
            Waiving a fine marks it Waived (informational) without erasing the amount.
        </p>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>