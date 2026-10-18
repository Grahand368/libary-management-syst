<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../admin_auth_check.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_validation.php';

    $errors = [];
    $name    = aurora_get_post_string('name');
    $email   = aurora_normalize_email($_POST['email'] ?? '');
    $mobile  = aurora_get_post_string('mobile');

    $nameClean = aurora_validate_name($name);
    if ($nameClean === null) $errors[] = 'Full name must be 2–100 characters, letters only.';
    else                      $name = $nameClean;

    if (!aurora_validate_email($email)) $errors[] = 'A valid email address is required.';

    if (!aurora_validate_mobile($mobile)) $errors[] = 'Mobile number contains invalid characters.';

    $success = false;
    $redirect = 'admin_dashboard.php';
    $toastType = 'error';
    $toastMsg  = 'Profile could not be updated.';

    if(empty($errors)){
        // ── 2. Identify the currently logged-in admin by session email ──
        $sessionEmail = $_SESSION['email'];
        $current = aurora_fetch_one(
            'select id, email from admins where email = ? limit 1',
            's',
            [$sessionEmail]
        );
        if(!$current){
            aurora_logout();
            header("Location: ../login_module/admin_login.php?expired=1");
            exit;
        }
        $currentId = intval($current['id']);

        // ── 3. Ensure new email is not used by another admin ──
        $emailClash = aurora_fetch_one(
            'select id from admins where email = ? and id <> ? limit 1',
            'si',
            [$email, $currentId]
        );
        if($emailClash){
            $errors[] = 'That email is already used by another administrator.';
        } else {
            // ── 4. Update ONLY the logged-in admin's row ──
            $affected = aurora_execute(
                'update admins set name = ?, email = ?, mobile = ? where id = ?',
                'sssi',
                [$name, $email, $mobile, $currentId]
            );
            if($affected > 0){
                $success   = true;
                $toastType = 'success';
                $toastMsg  = 'Profile updated successfully';
                // Keep the session in sync with the new values
                $_SESSION['name']  = $name;
                $_SESSION['email'] = $email;
            } else {
                $errors[] = 'No changes were made.';
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile Update · Aurora Library</title>
    <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body class="app-bg" onload="Aurora.toast('<?php echo htmlspecialchars($toastMsg, ENT_QUOTES); ?>','<?php echo $toastType; ?>'); setTimeout(()=>{window.location.href='<?php echo $redirect; ?>';}, 1500);">
<div class="auth__panel" style="min-height:100vh;display:flex;align-items:center;justify-content:center;">
    <div class="auth__card fade-up text-center" style="max-width:520px;">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,<?php echo $success ? 'var(--emerald),#0f4d2c' : 'var(--crimson),#7f1d1d'; ?>);margin:0 auto 24px;display:grid;place-items:center;box-shadow:0 12px 32px rgba(0,0,0,0.18);">
            <?php if($success): ?>
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            <?php else: ?>
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            <?php endif; ?>
        </div>
        <h2 class="auth__title" style="font-size:32px;"><?php echo $success ? 'Profile updated.' : 'Update failed.'; ?></h2>
        <?php if($success): ?>
            <p class="auth__subtitle" style="margin-bottom:8px;">Your changes have been saved successfully.</p>
        <?php else: ?>
            <p class="auth__subtitle" style="margin-bottom:16px; color: var(--crimson);">
                <?php echo htmlspecialchars(implode(' ', $errors)); ?>
            </p>
            <a href="edit_profile.php" class="btn btn--primary">Go back & try again</a>
        <?php endif; ?>
        <p class="text-sm text-muted mt-4">Redirecting<?php echo $success ? ' to dashboard' : ''; ?>…</p>
    </div>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>
