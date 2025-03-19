<?php
include "db_connect.php"; // Your database connection file

if (isset($_POST['id']) && isset($_POST['status'])) {
    $id = intval($_POST['id']);
    $newStatus = ($_POST['status'] == "0") ? 1 : 0; // Toggle: 0 → 1 (disable), 1 → 0 (enable)

    $query = "UPDATE cars SET is_active = $newStatus WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        echo $newStatus; // Return new status to update button
    } else {
        echo "error";
    }
}
?> 