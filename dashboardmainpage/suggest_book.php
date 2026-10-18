<?php
    /**
     * Aurora Library — Member: Suggest a Book for Library Purchase
     *
     * This is a SUGGESTION flow, not a purchase. The user is asking the
     * library to consider buying a book. No payment happens here.
     *
     * GET  -> show the form
     * POST -> validate, insert as 'Pending', redirect with flash
     */

    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../user_auth_check.php';

    $errors = [];
    $values = [
        'book_title'      => '',
        'author_name'     => '',
        'isbn'            => '',
        'reason'          => '',
        'estimated_price' => '',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!aurora_csrf_check($token)) {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'Invalid security token. Please retry.'];
            header('Location: suggest_book.php');
            exit;
        }

        $values['book_title']      = trim((string)($_POST['book_title']      ?? ''));
        $values['author_name']     = trim((string)($_POST['author_name']     ?? ''));
        $values['isbn']            = trim((string)($_POST['isbn']            ?? ''));
        $values['reason']          = trim((string)($_POST['reason']          ?? ''));
        $values['estimated_price'] = trim((string)($_POST['estimated_price'] ?? ''));

        // Validation
        if (mb_strlen($values['book_title']) < 2 || mb_strlen($values['book_title']) > 255) {
            $errors[] = 'Please enter the book title (2–255 characters).';
        }
        if ($values['author_name'] !== '' && mb_strlen($values['author_name']) > 255) {
            $errors[] = 'Author name is too long (max 255 characters).';
        }
        if ($values['isbn'] !== '' && !preg_match('/^[0-9Xx\- ]{6,32}$/', $values['isbn'])) {
            $errors[] = 'ISBN should be 6–32 characters (digits, X, dashes or spaces).';
        }
        if ($values['reason'] !== '' && mb_strlen($values['reason']) > 2000) {
            $errors[] = 'Reason is too long (max 2000 characters).';
        }
        $price = null;
        if ($values['estimated_price'] !== '') {
            $price = filter_var($values['estimated_price'], FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
            if ($price === false) {
                $errors[] = 'Estimated price must be a non-negative number.';
            }
        }

        if (empty($errors)) {
            $userId = (int)($_SESSION['id'] ?? 0);

            // Duplicate-prevention: don't let a member spam-insert the same
            // title while a previous suggestion is still Pending. Approved,
            // Declined, and Purchased are terminal — a fresh suggestion is OK.
            $dup = aurora_fetch_one(
                "SELECT purchase_request_id
                   FROM purchase_requests
                  WHERE user_id = ?
                    AND status = 'Pending'
                    AND LOWER(TRIM(book_title)) = LOWER(TRIM(?))
                  LIMIT 1",
                'is',
                [$userId, $values['book_title']]
            );
            if ($dup) {
                $errors[] = 'You already have a pending suggestion for "' . $values['book_title'] . '". Please wait for the librarian to review it.';
            } else {
                $stmt = mysqli_prepare(
                    aurora_db(),
                    "INSERT INTO purchase_requests
                        (user_id, book_title, author_name, isbn, reason, estimated_price, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'issssd',
                    $userId,
                    $values['book_title'],
                    $values['author_name'],
                    $values['isbn'],
                    $values['reason'],
                    $price
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $_SESSION['_flash'] = ['type' => 'success', 'msg' => 'Thank you! Your suggestion has been sent to the librarian for review.'];
                header('Location: my_purchase_suggestions.php');
                exit;
            }
        }

        // Repopulate form on validation error.
        $_SESSION['_flash']      = ['type' => 'error', 'msg' => implode(' ', $errors)];
        $_SESSION['_pr_old']   = $values;
    }

    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    if (!empty($_SESSION['_pr_old'])) {
        $values = array_merge($values, $_SESSION['_pr_old']);
        unset($_SESSION['_pr_old']);
    }
$current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Suggest a Book for Library Purchase — Aurora Library</title>
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <?php render_user_sidebar($current); ?>
    <main class="app-main" id="app-main">
        <?php render_user_header('Suggest a Book for Library Purchase'); ?>

        <div class="suggest-card">
            <div class="info-banner">
                <strong>Please note:</strong> This is a <em>suggestion</em>, not a personal purchase.
                The library will review your request and decide whether to add the book to the collection.
                No payment is involved at any point.
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($flash['msg']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="suggest_book.php">
                <?php echo aurora_csrf_field(); ?>

                <div class="form-group">
                    <label for="book_title">Book Title <span style="color:#c0392b;">*</span></label>
                    <input type="text" id="book_title" name="book_title" class="form-control"
                           value="<?php echo htmlspecialchars($values['book_title']); ?>"
                           required minlength="2" maxlength="255">
                </div>

                <div class="form-group">
                    <label for="author_name">Author</label>
                    <input type="text" id="author_name" name="author_name" class="form-control"
                           value="<?php echo htmlspecialchars($values['author_name']); ?>"
                           maxlength="255">
                    <div class="help-text">Optional, but helpful for the librarian.</div>
                </div>

                <div class="form-group">
                    <label for="isbn">ISBN</label>
                    <input type="text" id="isbn" name="isbn" class="form-control"
                           value="<?php echo htmlspecialchars($values['isbn']); ?>"
                           maxlength="32" placeholder="e.g. 978-3-16-148410-0">
                    <div class="help-text">Optional. Digits, dashes, spaces, and X only.</div>
                </div>

                <div class="form-group">
                    <label for="reason">Why should the library buy this?</label>
                    <textarea id="reason" name="reason" class="form-control" rows="4"
                              maxlength="2000" placeholder="e.g. Frequently requested by members; aligns with the new curriculum..."><?php echo htmlspecialchars($values['reason']); ?></textarea>
                    <div class="help-text">Optional. Max 2000 characters.</div>
                </div>

                <div class="form-group">
                    <label for="estimated_price">Estimated Price (Rs.)</label>
                    <input type="number" step="0.01" min="0" id="estimated_price" name="estimated_price" class="form-control"
                           value="<?php echo htmlspecialchars($values['estimated_price']); ?>">
                    <div class="help-text">Optional. Helps with budgeting.</div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">Submit Suggestion</button>
                <a href="books.php" class="btn btn-default">Cancel</a>
            </form>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>