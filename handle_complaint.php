<?php
session_start();
require_once 'db_connect.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}

header('Content-Type: application/json');

if (!isset($_POST['complaint_id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Complaint ID is required']));
}

$complaint_id = filter_var($_POST['complaint_id'], FILTER_VALIDATE_INT);
if ($complaint_id === false) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid complaint ID']));
}

// Get the new status (resolved or in_progress)
$status = isset($_POST['status']) ? $_POST['status'] : 'resolved';

// Validate status
$valid_statuses = ['resolved', 'in_progress'];
if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid status']));
}

try {
    // Update complaint status
    $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $complaint_id);
    
    if ($stmt->execute()) {
        $message = $status === 'resolved' ? 
            'Complaint marked as resolved successfully' : 
            'Complaint marked as in progress';
            
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        throw new Exception("Failed to update complaint status");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>
