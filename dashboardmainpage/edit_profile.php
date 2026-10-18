<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../user_auth_check.php';

    $user = aurora_current_user();
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    $row = aurora_fetch_one(
        'SELECT name, email, mobile, address FROM users WHERE id = ? LIMIT 1',
        'i',
        [(int)$user['id']]
    ) ?: ['name' => $user['name'], 'email' => $user['email'], 'mobile' => '', 'address' => ''];

    $current = basename($_SERVER['SCRIPT_NAME']);
    require __DIR__ . '/_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Profile — Aurora Library</title>
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
<div class="app-shell">
    <?php render_user_sidebar($current); ?>
    <main class="app-main" id="app-main">
        <?php render_user_header('Edit Profile'); ?>
        <div style="max-width:640px;margin:0 auto;">
            <div class="card-bordered">
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
                        <?php echo htmlspecialchars($flash['msg']); ?>
                    </div>
                <?php endif; ?>
                <form action="update.php" method="post">
                    <?php echo aurora_csrf_field(); ?>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($row['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($row['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="text" name="mobile" class="form-control" value="<?php echo htmlspecialchars($row['mobile'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea rows="3" name="address" class="form-control"><?php echo htmlspecialchars($row['address'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="update" class="btn btn-primary">Update</button>
                    <a href="view_profile.php" class="btn btn-default">Cancel</a>
                </form>
            </div>
        </div>
    </main>
</div>
<script src="../static/js/aurora.js"></script>
</body>
</html>