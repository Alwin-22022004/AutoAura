<?php
require_once 'db_connect.php';

// Add mobile column if it doesn't exist
$check_column = "SHOW COLUMNS FROM users LIKE 'mobile'";
$result = $conn->query($check_column);

if ($result->num_rows == 0) {
    $alter_table = "ALTER TABLE users ADD COLUMN mobile VARCHAR(15) NOT NULL AFTER email";
    if ($conn->query($alter_table)) {
        echo "Mobile column added successfully!";
    } else {
        echo "Error adding mobile column: " . $conn->error;
    }
} else {
    echo "Mobile column already exists!";
}

$conn->close();
?>
