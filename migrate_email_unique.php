<?php
    require_once __DIR__ . '/aurora_db.php';
    require_once __DIR__ . '/aurora_validation.php';
    $conn = aurora_db();
    $log = [];
    $err = 0;

    // 1) Add UNIQUE constraint on users.email (idempotent)
    $check = mysqli_query($conn, "SHOW INDEXES FROM users WHERE Column_name = 'email' AND Non_unique = 0");
    if (mysqli_num_rows($check) > 0) {
        $log[] = ["<code>users.email</code> already has a UNIQUE index — skipped.", "warn"];
    } else {
        // Detect duplicates first and surface them so the user can fix them
        $dups = mysqli_query($conn, "SELECT email, COUNT(*) as c FROM users GROUP BY email HAVING c > 1");
        $dupCount = mysqli_num_rows($dups);
        if ($dupCount > 0) {
            $log[] = ["Found $dupCount duplicate email(s) in <code>users</code> — fix them in phpMyAdmin before adding the UNIQUE constraint.", "err"];
            $err++;
        } else {
            if (mysqli_query($conn, "ALTER TABLE users ADD UNIQUE KEY uniq_users_email (email)")) {
                $log[] = ["Added UNIQUE constraint on <code>users.email</code>.", "ok"];
            } else {
                $log[] = ["Failed to add UNIQUE on <code>users.email</code>: " . mysqli_error($conn), "err"];
                $err++;
            }
        }
    }

    // 2) Add UNIQUE constraint on admins.email
    $check2 = mysqli_query($conn, "SHOW INDEXES FROM admins WHERE Column_name = 'email' AND Non_unique = 0");
    if (mysqli_num_rows($check2) > 0) {
        $log[] = ["<code>admins.email</code> already has a UNIQUE index — skipped.", "warn"];
    } else {
        $dups2 = mysqli_query($conn, "SELECT email, COUNT(*) as c FROM admins GROUP BY email HAVING c > 1");
        if (mysqli_num_rows($dups2) > 0) {
            $log[] = ["Found duplicate email(s) in <code>admins</code> — fix them in phpMyAdmin first.", "err"];
            $err++;
        } else {
            if (mysqli_query($conn, "ALTER TABLE admins ADD UNIQUE KEY uniq_admins_email (email)")) {
                $log[] = ["Added UNIQUE constraint on <code>admins.email</code>.", "ok"];
            } else {
                $log[] = ["Failed to add UNIQUE on <code>admins.email</code>: " . mysqli_error($conn), "err"];
                $err++;
            }
        }
    }

    // 3) Verify the default admin's email is still a valid format
    $adminRow = aurora_fetch_one('select email from admins limit 1');
    if ($adminRow && !aurora_validate_email($adminRow['email'])) {
        $log[] = ["Default admin email <code>" . htmlspecialchars($adminRow['email']) . "</code> is not a valid format — please update it.", "err"];
        $err++;
    } else {
        $log[] = ["Default admin email format is valid.", "ok"];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Migration · Aurora Library</title>
    <link rel="stylesheet" href="static/css/aurora.css">
</head>
<body class="app-bg" style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;">
    <div class="auth__card fade-up" style="max-width:600px;width:100%;">
        <div class="flex" style="align-items:center;gap:14px;margin-bottom:24px;">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,<?php echo $err === 0 ? 'var(--emerald),#0f4d2c' : 'var(--crimson),#7f1d1d'; ?>);display:grid;place-items:center;flex-shrink:0;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <div>
                <h2 class="auth__title" style="font-size:26px;margin:0;"><?php echo $err === 0 ? 'Email constraints applied.' : 'Migration finished with errors.'; ?></h2>
                <p class="auth__subtitle" style="margin:2px 0 0;font-size:13px;">Uniqueness + format validation are now enforced</p>
            </div>
        </div>

        <div style="background:var(--ink-50);border:1px solid var(--ink-100);border-radius:14px;padding:16px 20px;margin-bottom:20px;">
            <div style="display:grid;gap:8px;">
                <?php foreach($log as $entry):
                    $kind = $entry[1];
                    $color = '#374151';
                    $icon = '•';
                    if ($kind === 'ok')   { $color = 'var(--emerald)'; $icon = '✓'; }
                    if ($kind === 'warn') { $color = 'var(--gold-700)'; $icon = '⚠'; }
                    if ($kind === 'err')  { $color = 'var(--crimson)'; $icon = '✕'; }
                ?>
                    <div class="flex gap-2" style="align-items:flex-start;font-size:13px;">
                        <span style="color:<?php echo $color; ?>;font-weight:700;line-height:1.4;"><?php echo $icon; ?></span>
                        <span><?php echo $entry[0]; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="text-sm text-muted text-center">
            Safe to re-run. Delete <code style="background:var(--ink-100);padding:2px 6px;border-radius:4px;">migrate_email_unique.php</code> after use.
        </p>
    </div>
</body>
</html>
