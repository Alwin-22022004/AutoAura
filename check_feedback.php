<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['showFeedback' => false, 'error' => 'No user session']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check for bookings that need feedback
$sql = "SELECT booking_id, car_id 
        FROM bookings 
        WHERE user_id = ? 
        AND status = 'confirmed' 
        AND payment_status = 'completed' 
        AND feedback_status = 'pending'
        ORDER BY booking_date DESC 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'showFeedback' => true,
        'bookingId' => $row['booking_id'],
        'carId' => $row['car_id'],
        'debug' => 'Found pending feedback'
    ]);
} else {
    // For debugging, let's include the SQL query
    echo json_encode([
        'showFeedback' => false,
        'debug' => [
            'user_id' => $user_id,
            'message' => 'No pending feedback found'
        ]
    ]);
}
