<?php
function logAction($conn, $action, $description) {
    $action = mysqli_real_escape_string($conn, $action);
    $description = mysqli_real_escape_string($conn, $description);

    $sql = "INSERT INTO activity_log (action, description) 
            VALUES ('$action', '$description')";
    mysqli_query($conn, $sql);
}
?>
