<?php
    $connection = mysqli_connect("localhost","root","");
    mysqli_select_db($connection,"lms");
    $log = [];
    $err = 0;

    // 1) Expand password column to VARCHAR(255) to safely store modern hashes
    foreach (['admins', 'users'] as $tbl) {
        $col = mysqli_fetch_assoc(mysqli_query($connection, "SHOW COLUMNS FROM $tbl LIKE 'password'"));
        if ($col && stripos($col['Type'], 'varchar(255)') === false) {
            if (mysqli_query($connection, "ALTER TABLE $tbl MODIFY COLUMN password VARCHAR(255) NOT NULL")) {
                $log[] = ["Expanded <code>$tbl.password</code> to VARCHAR(255).", "ok"];
            } else {
                $log[] = ["Failed to expand <code>$tbl.password</code>: " . mysqli_error($connection), "err"];
                $err++;
            }
        } else {
            $log[] = ["<code>$tbl.password</code> already at VARCHAR(255).", "warn"];
        }
    }

    // 2) Inspect: how many rows still have a plain-text password?
    //    Heuristic: a password_hash() bcrypt/argon2 hash is 60+ chars and starts with $2y$, $argon2$, etc.
    //    Anything shorter or not starting with $ is treated as legacy plain text.
    $countPlainAdmin = 0; $countHashedAdmin = 0;
    $res = mysqli_query($connection, "SELECT password FROM admins");
    while ($r = mysqli_fetch_assoc($res)) {
        $p = $r['password'];
        if (preg_match('/^\$(2[axyb]|argon2(i|id))\$/', $p)) $countHashedAdmin++;
        else $countPlainAdmin++;
    }
    $countPlainUser = 0; $countHashedUser = 0;
    $res = mysqli_query($connection, "SELECT password FROM users");
    while ($r = $res->fetch_assoc()) {
        $p = $r['password'];
        if (preg_match('/^\$(2[axyb]|argon2(i|id))\$/', $p)) $countHashedUser++;
        else $countPlainUser++;
    }

    $log[] = ["Admin passwords: <strong>$countHashedAdmin hashed</strong>, <strong>$countPlainAdmin still plain</strong>.", "info"];
    $log[] = ["User passwords:  <strong>$countHashedUser hashed</strong>, <strong>$countPlainUser still plain</strong>.", "info"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Migration · Aurora Library</title>
    <link rel="stylesheet" href="static/css/aurora.css">
</head>
<body class="app-bg" style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;">
    <div class="auth__card fade-up" style="max-width:600px;width:100%;">
        <div class="flex" style="align-items:center;gap:14px;margin-bottom:24px;">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--gold-600),var(--gold-700));display:grid;place-items:center;flex-shrink:0;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.4" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <div>
                <h2 class="auth__title" style="font-size:26px;margin:0;">Password security ready.</h2>
                <p class="auth__subtitle" style="margin:2px 0 0;font-size:13px;">Existing users will be migrated transparently on next sign-in.</p>
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

        <div style="background:linear-gradient(135deg,#0a0e1a,#1f2937);border-radius:14px;padding:20px;margin-bottom:24px;color:rgba(255,255,255,0.85);font-size:13px;line-height:1.7;">
            <div style="font-size:11px;color:var(--gold-400);text-transform:uppercase;letter-spacing:0.16em;font-weight:600;margin-bottom:10px;">How migration works</div>
            <p style="margin:0 0 8px;">• New users are stored with <code style="color:var(--gold-400);">password_hash()</code> immediately.</p>
            <p style="margin:0 0 8px;">• Existing users can still log in with their old password.</p>
            <p style="margin:0;">• On successful login, the plain-text password is <strong style="color:var(--gold-400);">automatically re-hashed</strong> and saved — no action required.</p>
        </div>

        <div class="flex gap-3" style="justify-content:center;flex-wrap:wrap;">
            <a href="login_module/admin_login.php" class="btn btn--primary">Admin Sign In</a>
            <a href="login_module/index.php" class="btn btn--ghost">Member Sign In</a>
        </div>

        <p class="text-sm text-muted mt-6 text-center">
            Safe to re-run. Delete <code style="background:var(--ink-100);padding:2px 6px;border-radius:4px;">migrate_passwords.php</code> after use.
        </p>
    </div>
</body>
</html>
