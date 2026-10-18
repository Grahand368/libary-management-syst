<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_validation.php';

    // ── 1. Gather inputs safely (NEVER echo $_POST['password'] back) ──
    $name    = aurora_get_post_string('name');
    $email   = aurora_normalize_email($_POST['email'] ?? '');
    $mobile  = aurora_get_post_string('mobile');
    $address = aurora_get_post_string('address');
    $rawPw   = isset($_POST['password']) ? (string)$_POST['password'] : '';

    $errors  = [];
    $success = false;

    // ── 2. Validate name (required, 2–100, letters/spaces) ────────────
    $nameClean = aurora_validate_name($name);
    if ($nameClean === null) {
        $errors[] = 'Please enter your full name (2–100 characters, letters only).';
    } else {
        $name = $nameClean;
    }

    // ── 3. Validate email (FILTER_VALIDATE_EMAIL + extra checks) ─────
    if (!aurora_validate_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    // ── 4. Validate password strength (8+, upper/lower/digit) ─────────
    $password = aurora_validate_password_strength($rawPw);
    if ($password === null) {
        $errors[] = 'Password must be at least 8 characters and contain at least one uppercase letter, one lowercase letter, and one number.';
    }

    // ── 5. Validate mobile (optional) ─────────────────────────────────
    if (!aurora_validate_mobile($mobile)) {
        $errors[] = 'Mobile number contains invalid characters.';
    }

    // ── 6. Validate address (required, 2–255) ─────────────────────────
    $addressClean = aurora_validate_length($address, 2, 255);
    if ($addressClean === null) {
        $errors[] = 'Please enter your city (2–255 characters).';
    } else {
        $address = $addressClean;
    }

    // ── 7. Duplicate email check (also enforced by the UNIQUE constraint) ──
    if (empty($errors)) {
        $existing = aurora_fetch_one(
            'select id from users where email = ? limit 1',
            's',
            [$email]
        );
        if ($existing) {
            $errors[] = 'An account with that email already exists. Try signing in instead.';
        }
    }

    // ── 8. Insert ──
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $affected = aurora_execute(
            'insert into users (name, email, password, mobile, address) VALUES (?, ?, ?, ?, ?)',
            'sssss',
            [$name, $email, $hash, $mobile, $address]
        );
        if ($affected > 0) {
            $success = true;
        } else {
            $errors[] = 'Could not create your account. Please try again.';
        }
    }

    if (!$success) {
        // NEVER echo $password / $rawPw back into the form.
        $_SESSION['flash_old']    = ['name' => $name, 'email' => $email, 'mobile' => $mobile, 'address' => $address];
        $_SESSION['flash_errors'] = $errors;
        header('Location: signup.php');
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aurora Library — Welcome</title>
    <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body class="app-bg">
<div class="auth__panel" style="min-height:100vh;display:flex;align-items:center;justify-content:center;">
    <div class="auth__card fade-up text-center" style="max-width:480px;">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--emerald),#0f4d2c);margin:0 auto 24px;display:grid;place-items:center;box-shadow:0 12px 32px rgba(31,111,67,0.28);">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h2 class="auth__title" style="font-size:32px;">Welcome aboard.</h2>
        <p class="auth__subtitle" style="margin-bottom:32px;">Your Aurora Library membership has been created. You may now sign in to access the collection.</p>
        <a href="index.php" class="btn btn--primary btn--block btn--lg">
            <span>Continue to Sign In</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>
</body>
</html>
