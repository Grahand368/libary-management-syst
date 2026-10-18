<?php
    require_once __DIR__ . '/../aurora_auth.php';
    aurora_logout();
    header("Location: index.php");
    exit;
?>