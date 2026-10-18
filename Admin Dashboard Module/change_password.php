<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../admin_auth_check.php';

    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Change Admin Password — Aurora Library</title>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <?php render_admin_sidebar($current); ?>
    <main class="app-main" id="app-main">
        <?php render_admin_header('Change Admin Password'); ?>
        <div style="max-width:520px;margin:0 auto;">
            <div class="card-bordered">
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                        <?php echo htmlspecialchars($flash['msg']); ?>
                    </div>
                <?php endif; ?>
                <form action="update_password.php" method="post">
                    <?php echo aurora_csrf_field(); ?>
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" class="form-control" name="old_password" autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" autocomplete="new-password">
                    </div>
                    <button type="submit" name="update" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>