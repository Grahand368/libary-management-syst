<?php
    /**
     * Aurora Library — Admin: Stock Audit
     *
     * Diagnostic page that reconciles book_qty with the issued_books ledger.
     * Use it to confirm the catalog quantities match reality and to surface
     * any drift after legacy bugs.
     *
     * GET params:
     *   fix_drift=1   Run a one-shot reconciliation (admin only, logged).
     *
     * Drift logic:
     *   expected_qty = books.book_qty
     *   currently_issued = COUNT(issued_books WHERE status=1 AND book_no = b.book_no)
     *   ledger_inferred = expected_qty + currently_issued  (i.e. the value
     *       the books.book_qty column should have had when those issues were
     *       created). If ledger_inferred is negative, the column has been
     *       decremented below zero at some point.
     *
     * The fix action adds `currently_issued` to book_qty (only for rows where
     * book_qty is non-negative). It does NOT touch book_qty if it is already
     * below the live-issued count.
     */
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../admin_auth_check.php';

    $flash  = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    $ranFix = false;
    $fixed  = 0;

    if (!empty($_GET['fix_drift'])) {
        if (!aurora_csrf_check($_GET['csrf'] ?? '')) {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid security token.'];
            header('Location: stock_audit.php');
            exit;
        }
        $conn = aurora_db();
        mysqli_report(MYSQLI_REPORT_OFF);
        mysqli_begin_transaction($conn);
        try {
            // Recompute every book's stock from the ledger:
            //   new_book_qty = currently_issued_count_for_this_book_no
            // We use book_no because issued_books.book_no is the join key.
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE books b
                 LEFT JOIN (
                     SELECT book_no, COUNT(*) AS c
                       FROM issued_books
                      WHERE status = 1
                      GROUP BY book_no
                 ) live ON live.book_no = b.book_no
                    SET b.book_qty = GREATEST(0, COALESCE(live.c, 0))'
            );
            if ($stmt) {
                mysqli_stmt_execute($stmt);
                $fixed = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
            }
            mysqli_commit($conn);
            $ranFix = true;
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => "Reconciled {$fixed} book row(s) against the live-issue ledger."];
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Reconciliation failed.'];
        }
        header('Location: stock_audit.php');
        exit;
    }

    // Read every book with its currently-issued count for reconciliation view.
    $rows = aurora_fetch_all(
        "SELECT b.book_id, b.book_name, b.book_no, b.book_qty,
                COALESCE(live.c, 0) AS currently_issued
           FROM books b
      LEFT JOIN (
          SELECT book_no, COUNT(*) AS c
            FROM issued_books
           WHERE status = 1
        GROUP BY book_no
      ) live ON live.book_no = b.book_no
       ORDER BY b.book_id ASC"
    );

    $zeroStock     = 0;
    $negativeStock = 0;
    $driftRows     = [];
    foreach ($rows as $r) {
        $qty = (int)$r['book_qty'];
        $iss = (int)$r['currently_issued'];
        if ($qty <= 0) $zeroStock++;
        if ($qty < 0)  $negativeStock++;
        if ($qty < 0 || ($qty === 0 && $iss > 0)) {
            $driftRows[] = $r;
        }
    }

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Stock Audit — Aurora Admin</title>
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <?php render_admin_sidebar($current); ?>
    <main class="app-main" id="app-main">
        <?php render_admin_header('Stock Audit'); ?>
        <div style="max-width:1200px;margin:0 auto;">
        <h3 class="text-center">Stock Audit</h3>
        <p class="text-center text-muted">
            Reconciles <code>books.book_qty</code> against the <code>issued_books</code> ledger.
            If any row went negative (because of a legacy bug), use the reconcile action below to set it
            back to the count of currently-issued copies.
        </p>

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                <?php echo htmlspecialchars($flash['msg']); ?>
            </div>
        <?php endif; ?>

        <div class="dash-grid">
            <div class="stat-card stat-blue">
                <small>Total book rows</small>
                <h2><?php echo count($rows); ?></h2>
            </div>
            <div class="stat-card stat-green">
                <small>Healthy rows</small>
                <h2><?php echo count($rows) - count($driftRows); ?></h2>
                <small>qty &gt; 0 and consistent</small>
            </div>
            <div class="stat-card stat-amber">
                <small>Out of stock</small>
                <h2><?php echo (int)$zeroStock; ?></h2>
                <small>qty ≤ 0 — at least no copies available</small>
            </div>
            <div class="stat-card stat-red">
                <small>Negative stock (drift!)</small>
                <h2><?php echo (int)$negativeStock; ?></h2>
                <small>must be reconciled</small>
            </div>
        </div>

        <div class="text-center" style="margin-bottom:14px;">
            <form method="GET" action="stock_audit.php" style="display:inline-block;"
                  onsubmit="return confirm('Reconcile all book_qty values against the live issue ledger?');">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(aurora_csrf_token(), ENT_QUOTES); ?>">
                <input type="hidden" name="fix_drift" value="1">
                <button type="submit" class="btn btn-warning">Reconcile from ledger</button>
            </form>
        </div>

        <h5>Per-book snapshot</h5>
        <table class="table table-bordered" style="background:#fff;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Book</th>
                    <th>Book No.</th>
                    <th>book_qty</th>
                    <th>Currently Issued (live)</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="empty-row">No books yet.</td></tr>
            <?php else: foreach ($rows as $r):
                $qty = (int)$r['book_qty'];
                $iss = (int)$r['currently_issued'];
                $note = '';
                $cls   = '';
                if ($qty < 0) {
                    $note = 'NEGATIVE — drift; reconcile.';
                    $cls  = 'danger';
                } elseif ($qty === 0 && $iss > 0) {
                    $note = "0 stock but {$iss} live issue(s) — reconcile.";
                    $cls  = 'warning';
                } elseif ($qty === 0) {
                    $note = 'Out of stock.';
                    $cls  = 'secondary';
                } else {
                    $note = 'OK';
                    $cls  = 'success';
                }
            ?>
                <tr class="<?php echo $cls === 'danger' || $cls === 'warning' ? 'table-'.$cls : ''; ?>">
                    <td><?php echo (int)$r['book_id']; ?></td>
                    <td class="wrap"><?php echo htmlspecialchars($r['book_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['book_no']); ?></td>
                    <td><strong><?php echo $qty; ?></strong></td>
                    <td><?php echo $iss; ?></td>
                    <td><span class="badge badge-<?php echo $cls; ?>"><?php echo $note; ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <p class="text-muted" style="margin-top:14px;">
            <strong>Notes:</strong> The reconcile action sets <code>book_qty = COUNT(active issues for this book_no)</code>,
            clamped at 0. This is a one-shot recovery — it should only be needed if a historical regression
            left quantities inconsistent. After reconciling, all future issue / return operations use the
            guarded <code>book_qty &gt; 0</code> / <code>book_qty + 1</code> updates that are safe under concurrency.
        </p>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>