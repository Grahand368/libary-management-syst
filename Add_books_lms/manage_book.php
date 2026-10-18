<?php
    require_once __DIR__ . '/../aurora_auth.php';
    require_once __DIR__ . '/../aurora_db.php';
    require_once __DIR__ . '/../aurora_error.php';
    require_once __DIR__ . '/../admin_auth_check.php';
    #fetch data from database
    $connection = mysqli_connect("localhost","root","");
    $db = mysqli_select_db($connection,"lms");
    $name = "";
    $email = "";
    $mobile = "";
    $query = "select * from admins where email = '$_SESSION[email]'";
    $query_run = mysqli_query($connection,$query);
    while ($row = mysqli_fetch_assoc($query_run)){
        $name = $row['name'];
        $email = $row['email'];
        $mobile = $row['mobile'];
    }

    $flash = aurora_flash_pull();
    if (!$flash && !empty($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
    }
    $csrf = aurora_csrf_token();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Book</title>
    <meta charset="utf-8" name="viewport" content="width=device-width,intial-scale=1">
    <link rel="stylesheet" type="text/css" href="../bootstrap-4.4.1/css/bootstrap.min.css">
      <script type="text/javascript" src="../bootstrap-4.4.1/js/juqery_latest.js"></script>
      <script type="text/javascript" src="../bootstrap-4.4.1/js/bootstrap.min.js"></script>
      <script type="text/javascript">
          function alertMsg(){
              alert("Book added successfully...");
              window.location.href = "admin_dashboard.php";
          }
      </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="navbar-header">
                <a class="navbar-brand" href="admin_dashboard.php">Library Management System (LMS)</a>
            </div>
            <font style="color: white"><span><strong>Welcome: <?php echo $_SESSION['name'];?></strong></span></font>
            <font style="color: white"><span><strong>Email: <?php echo $_SESSION['email'];?></strong></font>
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
                <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
              </li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown">Books </a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="add_book.php">Add New Book</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="manage_book.php">Manage Books</a>
                </div>
              </li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown">Category </a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="../Manage_Book_Categories/add_cat.php">Add New Category</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="../Manage_Book_Categories/manage_cat.php">Manage Category</a>
                </div>
              </li>
               <li class="nav-item dropdown">
                 <a class="nav-link dropdown-toggle" data-toggle="dropdown">Authors</a>
                 <div class="dropdown-menu">
                     <a class="dropdown-item" href="../Manage_Book_Categories/add_author.php">Add New Author</a>
                     <div class="dropdown-divider"></div>
                     <a class="dropdown-item" href="../Manage_Book_Categories/manage_authors.php">Manage Author</a>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="issue_book.php">Issue Book</a>
              </li>
            </ul>
        </div>
    </nav><br>
    <center><h4>Manage Books</h4><br></center>
        <?php if ($flash): ?>
            <?php echo aurora_toast_inline($flash['type'], $flash['msg']); ?>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-2"></div>
            <div class="col-md-8">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>ISBN No.</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <?php
                        $connection = mysqli_connect("localhost","root","");
                        $db = mysqli_select_db($connection,"lms");
                        $query = "select * from books";
                        $query_run = mysqli_query($connection,$query);
                        while ($row = mysqli_fetch_assoc($query_run)){
                            $bid = (int)$row['book_id'];
                            $bno = htmlspecialchars($row['book_no']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['book_name']);?></td>
                                <td><?php echo (int)$row['author_id'];?></td>
                                <td><?php echo (int)$row['cat_id'];?></td>
                                <td><?php echo $bno;?></td>
                                <td><?php echo htmlspecialchars((string)$row['book_price']);?></td>
                                <td>
                                    <a class="btn" href="edit_book.php?bn=<?php echo $bid;?>">Edit</a>
                                    <form method="POST" action="delete_book.php" style="display:inline;"
                                          onsubmit="return confirm('Delete this book?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>">
                                        <input type="hidden" name="book_id"    value="<?php echo $bid; ?>">
                                        <button type="submit" class="btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php
                        }
                    ?>
                </table>
            </div>
            <div class="col-md-2"></div>
        </div>
</body>
</html>