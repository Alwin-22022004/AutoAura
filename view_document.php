<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-page.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    die("No document ID provided");
}

$user_id = $_SESSION['user_id'];
$doc_user_id = intval($_GET['id']);

// Security check - only allow users to view their own documents
if ($user_id !== $doc_user_id) {
    die("Unauthorized access");
}

// Get the document from database
$stmt = $conn->prepare("SELECT verification_doc FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($row['verification_doc']) {
        // Output PDF headers
        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=verification_document.pdf");
        echo $row['verification_doc'];
    } else {
        echo "No document found";
    }
} else {
    echo "User not found";
}

$stmt->close();
?>
