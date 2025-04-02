<?php
session_start();
require_once 'config.php';

if (isset($_POST['booking_id']) && isset($_POST['status'])) {
    $booking_id = $_POST['booking_id'];
    $status = $_POST['status'];
    
    // Update the status
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = ? WHERE booking_id = ?");
    $stmt->bind_param("si", $status, $booking_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
}

$conn->close();
?>
