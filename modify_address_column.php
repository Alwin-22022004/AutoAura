<?php
require_once 'db_connect.php';

// Modify address column to be nullable with a default value of NULL
$sql = "ALTER TABLE users MODIFY COLUMN address VARCHAR(255) DEFAULT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Address column modified successfully to be nullable\n";
} else {
    echo "Error modifying address column: " . $conn->error . "\n";
}
?>
