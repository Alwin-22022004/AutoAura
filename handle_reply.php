<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reply_message'], $_POST['user_id'])) {
    $reply = $conn->real_escape_string($_POST['reply_message']);
    $user_id = intval($_POST['user_id']);

    // Insert the reply
    $insert_sql = "INSERT INTO enquiries (user_id, message, is_admin_reply, created_at) 
                   VALUES ($user_id, '$reply', 1, NOW())";
    
    if ($conn->query($insert_sql)) {
        // Redirect back to the messages section with the same user selected
        header("Location: owner.php?section=messages&user=" . $user_id);
    } else {
        // If there's an error, redirect with error parameter
        header("Location: owner.php?section=messages&user=" . $user_id . "&error=1");
    }
} else {
    // Invalid request, redirect to dashboard
    header("Location: owner.php");
}

$conn->close();
exit();
?>
