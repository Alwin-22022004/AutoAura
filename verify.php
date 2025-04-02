<?php
session_start();
require('config.php');
require('db_connect.php');
require('vendor/autoload.php');

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// Prevent any output before JSON response
error_reporting(0);

header('Content-Type: application/json');

$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

// Get payment details from POST
$razorpay_payment_id = $_POST['razorpay_payment_id'] ?? null;
$razorpay_order_id = $_POST['razorpay_order_id'] ?? null;
$razorpay_signature = $_POST['razorpay_signature'] ?? null;
$booking_id = $_SESSION['booking_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$razorpay_payment_id || !$razorpay_order_id || !$razorpay_signature || !$booking_id || !$user_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing payment information']);
    exit;
}

try {
    // Verify signature
    $attributes = [
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_signature' => $razorpay_signature
    ];
    
    $api->utility->verifyPaymentSignature($attributes);
    
    // Start transaction
    $conn->begin_transaction();
    
    // Get payment details
    $payment = $api->payment->fetch($razorpay_payment_id);
    
    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO payments (booking_id, payment_id, order_id, amount, currency, status,user_id) VALUES (?, ?, ?, ?, ?, ?,?)");
    $amount = $payment->amount / 100; // Convert from paise to rupees
    $status = $payment->status;
    $stmt->bind_param("isssssi", $booking_id, $razorpay_payment_id, $razorpay_order_id, $amount, $payment->currency, $status,$user_id);
    $stmt->execute();
    
    // Update booking payment status
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'completed', status = 'confirmed' WHERE booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Store booking ID for confirmation page
    $_SESSION['completed_booking_id'] = $booking_id;
    
    // Clear payment session variables
    unset($_SESSION['booking_id']);
    unset($_SESSION['razorpay_order_id']);
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Payment successful',
        'redirect' => "booking-confirmation.php?id=" . $booking_id
    ]);
} catch (SignatureVerificationError $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payment signature']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
