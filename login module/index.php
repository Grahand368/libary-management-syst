<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';
    require_once __DIR__ . '/../aurora_validation.php';
    aurora_session_start();
    aurora_no_cache_headers();

    // If the visitor is already signed in, skip the form and go straight to
    // the dashboard. This prevents the back-button from looping back onto
    // the login page and re-rendering it section-by-section.
    if (aurora_session_valid() && aurora_is_user()) {
        header('Location: ../dashboardmainpage/user-dashboard.php');
        exit;
    }

    $error = '';
    $blockedFor = 0; // seconds remaining when locked out
    if(isset($_POST['login'])){
        $email = aurora_normalize_email($_POST['email'] ?? '');
        if (!aurora_validate_email($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            $rl = aurora_rl_status('user', $email);
            if ($rl['blocked']) {
                $blockedFor = $rl['retry_after'];
                $error = 'Too many failed attempts. Try again in ' . aurora_rl_format_retry($rl['retry_after']) . '.';
            } else {
            $row = aurora_fetch_one(
                'select id, name, email, password, is_active, deleted_at from users where email = ? limit 1',
                's',
                [$email]
            );
            if(!$row){
                $error = 'No account found with that email.';
                $rl = aurora_rl_record_failure('user', $email);
                if ($rl['blocked']) {
                    $blockedFor = $rl['retry_after'];
                    $error = 'Too many failed attempts. Try again in ' . aurora_rl_format_retry($rl['retry_after']) . '.';
                }
            } else {
                $gate = aurora_user_can_login($row);
                if (!$gate['ok']) {
                    if ($gate['reason'] === 'deleted') {
                        $error = 'This account has been removed. Please contact the librarian.';
                    } else {
                        $error = 'Your account is currently deactivated. Please contact the librarian.';
                    }
                } else {
                $verified = aurora_verify_and_maybe_upgrade(
                    $_POST['password'],
                    $row['password'],
                    aurora_db(),
                    'users',
                    'email',
                    $row['email']
                );
                if($verified){
                    aurora_rl_record_success('user', $email);
                    aurora_login('user', $row);
                    header("Location: ../dashboardmainpage/user-dashboard.php");
                    exit;
                } else {
                    $rl = aurora_rl_record_failure('user', $email);
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
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aurora Library — Sign In</title>
    <link rel="stylesheet" href="../static/css/aurora.css">
</head>
    <body class="app-bg" onpageshow="if(event.persisted){location.reload();}">

<div class="auth">
    <!-- Left: Art panel -->
    <aside class="auth__art">
        <div class="auth__art-inner fade-in">
            <span class="eyebrow auth__art-eyebrow">Aurora Library · Est. 2026</span>
            <h1>Where <em>knowledge</em><br>meets elegance.</h1>
            <p>A curated sanctuary for readers, scholars, and dreamers. Sign in to explore thousands of titles, manage your collection, and continue your literary journey.</p>

            <div class="auth__art-quote">
                <p>"A library is a hospital for the mind."</p>
                <span>— Anonymous</span>
            </div>
        </div>

        <div class="auth__art-ticker">
            <span><span class="dot"></span> Open 9:00 AM — 9:00 PM</span>
            <span><span class="dot"></span> 12,000+ Volumes</span>
            <span><span class="dot"></span> AC Reading Halls</span>
        </div>
    </aside>

    <!-- Right: Form panel -->
    <section class="auth__panel">
        <div class="auth__card fade-up">
            <a href="index.php" class="brand mb-6">
                <span class="brand__mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </span>
                <span class="brand__text">
                    <strong>Aurora Library</strong>
                    <span>Member Portal</span>
                </span>
            </a>

            <div class="auth__tabs">
                <a href="index.php" class="auth__tab is-active">Member</a>
                <a href="admin_login.php" class="auth__tab">Administrator</a>
            </div>

            <div class="auth__header">
                <h2 class="auth__title">Welcome back.</h2>
                <p class="auth__subtitle">Sign in to your account to continue.</p>
            </div>

<?php if($error): ?>
                <?php echo aurora_toast_inline('error', $error); ?>
            <?php endif; ?>

            <?php if($blockedFor > 0): ?>
                <div class="text-sm" style="text-align:center;margin-bottom:16px;font-weight:600;padding:10px;background:rgba(155,28,28,0.12);color:var(--crimson);border-radius:10px;" id="aurora-block-msg">
                    Login locked. Try again in <span id="aurora-block-countdown" data-seconds="<?php echo (int)$blockedFor; ?>"><?php echo aurora_rl_format_retry($blockedFor); ?></span>.
                </div>
            <?php endif; ?>

        <form action="" method="post" autocomplete="off" id="login-form">
                <!-- Chrome autofill trap: hidden fields absorb the saved credentials -->
                <input type="text" name="email_x" tabindex="-1" autocomplete="no value" style="opacity:0;position:absolute;left:-9999px;width:0;height:0;">
                <input type="password" name="password_x" tabindex="-1" autocomplete="new-password" style="opacity:0;position:absolute;left:-9999px;width:0;height:0;">

                <div class="field">
                    <label class="field__label" for="email">Email Address</label>
                    <div class="input--with-icon">
                        <svg class="input__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
                        <input class="input" type="email" name="email" id="email" placeholder="you@aurora.library" required autocomplete="off">
                    </div>
                </div>

                <div class="field">
                    <label class="field__label" for="password">Password</label>
                    <div class="input--with-icon" style="position:relative;">
                        <svg class="input__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input class="input" type="password" name="password" id="password" placeholder="Enter your password" required autocomplete="new-password">
                        <button type="button" data-toggle-password="#password" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--ink-400);padding:4px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex-between mb-6" style="font-size:13px;">
                    <label class="flex gap-2" style="color:var(--ink-600);align-items:center;cursor:pointer;">
                        <input type="checkbox" style="accent-color:var(--gold-600);"> Remember me
                    </label>
                    <a href="#" style="color:var(--gold-700);font-weight:600;">Forgot password?</a>
                </div>

                <button type="submit" name="login" class="btn btn--primary btn--block btn--lg">
                    <span>Sign in</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </button>
            </form>

            <div class="auth__footer">
                New to Aurora? <a href="signup.php">Create an account</a>
            </div>
        </div>
    </section>
</div>

<script src="../static/js/aurora.js"></script>
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
<script>
(function(){
    // Wipe any browser autofill that may have survived autocomplete="off"
    // on back/forward navigation (bfcache restore or HTTP cache).
    function clearLoginForm(){
        var f = document.getElementById('login-form');
        if(!f) return;
        var inputs = f.querySelectorAll('input[type="email"], input[type="password"], input[type="text"]');
        for(var i=0;i<inputs.length;i++){
            inputs[i].value = '';
            inputs[i].autocomplete = 'off';
        }
    }
    window.addEventListener('pageshow', clearLoginForm);
    document.addEventListener('DOMContentLoaded', clearLoginForm);
    clearLoginForm();
})();
</script>
</body>
</html>

