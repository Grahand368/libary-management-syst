<?php
    
    require_once __DIR__ . '/../admin_auth_check.php';

    $catId = aurora_validate_positive_int($_GET['cid'] ?? null);
    if (!$catId) {
        aurora_flash_set('error', 'Invalid category.');
        header('Location: manage_cat.php');
        exit;
    }

    $connection = aurora_db();
    $stmt = mysqli_prepare($connection, 'DELETE FROM category WHERE cat_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $catId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
?>
<script type="text/javascript">
    alert("Category Deleted successfully...");
    window.location.href = "manage_cat.php";
</script>
