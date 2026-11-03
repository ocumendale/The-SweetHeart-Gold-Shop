<?php
include 'db.php';
include 'log_action.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $sql = "DELETE FROM products WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        logAction($conn, 'PERMANENT DELETE', "Permanently deleted product with ID: $id");
        header("Location: recyclebin.php?msg=deleted");
        exit();
    } else {
        header("Location: recyclebin.php?msg=error");
        exit();
    }
} else {
    header("Location: recyclebin.php");
    exit();
}
?>
