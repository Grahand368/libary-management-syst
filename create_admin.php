<?php
    require_once __DIR__ . '/aurora_db.php';
    require_once __DIR__ . '/aurora_validation.php';
    $conn = aurora_db();
    $db = mysqli_select_db($conn, "lms");

    // Default admin credentials
    $name     = "Administrator";
    $email    = aurora_normalize_email("admin@aurora.library");
    $password = "admin123";
    $mobile   = 9999999999;

    // Sanity check: the default email must be a real, deliverable-looking address
    if (!aurora_validate_email($email)) {
        $log[] = ["Default admin email <code>$email</code> is not valid — please edit create_admin.php.", "err"];
    }

    $log = [];

    // Step 1: Ensure database exists
    if (!$db) {
        mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS lms");
        mysqli_select_db($conn, "lms");
        $log[] = ["Created database `lms`.", "ok"];
    } else {
        $log[] = ["Database `lms` already exists.", "ok"];
    }

    // Step 2: Ensure admins table exists
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'admins'");
    if (mysqli_num_rows($tableCheck) == 0) {
        $create = "CREATE TABLE admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            mobile BIGINT
        )";
        if (mysqli_query($conn, $create)) {
            $log[] = ["Created table `admins`.", "ok"];
        } else {
            $log[] = ["Failed to create table `admins`: " . mysqli_error($conn), "err"];
        }
    } else {
        $log[] = ["Table `admins` already exists.", "ok"];
    }

    // Step 3: Ensure users table exists (in case signup was never used)
    $usersCheck = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    if (mysqli_num_rows($usersCheck) == 0) {
        $createUsers = "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            mobile BIGINT,
            address VARCHAR(255),
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL
        )";
        if (mysqli_query($conn, $createUsers)) {
            $log[] = ["Created table `users`.", "ok"];
        }
    } else {
        $log[] = ["Table `users` already exists.", "ok"];
    }

    // Step 3b: Ensure account-status columns exist on legacy databases.
    foreach ([
        'is_active'  => "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        'created_at' => "ALTER TABLE users ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'deleted_at' => "ALTER TABLE users ADD COLUMN deleted_at DATETIME DEFAULT NULL",
    ] as $col => $ddl) {
        $chk = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE '$col'");
        if (mysqli_num_rows($chk) == 0) {
            @mysqli_query($conn, $ddl);
            $log[] = ["Added `users.$col` column.", "ok"];
        }
    }
    // Backfill created_at for old rows where MySQL won't auto-populate.
    @mysqli_query($conn, "UPDATE users SET created_at = NOW() WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");

    // Step 4: Ensure books table exists
    $booksCheck = mysqli_query($conn, "SHOW TABLES LIKE 'books'");
    if (mysqli_num_rows($booksCheck) == 0) {
        $createBooks = "CREATE TABLE books (
            book_id INT AUTO_INCREMENT PRIMARY KEY,
            book_name VARCHAR(255) NOT NULL,
            author_id INT,
            cat_id INT,
            book_no VARCHAR(50),
            book_price DECIMAL(10,2),
            book_qty INT NOT NULL DEFAULT 1,
            book_image VARCHAR(500) DEFAULT NULL,
            book_description TEXT DEFAULT NULL
        )";
        if (mysqli_query($conn, $createBooks)) {
            $log[] = ["Created table `books`.", "ok"];
        }
    } else {
        $log[] = ["Table `books` already exists.", "ok"];
    }

    // Step 4a: Ensure all required FKs are in place on `books`.
    $booksFks = [
        'fk_books_author' => "ALTER TABLE books ADD CONSTRAINT fk_books_author
                                 FOREIGN KEY (author_id) REFERENCES authors(author_id)
                                 ON DELETE RESTRICT ON UPDATE CASCADE",
        'fk_books_cat'    => "ALTER TABLE books ADD CONSTRAINT fk_books_cat
                                 FOREIGN KEY (cat_id) REFERENCES category(cat_id)
                                 ON DELETE RESTRICT ON UPDATE CASCADE",
    ];
    foreach ($booksFks as $name => $ddl) {
        $chk = mysqli_query(
            $conn,
            "SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE table_schema = DATABASE()
                AND table_name   = 'books'
                AND constraint_name = '" . mysqli_real_escape_string($conn, $name) . "'"
        );
        $row = $chk ? mysqli_fetch_row($chk) : null;
        if (!$row || (int)$row[0] === 0) {
            if (@mysqli_query($conn, $ddl)) {
                $log[] = ["Added books FK `$name`.", "ok"];
            } else {
                $log[] = ["Failed to add books FK `$name`: " . mysqli_error($conn), "warn"];
            }
        }
    }

    // Step 4b: Ensure books.book_qty exists on legacy databases
    $qtyCol = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'book_qty'");
    if (mysqli_num_rows($qtyCol) == 0) {
        @mysqli_query($conn, "ALTER TABLE books ADD COLUMN book_qty INT NOT NULL DEFAULT 1");
        $log[] = ["Added `books.book_qty` column.", "ok"];
    }

    // Step 4c: Ensure user-facing catalog columns exist (image + description).
    foreach ([
        'book_image'      => "ALTER TABLE books ADD COLUMN book_image VARCHAR(500) DEFAULT NULL",
        'book_description'=> "ALTER TABLE books ADD COLUMN book_description TEXT DEFAULT NULL",
    ] as $col => $ddl) {
        $chk = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE '$col'");
        if (mysqli_num_rows($chk) == 0) {
            @mysqli_query($conn, $ddl);
            $log[] = ["Added `books.$col` column.", "ok"];
        }
    }

    // Step 5: Ensure category table exists
    $catCheck = mysqli_query($conn, "SHOW TABLES LIKE 'category'");
    if (mysqli_num_rows($catCheck) == 0) {
        $createCat = "CREATE TABLE category (
            cat_id INT AUTO_INCREMENT PRIMARY KEY,
            cat_name VARCHAR(100) NOT NULL
        )";
        if (mysqli_query($conn, $createCat)) {
            $log[] = ["Created table `category`.", "ok"];
        }
    } else {
        $log[] = ["Table `category` already exists.", "ok"];
    }

    // Step 6: Ensure issued_books table exists
    $issuedCheck = mysqli_query($conn, "SHOW TABLES LIKE 'issued_books'");
    if (mysqli_num_rows($issuedCheck) == 0) {
        $createIssued = "CREATE TABLE issued_books (
            id INT AUTO_INCREMENT PRIMARY KEY,
            book_no VARCHAR(50),
            book_name VARCHAR(255),
            book_author VARCHAR(255),
            student_id INT,
            status TINYINT DEFAULT 1,
            issue_date DATE,
            due_date DATE DEFAULT NULL,
            return_date DATE DEFAULT NULL,
            fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            fine_status VARCHAR(20) NOT NULL DEFAULT 'No Fine'
        )";
        if (mysqli_query($conn, $createIssued)) {
            $log[] = ["Created table `issued_books`.", "ok"];
        }
    } else {
        $log[] = ["Table `issued_books` already exists.", "ok"];
    }

    // Step 6a: Ensure all required FKs are in place on `issued_books`.
    $issuedFks = [
        'fk_issued_student' => "ALTER TABLE issued_books ADD CONSTRAINT fk_issued_student
                                  FOREIGN KEY (student_id) REFERENCES users(id)
                                  ON DELETE RESTRICT ON UPDATE CASCADE",
        'fk_issued_book'    => "ALTER TABLE issued_books ADD CONSTRAINT fk_issued_book
                                  FOREIGN KEY (book_no) REFERENCES books(book_no)
                                  ON DELETE RESTRICT ON UPDATE CASCADE",
    ];
    foreach ($issuedFks as $name => $ddl) {
        $chk = mysqli_query(
            $conn,
            "SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE table_schema = DATABASE()
                AND table_name   = 'issued_books'
                AND constraint_name = '" . mysqli_real_escape_string($conn, $name) . "'"
        );
        $row = $chk ? mysqli_fetch_row($chk) : null;
        if (!$row || (int)$row[0] === 0) {
            if (@mysqli_query($conn, $ddl)) {
                $log[] = ["Added issued_books FK `$name`.", "ok"];
            } else {
                $log[] = ["Failed to add issued_books FK `$name`: " . mysqli_error($conn), "warn"];
            }
        }
    }

    // Step 6b: Ensure all fine-management columns exist on legacy schemas.
    //           - return_date / due_date: when a book is/was issued
    //           - fine_amount: monetary fine (0 when no fine)
    //           - fine_status: 'No Fine' | 'Unpaid' | 'Paid'
    foreach ([
        'due_date'    => "ALTER TABLE issued_books ADD COLUMN due_date DATE DEFAULT NULL",
        'return_date' => "ALTER TABLE issued_books ADD COLUMN return_date DATE DEFAULT NULL",
        'fine_amount' => "ALTER TABLE issued_books ADD COLUMN fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0",
        'fine_status' => "ALTER TABLE issued_books ADD COLUMN fine_status VARCHAR(20) NOT NULL DEFAULT 'No Fine'",
    ] as $col => $ddl) {
        $chk = mysqli_query($conn, "SHOW COLUMNS FROM issued_books LIKE '$col'");
        if (mysqli_num_rows($chk) == 0) {
            @mysqli_query($conn, $ddl);
            $log[] = ["Added `issued_books.$col` column.", "ok"];
        }
    }

    // Step 6c: If a legacy `fine` column exists, fold its values into
    //           `fine_amount`, then drop it so we have one source of truth.
    $legacyFine = mysqli_query($conn, "SHOW COLUMNS FROM issued_books LIKE 'fine'");
    if (mysqli_num_rows($legacyFine) > 0) {
        @mysqli_query(
            $conn,
            "UPDATE issued_books
                SET fine_amount = COALESCE(fine_amount, 0) + COALESCE(fine, 0)
              WHERE fine_amount = 0 OR fine_amount IS NULL"
        );
        @mysqli_query($conn, "ALTER TABLE issued_books DROP COLUMN fine");
        $log[] = ["Migrated legacy `issued_books.fine` into `fine_amount`.", "ok"];
    }

    // Step 6d: Backfill due_date for any rows that have an issue_date but
    //           no due_date (assumes 14-day loan period). Safe to re-run.
    @mysqli_query(
        $conn,
        "UPDATE issued_books
            SET due_date = DATE_ADD(issue_date, INTERVAL 14 DAY)
          WHERE due_date IS NULL AND issue_date IS NOT NULL"
    );

    // Step 6e: Backfill fine_status for any rows where it is NULL/blank.
    @mysqli_query(
        $conn,
        "UPDATE issued_books
            SET fine_status = 'No Fine'
          WHERE fine_status IS NULL OR fine_status = ''"
    );

    // Step 7: book_requests table for the member → admin book request flow.
    $reqCheck = mysqli_query($conn, "SHOW TABLES LIKE 'book_requests'");
    if (mysqli_num_rows($reqCheck) == 0) {
        $createReq = "CREATE TABLE book_requests (
            request_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            request_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status ENUM('Pending','Approved','Declined','Cancelled') NOT NULL DEFAULT 'Pending',
            admin_response_date DATETIME DEFAULT NULL,
            admin_response_by INT DEFAULT NULL,
            admin_response_note VARCHAR(500) DEFAULT NULL,
            due_date DATE DEFAULT NULL,
            UNIQUE KEY uniq_user_book_pending (user_id, book_id, status),
            KEY idx_status (status),
            KEY idx_user (user_id),
            KEY idx_book (book_id)
        )";
        if (mysqli_query($conn, $createReq)) {
            $log[] = ["Created table `book_requests`.", "ok"];
        } else {
            $log[] = ["Failed to create `book_requests`: " . mysqli_error($conn), "err"];
        }
} else {
            $log[] = ["Table `book_requests` already exists.", "ok"];
        }

        // Step 7a: Ensure all required FKs are in place on `book_requests`.
        $bkFks = [
            'fk_bkreq_user' => "ALTER TABLE book_requests ADD CONSTRAINT fk_bkreq_user
                                 FOREIGN KEY (user_id) REFERENCES users(id)
                                 ON DELETE RESTRICT ON UPDATE CASCADE",
            'fk_bkreq_book' => "ALTER TABLE book_requests ADD CONSTRAINT fk_bkreq_book
                                 FOREIGN KEY (book_id) REFERENCES books(book_id)
                                 ON DELETE RESTRICT ON UPDATE CASCADE",
        ];
        foreach ($bkFks as $name => $ddl) {
            $chk = mysqli_query(
                $conn,
                "SELECT COUNT(*) FROM information_schema.table_constraints
                  WHERE table_schema = DATABASE()
                    AND table_name   = 'book_requests'
                    AND constraint_name = '" . mysqli_real_escape_string($conn, $name) . "'"
            );
            $row = $chk ? mysqli_fetch_row($chk) : null;
            if (!$row || (int)$row[0] === 0) {
                if (@mysqli_query($conn, $ddl)) {
                    $log[] = ["Added book_requests FK `$name`.", "ok"];
                } else {
                    $log[] = ["Failed to add book_requests FK `$name`: " . mysqli_error($conn), "warn"];
                }
            }
        }

    // Step 8: purchase_requests table for the library-purchase suggestion flow.
    $prCheck = mysqli_query($conn, "SHOW TABLES LIKE 'purchase_requests'");
    if (mysqli_num_rows($prCheck) == 0) {
        $createPR = "CREATE TABLE purchase_requests (
            purchase_request_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            book_title VARCHAR(255) NOT NULL,
            author_name VARCHAR(255) DEFAULT NULL,
            isbn VARCHAR(32) DEFAULT NULL,
            reason TEXT,
            estimated_price DECIMAL(10,2) DEFAULT NULL,
            status ENUM('Pending','Approved','Purchased','Declined','Rejected') NOT NULL DEFAULT 'Pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            admin_response_by INT DEFAULT NULL,
            admin_response_note VARCHAR(500) DEFAULT NULL,
            KEY idx_user (user_id),
            KEY idx_status (status),
            KEY idx_title (book_title)
        )";
        if (mysqli_query($conn, $createPR)) {
            $log[] = ["Created table `purchase_requests`.", "ok"];
        } else {
            $log[] = ["Failed to create `purchase_requests`: " . mysqli_error($conn), "err"];
        }
} else {
            $log[] = ["Table `purchase_requests` already exists.", "ok"];
        }

        // Step 8a: Ensure FK on `purchase_requests.user_id` → users.id.
        $prFkName = 'fk_purreq_user';
        $chk = mysqli_query(
            $conn,
            "SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE table_schema = DATABASE()
                AND table_name   = 'purchase_requests'
                AND constraint_name = '" . mysqli_real_escape_string($conn, $prFkName) . "'"
        );
        $row = $chk ? mysqli_fetch_row($chk) : null;
        if (!$row || (int)$row[0] === 0) {
            $ddl = "ALTER TABLE purchase_requests ADD CONSTRAINT fk_purreq_user
                      FOREIGN KEY (user_id) REFERENCES users(id)
                      ON DELETE RESTRICT ON UPDATE CASCADE";
            if (@mysqli_query($conn, $ddl)) {
                $log[] = ["Added purchase_requests FK `$prFkName`.", "ok"];
            } else {
                $log[] = ["Failed to add purchase_requests FK `$prFkName`: " . mysqli_error($conn), "warn"];
            }
        }

    // Step 7: Ensure authors table exists
    $authorCheck = mysqli_query($conn, "SHOW TABLES LIKE 'authors'");
    if (mysqli_num_rows($authorCheck) == 0) {
        $createAuthors = "CREATE TABLE authors (
            author_id INT AUTO_INCREMENT PRIMARY KEY,
            author_name VARCHAR(100) NOT NULL
        )";
        if (mysqli_query($conn, $createAuthors)) {
            $log[] = ["Created table `authors`.", "ok"];
        }
    } else {
        $log[] = ["Table `authors` already exists.", "ok"];
    }

    // Step 8: Insert the default admin
    $check = mysqli_query($conn, "SELECT * FROM admins WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {
        $log[] = ["Admin with email <strong>$email</strong> already exists — no changes made.", "warn"];
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $hEsc   = mysqli_real_escape_string($conn, $hashed);
        $query  = "INSERT INTO admins (name, email, password, mobile) VALUES('$name', '$email', '$hEsc', $mobile)";
        if (mysqli_query($conn, $query)) {
            $log[] = ["Admin account <strong>$email</strong> created successfully (password is securely hashed).", "ok"];
        } else {
            $log[] = ["Failed to insert admin: " . mysqli_error($conn), "err"];
        }
    }

    $allOk = !in_array("err", array_column($log, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup · Aurora Library</title>
    <link rel="stylesheet" href="static/css/aurora.css">
</head>
<body class="app-bg" style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;">
    <div class="auth__card fade-up" style="max-width:560px;width:100%;">
        <div class="flex" style="align-items:center;gap:14px;margin-bottom:24px;">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,<?php echo $allOk ? 'var(--emerald),#0f4d2c' : 'var(--crimson),#7f1d1d'; ?>);display:grid;place-items:center;flex-shrink:0;">
                <?php if($allOk): ?>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <?php else: ?>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?php endif; ?>
            </div>
            <div>
                <h2 class="auth__title" style="font-size:26px;margin:0;"><?php echo $allOk ? 'Setup complete.' : 'Setup finished with errors.'; ?></h2>
                <p class="auth__subtitle" style="margin:2px 0 0;font-size:13px;">Database initialized & admin account created</p>
            </div>
        </div>

        <div style="background:var(--ink-50);border:1px solid var(--ink-100);border-radius:14px;padding:16px 20px;margin-bottom:20px;">
            <div style="display:grid;gap:8px;">
                <?php foreach($log as $entry): ?>
                    <div class="flex gap-2" style="align-items:center;font-size:13px;">
                        <?php if($entry[1] === 'ok'): ?>
                            <span style="color:var(--emerald);font-weight:700;">✓</span>
                        <?php elseif($entry[1] === 'warn'): ?>
                            <span style="color:var(--gold-700);font-weight:700;">⚠</span>
                        <?php else: ?>
                            <span style="color:var(--crimson);font-weight:700;">✕</span>
                        <?php endif; ?>
                        <span><?php echo $entry[0]; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="text-align:left;background:linear-gradient(135deg,#0a0e1a,#1f2937);border-radius:14px;padding:20px;margin-bottom:24px;">
            <div style="font-size:11px;color:var(--gold-400);text-transform:uppercase;letter-spacing:0.16em;font-weight:600;margin-bottom:12px;">Default Admin Credentials</div>
            <div style="display:grid;gap:6px;font-family:'JetBrains Mono',monospace;font-size:14px;">
                <div><span style="color:rgba(255,255,255,0.5);">Email:</span> <strong style="color:#fff;">admin@aurora.library</strong></div>
                <div><span style="color:rgba(255,255,255,0.5);">Password:</span> <strong style="color:#fff;">admin123</strong></div>
            </div>
        </div>

        <div class="flex gap-3" style="justify-content:center;">
            <a href="login_module/admin_login.php" class="btn btn--primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Go to Admin Login</span>
            </a>
            <a href="login_module/index.php" class="btn btn--ghost">Member Login</a>
        </div>

        <p class="text-sm text-muted mt-6 text-center">
            For security, delete <code style="background:var(--ink-100);padding:2px 6px;border-radius:4px;">create_admin.php</code> from your server after setup.
        </p>
    </div>
</body>
</html>

