<?php
    
    require_once __DIR__ . '/../admin_auth_check.php';

    $catId   = aurora_validate_positive_int($_GET['cid'] ?? null);
    if (!$catId) {
        aurora_flash_set('error', 'Invalid category.');
        header('Location: manage_cat.php');
        exit;
    }

    $cat_name = '';
    $connection = aurora_db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!aurora_csrf_check($token)) {
            aurora_flash_set('error', 'Invalid security token.');
            header('Location: manage_cat.php');
            exit;
        }

        $newName = trim((string)($_POST['cat_name'] ?? ''));
        $validName = aurora_validate_name($newName);
        if ($validName === null) {
            aurora_flash_set('error', 'Please enter a valid category name (2-100 characters).');
            header('Location: edit_cat.php?cid=' . $catId);
            exit;
        }

        $stmt = mysqli_prepare($connection, 'UPDATE category SET cat_name = ? WHERE cat_id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $validName, $catId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        aurora_flash_set('success', 'Category updated successfully.');
        header('Location: manage_cat.php');
        exit;
    }

    $stmt = mysqli_prepare($connection, 'SELECT cat_name FROM category WHERE cat_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $catId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        aurora_flash_set('error', 'Category not found.');
        header('Location: manage_cat.php');
        exit;
    }
    $cat_name = $row['cat_name'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Category — Aurora Library</title>
    <meta charset="utf-8" name="viewport" content="width=device-width,intial-scale=1">
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
    <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
    <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="../static/css/aurora.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="navbar-header">
                <a class="navbar-brand" href="../Admin Dashboard Module/admin_dashboard.php">Library Management System (LMS)</a>
            </div>
            <font style="color: white"><span><strong>Welcome: <?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></strong></span></font>
            <font style="color: white"><span><strong>Email: <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></strong></font>
            <ul class="nav navbar-nav navbar-right">
              <li class="nav-item">
                <a class="nav-link" href="../logout.php">Logout</a>
              </li>
            </ul>
        </div>
    </nav><br>
    <span><marquee>This is library management system. Library opens at 8:00 AM and close at 8:00 PM</marquee></span><br><br>
        <center><h4>Edit Category</h4><br></center>
        <div class="row">
            <div class="col-md-4"></div>
            <div class="col-md-4">
                <form action="" method="post">
                    <?php echo aurora_csrf_field(); ?>
                    <div class="form-group">
                        <label for="name">Category Name:</label>
                        <input type="text" class="form-control" name="cat_name" value="<?php echo htmlspecialchars($cat_name); ?>" required>
                    </div>
                    <button type="submit" name="update_cat" class="btn btn-primary">Update Category</button>
                    <a href="manage_cat.php" class="btn btn-default">Cancel</a>
                </form>
            </div>
            <div class="col-md-4"></div>
        </div>
</body>
</html>
