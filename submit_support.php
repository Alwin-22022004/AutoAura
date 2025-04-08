<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to submit a support request'
    ]);
    exit;
}

// Validate input
if (!isset($_POST['subject']) || !isset($_POST['message'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Both subject and message are required'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

// Validate content length
if (empty($subject) || strlen($subject) > 255) {
    echo json_encode([
        'success' => false,
        'message' => 'Subject is required and must be less than 255 characters'
    ]);
    exit;
}

if (empty($message)) {
    echo json_encode([
        'success' => false,
        'message' => 'Message cannot be empty'
    ]);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();

    // Insert support request into complaints table
    $stmt = $conn->prepare("INSERT INTO complaints (user_id, subject, description, status) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param("iss", $user_id, $subject, $message);
    
    if ($stmt->execute()) {
        // Get the complaint ID
        $complaint_id = $conn->insert_id;

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Your support request has been submitted successfully! We will get back to you soon.',
            'complaint_id' => $complaint_id
        ]);
    } else {
        throw new Exception("Failed to submit support request");
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while submitting your request. Please try again.'
    ]);

    // Log the error (in a production environment)
    error_log('Support request error: ' . $e->getMessage());
}

$stmt->close();
$conn->close();
?>
