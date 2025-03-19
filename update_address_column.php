<?php
require_once 'db_connect.php';

// Modify address column to be nullable
$sql = "ALTER TABLE users MODIFY COLUMN address VARCHAR(255)";
if ($conn->query($sql) === TRUE) {
    echo "Address column updated successfully to be nullable";
} else {
    echo "Error updating address column: " . $conn->error;
}
?>
