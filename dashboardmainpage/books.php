<?php
    /**
     * Aurora Library — Member: Browse Catalogue
     *
     * Shows the complete library catalog with:
     *   Book Name | Author | Category | Book No. | Price | Available Qty | Status | Action
     *
     * Status badges:
     *   Available    — book_qty >= LOW_STOCK_THRESHOLD
     *   Low Stock    — 0 < book_qty <  LOW_STOCK_THRESHOLD
     *   Out of Stock — book_qty <= 0
     *
     * Filter query string (optional):
     *   q            — free-text search (title / description / book_no)
     *   cat_id       — restrict to a category
     *   author_id    — restrict to an author
     *   status       — 'available' | 'low' | 'out' | '' (all)
     */

    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../user_auth_check.php';

    if (!defined('AURORA_LOW_STOCK_THRESHOLD')) define('AURORA_LOW_STOCK_THRESHOLD', 3);

    $user   = aurora_current_user();
    $flash  = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    // ── 1. Inputs ─────────────────────────────────────────────────────
    $q         = aurora_get_query_string('q');
    $catId     = filter_var($_GET['cat_id']    ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $authorId  = filter_var($_GET['author_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status    = trim((string)($_GET['status'] ?? ''));
    if (!in_array($status, ['available', 'low', 'out', ''], true)) {
        $status = '';
    }

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_sidebar.php';

    // ── 2. Filter dropdowns (categories + authors) ────────────────────
    $categories = aurora_fetch_all('SELECT cat_id, cat_name FROM category ORDER BY cat_name');
    $authors    = aurora_fetch_all('SELECT author_id, author_name FROM authors ORDER BY author_name');

    // ── 3. Build query ────────────────────────────────────────────────
    $where = ['1=1'];
    $types = '';
    $args  = [];

    if ($q !== '') {
        $where[] = '(b.book_name LIKE ? OR b.book_description LIKE ? OR b.book_no LIKE ?)';
        $like    = '%' . $q . '%';
        $types  .= 'sss';
        $args[]  = $like; $args[] = $like; $args[] = $like;
    }
    if ($catId) {
        $where[]   = 'b.cat_id = ?';
        $types    .= 'i';
        $args[]    = $catId;
    }
    if ($authorId) {
        $where[]   = 'b.author_id = ?';
        $types    .= 'i';
        $args[]    = $authorId;
    }
    if ($status === 'available') {
        $where[] = 'b.book_qty >= ?';
        $types  .= 'i';
        $args[]  = AURORA_LOW_STOCK_THRESHOLD;
    } elseif ($status === 'low') {
        $where[] = 'b.book_qty > 0 AND b.book_qty < ?';
        $types  .= 'i';
        $args[]  = AURORA_LOW_STOCK_THRESHOLD;
    } elseif ($status === 'out') {
        $where[] = 'b.book_qty <= 0';
    }
    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT b.book_id, b.book_name, b.book_no, b.book_price, b.book_qty,
               b.book_image, b.book_description,
               a.author_id, a.author_name,
               c.cat_id, c.cat_name
          FROM books b
          LEFT JOIN authors a ON b.author_id = a.author_id
          LEFT JOIN category c ON b.cat_id  = c.cat_id
         WHERE $whereSql
      ORDER BY (b.book_qty > 0) DESC, b.book_name ASC";

    $rows = aurora_fetch_all($sql, $types, $args);

    // ── 4. Helpers ────────────────────────────────────────────────────
    function aurora_stock_status(int $qty): array {
        if ($qty <= 0) {
            return ['key' => 'out',  'label' => 'Out of Stock', 'class' => 'danger'];
        }
        if ($qty < AURORA_LOW_STOCK_THRESHOLD) {
            return ['key' => 'low',  'label' => 'Low Stock',    'class' => 'warning'];
        }
        return ['key' => 'available', 'label' => 'Available', 'class' => 'success'];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Browse Books</title>
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
            <div style="display:flex;align-items:center;gap:10px;">
                <button class="menu-toggle" data-menu-toggle aria-label="Open menu">☰</button>
                <div class="header-title">Browse the catalogue</div>
            </div>
            <div class="text-muted" style="font-size:13px;">
                <?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>
            </div>
        </header>

        <div style="max-width:1200px;margin:0 auto;">

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($flash['msg']); ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <strong>Can't find the book you want?</strong><br>
                    Use <em>Suggest a Book</em> to ask the library to consider purchasing it.
                    This is a <strong>library purchase suggestion</strong> — no payment is involved.
                </div>
                <a href="suggest_book.php" class="btn btn-warning">Suggest a Book for Library Purchase</a>
            </div>

            <!-- ── Filter form ───────────────────────────────────────────── -->
            <form method="GET" action="books.php" class="form-inline" style="gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
                       class="form-control" placeholder="Search title, description, or book no." style="min-width:220px;">

                <select name="cat_id" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c):
                        $sel = ($catId === (int)$c['cat_id']) ? 'selected' : ''; ?>
                        <option value="<?php echo (int)$c['cat_id']; ?>" <?php echo $sel; ?>>
                            <?php echo htmlspecialchars($c['cat_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="author_id" class="form-control">
                    <option value="">All Authors</option>
                    <?php foreach ($authors as $a):
                        $sel = ($authorId === (int)$a['author_id']) ? 'selected' : ''; ?>
                        <option value="<?php echo (int)$a['author_id']; ?>" <?php echo $sel; ?>>
                            <?php echo htmlspecialchars($a['author_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-control">
                    <?php
                        $opts = ['' => 'All statuses', 'available' => 'Available', 'low' => 'Low Stock', 'out' => 'Out of Stock'];
                        foreach ($opts as $k => $lbl): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $status === $k ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lbl); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="books.php" class="btn btn-default">Reset</a>
            </form>

            <p class="text-muted" style="margin-top:-8px;margin-bottom:8px;">
                Showing <?php echo count($rows); ?> book<?php echo count($rows) === 1 ? '' : 's'; ?>.
            </p>

            <?php if (empty($rows)): ?>
                <div class="alert alert-warning text-center">No books match your filters.</div>
            <?php else: ?>
                <div class="table-responsive card-bordered" style="padding:0;">
                    <table class="table table-catalog table-hover" style="margin-bottom:0;">
                        <thead>
                            <tr>
                                <th>Book Name</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Book No.</th>
                                <th class="price-cell">Price</th>
                                <th>Available Qty</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $b):
                            $qty    = (int)$b['book_qty'];
                            $stk    = aurora_stock_status($qty);
                            $isAvail = $qty > 0;
                            $price  = $b['book_price'];
                            $priceStr = ($price === null || $price === '' || $price === '0.00' || $price === '0')
                                ? '—'
                                : 'Rs. ' . number_format((float)$price, 2);
                        ?>
                            <tr>
                                <td class="wrap">
                                    <strong><?php echo htmlspecialchars($b['book_name']); ?></strong>
                                    <?php if (!empty($b['book_description'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($b['book_description'], 0, 80)); ?><?php echo mb_strlen((string)$b['book_description']) > 80 ? '…' : ''; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($b['author_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($b['cat_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($b['book_no']); ?></td>
                                <td class="price-cell"><?php echo $priceStr; ?></td>
                                <td>
                                    <span class="qty-pill <?php echo $stk['class']; ?>"><?php echo $qty; ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $stk['class']; ?>"><?php echo $stk['label']; ?></span>
                                </td>
                                <td>
                                    <form method="POST" action="request_book.php" style="margin:0;" onsubmit="return confirm('Submit a borrow request for this book?');">
                                        <?php echo aurora_csrf_field(); ?>
                                        <input type="hidden" name="book_id" value="<?php echo (int)$b['book_id']; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $isAvail ? 'btn-primary' : 'btn-warning'; ?>">
                                            <?php echo $isAvail ? 'Request to Borrow' : 'Request Book'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>