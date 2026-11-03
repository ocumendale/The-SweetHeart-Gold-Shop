<?php
include 'db.php';
include 'log_action.php'; // our logging function

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // sanitize

    // Soft delete instead of hard delete
    $sql = "UPDATE products SET is_deleted = 1 WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        // Log the action
        logAction($conn, 'DELETE', "Soft deleted product with ID: $id");

        header("Location: inventory.php?msg=deleted");
        exit();
    } else {
        header("Location: inventory.php?msg=error");
        exit();
    }
} else {
    header("Location: inventory.php");
    exit();
}
?>
