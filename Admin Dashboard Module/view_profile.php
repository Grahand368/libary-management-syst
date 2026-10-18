<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../admin_auth_check.php';
    require_once __DIR__ . '/../aurora_db.php';

    $row = aurora_fetch_one(
        'SELECT name, email, mobile FROM admins WHERE id = ? LIMIT 1',
        'i',
        [(int)($_SESSION['id'] ?? 0)]
    ) ?: ['name' => $_SESSION['name'] ?? '', 'email' => $_SESSION['email'] ?? '', 'mobile' => ''];

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Profile — Aurora Library</title>
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
        <?php render_admin_header('Admin Profile'); ?>
        <div style="max-width:640px;margin:0 auto;">
            <div class="card-bordered">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['name'] ?? ''); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['email'] ?? ''); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Mobile</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['mobile'] ?? ''); ?>" disabled>
                </div>
                <div style="display:flex;gap:8px;">
                    <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
                    <a href="change_password.php" class="btn btn-default">Change Password</a>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>