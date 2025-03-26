<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['action']) || !isset($_POST['bookingId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'];
$booking_id = $_POST['bookingId'];
$action = $_POST['action'];

// Verify booking belongs to user
$check_sql = "SELECT car_id FROM bookings WHERE booking_id = ? AND user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $booking_id, $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if (!$row = $result->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$car_id = $row['car_id'];

if ($action === 'skip') {
    $sql = "UPDATE bookings SET feedback_status = 'skipped' WHERE booking_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update feedback status']);
    }
} elseif ($action === 'submit') {
    if (!isset($_POST['rating']) || !isset($_POST['reviewText'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    $rating = (int)$_POST['rating'];
    $review_text = trim($_POST['reviewText']);

    if ($rating < 1 || $rating > 5 || empty($review_text)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid rating or review text']);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert review
        $review_sql = "INSERT INTO reviews (booking_id, user_id, car_id, rating, review_text) 
                       VALUES (?, ?, ?, ?, ?)";
        $review_stmt = $conn->prepare($review_sql);
        $review_stmt->bind_param("iiiis", $booking_id, $user_id, $car_id, $rating, $review_text);
        $review_stmt->execute();

        // Update booking status
        $update_sql = "UPDATE bookings SET feedback_status = 'submitted' WHERE booking_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $booking_id);
        $update_stmt->execute();

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit feedback']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
}
