<?php
    
    require_once __DIR__ . '/../admin_auth_check.php';

    require_once __DIR__ . '/../aurora_db.php';
    $connection = aurora_db();
    $name = "";
    $email = "";
    $mobile = "";

    $stmt = mysqli_prepare($connection, 'SELECT name, email, mobile FROM admins WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $_SESSION['email']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if ($row) {
        $name = $row['name'];
        $email = $row['email'];
        $mobile = $row['mobile'];
    }

    $categories = aurora_fetch_all('SELECT cat_id, cat_name FROM category ORDER BY cat_name');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Category</title>
    <meta charset="utf-8" name="viewport" content="width=device-width,intial-scale=1">
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="navbar-header">
                <a class="navbar-brand" href="admin_dashboard.php">Library Management System (LMS)</a>
            </div>
            <font style="color: white"><span><strong>Welcome: <?php echo htmlspecialchars($name); ?></strong></span></font>
            <font style="color: white"><span><strong>Email: <?php echo htmlspecialchars($email); ?></strong></font>
            <ul class="nav navbar-nav navbar-right">
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown">My Profile </a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="">View Profile</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#">Edit Profile</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="change_password.php">Change Password</a>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../logout.php">Logout</a>
              </li>
            </ul>
        </div>
    </nav><br>
    <nav class="navbar navbar-expand-lg navbar-light" style="background-color: #e3f2fd">
        <div class="container-fluid">
            
            <ul class="nav navbar-nav navbar-center">
              <li class="nav-item">
                <a class="nav-link" href="../Admin Dashboard Module/admin_dashboard.php">Dashboard</a>
              </li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown">Books </a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="../Add_books_lms/add_book.php">Add New Book</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="../Add_books_lms/manage_book.php">Manage Books</a>
                </div>
              </li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown">Category </a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="add_cat.php">Add New Category</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="manage_cat.php">Manage Category</a>
                </div>
              </li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown">Authors</a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="add_author.php">Add New Author</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="manage_authors.php">Manage Author</a>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../Issue_book_Module/issue_book.php">Issue Book</a>
              </li>
            </ul>
        </div>
    </nav><br>
    <center><h4>Manage Category</h4><br></center>
    <?php echo aurora_toast_render('info'); ?>
        <div class="row">
            <div class="col-md-2"></div>
            <div class="col-md-8">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="2" class="text-muted">No categories yet.</td></tr>
                    <?php else: foreach ($categories as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['cat_name']); ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="edit_cat.php?cid=<?php echo (int)$c['cat_id']; ?>">Edit</a>
                                <a class="btn btn-sm btn-outline-danger" href="delete_cat.php?cid=<?php echo (int)$c['cat_id']; ?>" onclick="return confirm('Delete this category?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-2"></div>
        </div>
</body>
</html>
