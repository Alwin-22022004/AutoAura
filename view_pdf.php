<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    exit('Access denied');
}

// Check if user_id is provided
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    http_response_code(400);
    exit('Invalid request');
}

$user_id = intval($_GET['user_id']);

// Prepare and execute query
$stmt = $conn->prepare("SELECT verification_doc FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit('Document not found');
}

$row = $result->fetch_assoc();
$file_path = $row['verification_doc'];

if (empty($file_path) || !file_exists($file_path)) {
    http_response_code(404);
    exit('Document not found');
}

// Get the file mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_path);
finfo_close($finfo);

// Verify it's a PDF
if ($mime_type !== 'application/pdf') {
    http_response_code(400);
    exit('Invalid file type');
}

// Set headers for PDF display
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="verification_document.pdf"');
header('Cache-Control: public, max-age=0');

// Output the file
readfile($file_path);
exit();

// Close database connection
$stmt->close();
$conn->close();
?>
