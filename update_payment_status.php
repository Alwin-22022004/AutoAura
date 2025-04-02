<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate required parameters
if (!isset($_POST['booking_id']) || !isset($_POST['status']) || !isset($_POST['amount'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$booking_id = $_POST['booking_id'];
$status = $_POST['status'];
$amount = $_POST['amount'];

// Generate unique IDs
$payment_id = time() . rand(1000, 9999);
$order_id = 'ORD' . time() . rand(100, 999);

// Validate status
$valid_statuses = ['pending', 'captured'];
if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get user_id from bookings table
    $user_stmt = $conn->prepare("SELECT user_id FROM bookings WHERE booking_id = ?");
    $user_stmt->bind_param("i", $booking_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        throw new Exception("Booking not found");
    }
    
    $user_id = $user_result->fetch_assoc()['user_id'];

    if ($status === 'captured') {
        // Check if payment record already exists
        $check_stmt = $conn->prepare("SELECT payment_id FROM payments WHERE booking_id = ?");
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows === 0) {
            // Insert new payment record with payment_id, order_id and user_id
            $payment_stmt = $conn->prepare("INSERT INTO payments (payment_id, order_id, booking_id, user_id, amount, status) VALUES (?, ?, ?, ?, ?, ?)");
            $payment_stmt->bind_param("ssiids", $payment_id, $order_id, $booking_id, $user_id, $amount, $status);
            $payment_stmt->execute();
        } else {
            // Update existing payment record
            $payment_stmt = $conn->prepare("UPDATE payments SET status = ?, amount = ? WHERE booking_id = ?");
            $payment_stmt->bind_param("sdi", $status, $amount, $booking_id);
            $payment_stmt->execute();
        }
    }

    // Update booking status
    $booking_stmt = $conn->prepare("UPDATE bookings SET payment_status = ? WHERE booking_id = ?");
    $booking_stmt->bind_param("si", $status, $booking_id);
    $booking_stmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment status updated successfully',
        'data' => [
            'booking_id' => $booking_id,
            'user_id' => $user_id,
            'payment_id' => $payment_id,
            'order_id' => $order_id,
            'status' => $status,
            'amount' => $amount
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>