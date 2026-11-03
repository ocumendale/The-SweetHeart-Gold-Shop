<?php
include 'db.php';
include 'log_action.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $sql = "UPDATE products SET is_deleted = 0 WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        logAction($conn, 'RESTORE', "Restored product with ID: $id");
        header("Location: recyclebin.php?msg=restored");
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
