<?php
session_start();
require('config.php');
require('db_connect.php');
require('vendor/autoload.php');

use Razorpay\Api\Api;

if (!isset($_SESSION['booking_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No booking found']);
    exit;
}

$booking_id = $_SESSION['booking_id'];

// Get booking details
$stmt = $conn->prepare("SELECT total_price FROM bookings WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid booking']);
    exit;
}

try {
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    $orderData = [
        'receipt' => 'booking_' . $booking_id,
        'amount' => $booking['total_price'] * 100, // Convert to paise
        'currency' => 'INR',
        'payment_capture' => 1 // Auto capture payment
    ];

    $order = $api->order->create($orderData);
    
    // Store order details in session for verification
    $_SESSION['razorpay_order_id'] = $order['id'];
    
    echo json_encode([
        'status' => 'success',
        'order_id' => $order['id'],
        'amount' => $orderData['amount']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
