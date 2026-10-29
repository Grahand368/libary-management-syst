<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';
    require_once __DIR__ . '/../aurora_validation.php';
    aurora_session_start();
    aurora_no_cache_headers();

    if (aurora_session_valid() && aurora_is_admin()) {
        header('Location: ../Admin Dashboard Module/admin_dashboard.php');
        exit;
    }

    $error = '';
    $blockedFor = 0;
    if(isset($_POST['login'])){
        $email = aurora_normalize_email($_POST['email'] ?? '');
        if (!aurora_validate_email($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            $rl = aurora_rl_status('admin', $email);
            if ($rl['blocked']) {
                $blockedFor = $rl['retry_after'];
                $error = 'Too many failed attempts. Try again in ' . aurora_rl_format_retry($rl['retry_after']) . '.';
            } else {
            $row = aurora_fetch_one(
                'select id, name, email, password from admins where email = ? limit 1',
                's',
                [$email]
            );
            if(!$row){
                $error = 'No administrator account found.';
                $rl = aurora_rl_record_failure('admin', $email);
                if ($rl['blocked']) {
                    $blockedFor = $rl['retry_after'];
                    $error = 'Too many failed attempts. Try again in ' . aurora_rl_format_retry($rl['retry_after']) . '.';
                }
            } else {
                $verified = aurora_verify_and_maybe_upgrade(
                    $_POST['password'],
                    $row['password'],
                    aurora_db(),
                    'admins',
                    'email',
                    $row['email']
                );
                if($verified){
                    aurora_rl_record_success('admin', $email);
                    aurora_login('admin', $row);
                    header("Location: ../Admin Dashboard Module/admin_dashboard.php");
                    exit;
                } else {
                    $rl = aurora_rl_record_failure('admin', $email);
                    if ($rl['blocked']) {
                        $blockedFor = $rl['retry_after'];
                        $error = 'Too many failed attempts. Try again in ' . aurora_rl_format_retry($rl['retry_after']) . '.';
                    } else {
                        $remaining = AURORA_RL_MAX - $rl['attempts'];
                        $error = 'Incorrect password. Please try again. ' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' remaining.';
                    }
                }
            }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aurora Library — Administrator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body class="app-bg" onpageshow="if(event.persisted){location.reload();}">

<div class="auth">
    <aside class="auth__art">
        <div class="auth__art-inner fade-in">
            <span class="eyebrow auth__art-eyebrow">Aurora Library · Administration</span>
            <h1>Curate the <em>collection</em> with precision.</h1>
            <p>Manage books, categories, authors, and member accounts from a single, elegant control center designed for librarians.</p>

            <div class="auth__art-quote">
                <p>"The library is the temple of learning."</p>
                <span>— Andrew Carnegie</span>
            </div>
        </div>

        <div class="auth__art-ticker">
            <span><span class="dot"></span> Restricted Access</span>
            <span><span class="dot"></span> Encrypted Session</span>
            <span><span class="dot"></span> Audit Logged</span>
        </div>
    </aside>

    <section class="auth__panel">
        <div class="auth__card fade-up">
            <div class="auth__brand">
                <div class="auth__brand-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                </div>
                <div class="auth__brand-text">
                    <strong>Aurora Library</strong>
                    <span>ADMINISTRATION PORTAL</span>
                </div>
            </div>

            <div class="auth__role-selector" role="radiogroup" aria-label="Select portal">
                <a href="index.php" class="auth__role-btn" role="radio" aria-checked="false" tabindex="-1">
                    <span>Member</span>
                </a>
                <a href="admin_login.php" class="auth__role-btn is-active" role="radio" aria-checked="true">
                    <span>Administrator</span>
                </a>
            </div>

            <div class="auth__header">
                <h2 class="auth__title">Welcome back, Administrator</h2>
                <p class="auth__subtitle">Sign in to manage your library with confidence.</p>
            </div>

            <?php if($error): ?>
                <?= aurora_toast_inline('error', $error) ?>
            <?php endif; ?>

            <?php if($blockedFor > 0): ?>
                <div class="auth__rate-limit" id="aurora-block-msg" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <span>Login locked. Try again in <span id="aurora-block-countdown" data-seconds="<?php echo (int)$blockedFor; ?>"><?php echo aurora_rl_format_retry($blockedFor); ?></span>.</span>
                </div>
            <?php endif; ?>

            <form action="" method="post" autocomplete="off" id="login-form" novalidate>
                <input type="text" name="email_x" tabindex="-1" autocomplete="no value" style="opacity:0;position:absolute;left:-9999px;width:0;height:0;">
                <input type="password" name="password_x" tabindex="-1" autocomplete="new-password" style="opacity:0;position:absolute;left:-9999px;width:0;height:0;">

                <div class="field">
                    <label class="field__label" for="email">Admin Email</label>
                    <div class="input--with-icon">
                        <svg class="input__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
                        <input class="input" type="email" name="email" id="email" placeholder="admin@aurora.library" required autocomplete="email" aria-describedby="email-hint">
                    </div>
                    <span class="field__hint" id="email-hint">Your administrator email address</span>
                </div>

                <div class="field">
                    <label class="field__label" for="password">Password</label>
                    <div class="input--with-icon">
                        <svg class="input__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input class="input" type="password" name="password" id="password" placeholder="Enter your password" required autocomplete="current-password" aria-describedby="password-hint">
                        <button type="button" class="input__toggle" data-toggle-password="#password" aria-label="Show password" aria-controls="password" aria-pressed="false">
                            <svg class="input__toggle-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <span class="field__hint" id="password-hint">Your secure password</span>
                </div>

                <div class="field field--remember">
                    <label class="checkbox-wrapper" for="remember">
                        <input type="checkbox" id="remember" name="remember" value="1" aria-describedby="remember-hint">
                        <span class="checkbox-box">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <span class="checkbox-label">Keep me signed in</span>
                    </label>
                    <a href="#" class="auth__help-link">Forgot password?</a>
                </div>

                <button type="submit" name="login" class="btn btn--primary btn--block btn--lg auth__submit-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Sign In to Admin Console</span>
                </button>
            </form>

            <div class="auth__footer">
                <a href="index.php" class="auth__footer-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                    <span>Back to Member Sign In</span>
                </a>
            </div>
        </div>
    </section>
</div>

<script src="../static/js/aurora.js"></script>
<script>
(function(){
    function clearLoginForm(){
        var f = document.getElementById('login-form');
        if(!f) return;
        var inputs = f.querySelectorAll('input[type="email"], input[type="password"], input[type="text"]');
        for(var i=0;i<inputs.length;i++){
            inputs[i].value = '';
            inputs[i].autocomplete = 'off';
        }
    }
    window.addEventListener('pageshow', function(e){
        clearLoginForm();
    });
    document.addEventListener('DOMContentLoaded', clearLoginForm);
    clearLoginForm();
})();
</script>
<script>
(function(){
    var el = document.getElementById('aurora-block-countdown');
    if (!el) return;
    var left = parseInt(el.getAttribute('data-seconds') || '0', 10);
    if (left <= 0) return;
    function fmt(s){
        if (s >= 60) {
            var m = Math.floor(s/60), r = s % 60;
            return (r > 0) ? (m + ' min ' + r + ' sec') : (m + ' min');
        }
        return s + ' sec';
    }
    var iv = setInterval(function(){
        left -= 1;
        if (left <= 0) {
            clearInterval(iv);
            var box = document.getElementById('aurora-block-msg');
            if (box) box.style.display = 'none';
            return;
        }
        el.textContent = fmt(left);
    }, 1000);
})();
</script>
</body>
</html>
