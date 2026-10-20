<?php
    /**
     * Aurora Library — Member: Dashboard
     *
     * Three logical zones:
     *   1. Stats      — Currently Issued · Available Books · Pending Requests · Active Fines
     *   2. Quick actions — Browse / Issued / Request / My Requests / Suggest / My Suggestions / Fines / Profile
     *   3. Recent activity — last issues, last borrow requests, last fine updates
     */

    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../user_auth_check.php';

    $user = aurora_current_user();
    $uid  = (int)$user['id'];

    // ── 1. Stat cards ─────────────────────────────────────────────────
    $stats = aurora_fetch_one(
        "SELECT
            (SELECT COUNT(*) FROM issued_books
               WHERE student_id = ? AND status = 1)                       AS active_issued,
            (SELECT COUNT(*) FROM books   WHERE book_qty > 0)              AS available_books,
            (SELECT COUNT(*) FROM book_requests
               WHERE user_id  = ? AND status  = 'Pending')                 AS pending_requests,
            (SELECT COALESCE(SUM(fine_amount), 0) FROM issued_books
               WHERE student_id = ? AND fine_status = 'Unpaid')            AS outstanding_fine",
        'iiii',
        [$uid, $uid, $uid]
    ) ?: ['active_issued' => 0, 'available_books' => 0, 'pending_requests' => 0, 'outstanding_fine' => 0];

    // ── 2. Recent issued books (status = 1) ────────────────────────────
    $recentIssued = aurora_fetch_all(
        'SELECT id, book_name, book_no, issue_date, due_date, fine_amount, fine_status
           FROM issued_books
          WHERE student_id = ?
       ORDER BY issue_date DESC, id DESC
          LIMIT 5',
        'i',
        [$uid]
    );
    foreach ($recentIssued as &$r) {
        $calc = aurora_fine_compute($r['issue_date'], null);
        $r['_days_late'] = $calc['days_late'];
        $r['_fine']      = $calc['fine'];
    }
    unset($r);

    // ── 3. Recent borrow requests ──────────────────────────────────────
    $recentRequests = aurora_fetch_all(
        'SELECT r.request_id, r.request_date, r.status,
                b.book_name, b.book_no
           FROM book_requests r
           JOIN books b ON b.book_id = r.book_id
          WHERE r.user_id = ?
       ORDER BY r.request_date DESC
          LIMIT 5',
        'i',
        [$uid]
    );

    // ── 4. Recent fine updates (anything with fine_amount > 0 or fine_status != 'No Fine') ──
    $recentFines = aurora_fetch_all(
        'SELECT id, book_name, book_no, fine_amount, fine_status, return_date, due_date
           FROM issued_books
          WHERE student_id = ?
            AND (fine_amount > 0 OR fine_status <> \'No Fine\')
       ORDER BY COALESCE(return_date, due_date, issue_date) DESC, id DESC
          LIMIT 5',
        'i',
        [$uid]
    );

    // ── 5. Recent purchase suggestions ────────────────────────────────
    $recentSuggests = aurora_fetch_all(
        'SELECT purchase_request_id, book_title, status, created_at
           FROM purchase_requests
          WHERE user_id = ?
       ORDER BY created_at DESC
          LIMIT 4',
        'i',
        [$uid]
    );

    // ── 6. Activity feed (issues + requests + suggestions + fines) ─────
    $feed = [];
    foreach ($recentIssued as $r) {
        $feed[] = [
            'when'  => $r['issue_date'],
            'icon'  => 'issued',
            'title' => 'Borrowed: ' . $r['book_name'],
            'meta'  => 'Issued ' . ($r['issue_date'] ?? '') . ' · due ' . ($r['due_date'] ?? '—'),
        ];
    }
    foreach ($recentRequests as $r) {
        $feed[] = [
            'when'  => $r['request_date'],
            'icon'  => 'request',
            'title' => 'Request: ' . $r['book_name'],
            'meta'  => 'Status: ' . $r['status'],
        ];
    }
    foreach ($recentFines as $r) {
        $feed[] = [
            'when'  => $r['return_date'] ?: ($r['due_date'] ?: ''),
            'icon'  => 'fine',
            'title' => 'Fine update: ' . $r['book_name'],
            'meta'  => 'Rs. ' . number_format((float)$r['fine_amount'], 2) . ' · ' . $r['fine_status'],
        ];
    }
    foreach ($recentSuggests as $r) {
        $feed[] = [
            'when'  => $r['created_at'],
            'icon'  => 'suggest',
            'title' => 'Suggestion: ' . $r['book_title'],
            'meta'  => 'Status: ' . $r['status'],
        ];
    }
    usort($feed, fn($a, $b) => strcmp((string)($b['when'] ?? ''), (string)($a['when'] ?? '')));
    $feed = array_slice($feed, 0, 6);

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Dashboard — Aurora Library</title>
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <?php render_user_sidebar($current); ?>

    <main class="app-main" id="app-main">
        <header class="app-header">
            <div class="header-left">
                <button class="menu-toggle" data-menu-toggle aria-label="Open menu">☰</button>
                <div class="header-title">Welcome, <?= htmlspecialchars($user['name']) ?></div>
            </div>
            <div class="header-right">
                <span class="text-muted" style="font-size: 13px;">
                    <?= htmlspecialchars($user['email']) ?>
                </span>
            </div>
        </header>

        <div style="max-width:1200px;margin:0 auto;">

            <!-- ── Stats ───────────────────────────────────────────────── -->
            <div class="section-title">My Library at a glance</div>
            <div class="dash-grid">
                <div class="stat-card stat-blue">
                    <small>Currently Issued</small>
                    <h2><?php echo (int)$stats['active_issued']; ?></h2>
                    <small><a href="view_issued_book.php">View borrowed books →</a></small>
                </div>
                <div class="stat-card stat-teal">
                    <small>Available Books</small>
                    <h2><?php echo (int)$stats['available_books']; ?></h2>
                    <small><a href="books.php">Browse the catalogue →</a></small>
                </div>
                <div class="stat-card stat-amber">
                    <small>Pending Requests</small>
                    <h2><?php echo (int)$stats['pending_requests']; ?></h2>
                    <small><a href="my_requests.php">Manage my requests →</a></small>
                </div>
                <div class="stat-card stat-red">
                    <small>Active Fines</small>
                    <h2>Rs. <?php echo number_format((float)$stats['outstanding_fine'], 2); ?></h2>
                    <small><a href="my_fines.php">View fines →</a></small>
                </div>
            </div>

            <!-- ── Quick actions ──────────────────────────────────────── -->
            <div class="section-title">Quick Actions</div>
            <div class="quick-actions">
                <a class="quick-btn" href="books.php">📚 Browse Books</a>
                <a class="quick-btn" href="view_issued_book.php">📖 My Issued Books</a>
                <a class="quick-btn" href="books.php#request">✉ Request a Book</a>
                <a class="quick-btn" href="my_requests.php">📨 My Requests</a>
                <a class="quick-btn" href="suggest_book.php">💡 Suggest a Book</a>
                <a class="quick-btn" href="my_purchase_suggestions.php">🛒 My Purchase Suggestions</a>
                <a class="quick-btn" href="my_fines.php">💸 My Fines</a>
                <a class="quick-btn" href="view_profile.php">👤 Profile</a>
            </div>

            <!-- ── Two-column: recent issues + recent requests / fines ── -->
            <div class="row" style="display:flex;gap:14px;flex-wrap:wrap;">
                <div class="card-bordered" style="flex:1 1 480px;min-width:0;">
                    <h5 style="margin-top:0;">Recently Issued Books</h5>
                    <div class="table-responsive" style="box-shadow:none;">
                        <table>
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Issue Date</th>
                                        <th>Due Date</th>
                                        <th>Days Late</th>
                                        <th>Fine</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($recentIssued)): ?>
                                    <tr><td colspan="5" class="empty-row">No books currently issued. <a href="books.php">Browse the catalogue</a>.</td></tr>
                                <?php else: foreach ($recentIssued as $r):
                                    $isLate = (int)$r['_days_late'] > 0;
                                ?>
                                    <tr>
                                        <td class="wrap"><strong><?php echo htmlspecialchars($r['book_name']); ?></strong>
                                            <br><small class="text-muted">No. <?php echo htmlspecialchars($r['book_no']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($r['issue_date'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($r['due_date'] ?? '—'); ?></td>
                                        <td class="<?php echo $isLate ? 'days-late-cell' : ''; ?>"><?php echo (int)$r['_days_late']; ?></td>
                                        <td>
                                            <?php if ((float)$r['_fine'] > 0): ?>
                                                <span class="days-late-cell">Rs. <?php echo number_format((float)$r['_fine'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-success">Rs. 0.00</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                    </div>
                </div>

                <div class="card-bordered" style="flex:1 1 380px;min-width:0;">
                    <h5 style="margin-top:0;">Recent Book Requests</h5>
                    <?php if (empty($recentRequests)): ?>
                        <p class="empty-row" style="margin:0;">No requests yet. <a href="books.php">Browse the catalogue</a> to request a book.</p>
                    <?php else: ?>
                        <?php foreach ($recentRequests as $r): ?>
                            <div class="feed-row">
                                <div class="feed-icon request">✉</div>
                                <div style="flex:1;min-width:0;">
                                    <div class="feed-title"><?php echo htmlspecialchars($r['book_name']); ?></div>
                                    <div class="feed-meta">
                                        <?php echo htmlspecialchars($r['status']); ?>
                                        &middot; <?php echo htmlspecialchars($r['request_date']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    <a href="my_requests.php" class="btn btn-sm btn-default" style="margin-top:8px;">See all requests</a>
                </div>
            </div>

            <!-- ── Recent Fine updates + suggestions ─────────────────── -->
            <div class="row" style="display:flex;gap:14px;flex-wrap:wrap;">
                <div class="card-bordered" style="flex:1 1 480px;min-width:0;">
                    <h5 style="margin-top:0;">Recent Fine Updates</h5>
                    <?php if (empty($recentFines)): ?>
                        <p class="empty-row" style="margin:0;">No fines on record. Keep it up!</p>
                    <?php else: ?>
                        <div class="table-responsive" style="box-shadow:none;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Fine</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentFines as $r): ?>
                                    <tr>
                                        <td class="wrap"><?php echo htmlspecialchars($r['book_name']); ?></td>
                                        <td>Rs. <?php echo number_format((float)$r['fine_amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($r['fine_status']); ?></td>
                                        <td><?php echo htmlspecialchars($r['return_date'] ?: ($r['due_date'] ?: '—')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-bordered" style="flex:1 1 380px;min-width:0;">
                    <h5 style="margin-top:0;">My Recent Suggestions</h5>
                    <?php if (empty($recentSuggests)): ?>
                        <p class="empty-row" style="margin:0;">No suggestions yet. <a href="suggest_book.php">Suggest a book</a> for the library to consider.</p>
                    <?php else: ?>
                        <?php foreach ($recentSuggests as $r): ?>
                            <div class="feed-row">
                                <div class="feed-icon suggest">💡</div>
                                <div style="flex:1;min-width:0;">
                                    <div class="feed-title"><?php echo htmlspecialchars($r['book_title']); ?></div>
                                    <div class="feed-meta"><?php echo htmlspecialchars($r['status']); ?> &middot; <?php echo htmlspecialchars($r['created_at']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    <a href="my_purchase_suggestions.php" class="btn btn-sm btn-default" style="margin-top:8px;">See all suggestions</a>
                </div>
            </div>

            <!-- ── Activity feed ─────────────────────────────────────── -->
            <div class="section-title">Recent Activity</div>
            <div class="card-bordered">
                <?php if (empty($feed)): ?>
                    <p class="empty-row" style="margin:0;">No activity yet. Start by browsing the catalogue.</p>
                <?php else: foreach ($feed as $a): ?>
                    <div class="feed-row">
                        <div class="feed-icon <?php echo htmlspecialchars($a['icon']); ?>">
                            <?php
                            echo match ($a['icon']) {
                                'issued'  => '📚',
                                'request' => '✉',
                                'fine'    => '💸',
                                'suggest' => '💡',
                                default   => '•',
                            };
                            ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="feed-title"><?php echo htmlspecialchars($a['title']); ?></div>
                            <div class="feed-meta">
                                <?php echo htmlspecialchars($a['meta']); ?>
                                <?php if (!empty($a['when'])): ?>
                                    &middot; <?php echo htmlspecialchars($a['when']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>