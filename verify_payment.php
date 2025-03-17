<?php
session_start();
require 'db_connect.php';
require 'vendor/autoload.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// Razorpay API credentials
define('RAZORPAY_KEY_ID', 'rzp_test_rld4LdI8f12ukW');
define('RAZORPAY_KEY_SECRET', '9rVua7uBMX1bCUwUFicZTrH5');

header('Content-Type: application/json');

try {
    // Get the JSON POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($_SESSION['booking_details'])) {
        throw new Exception('Invalid session state');
    }

    $booking = $_SESSION['booking_details'];
    $booking_id = $data['booking_id'] ?? null;
    
    if ($booking_id != $booking['booking_id']) {
        throw new Exception('Invalid booking ID');
    }

    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    
    $attributes = [
        'razorpay_payment_id' => $data['razorpay_payment_id'],
        'razorpay_order_id' => $data['razorpay_order_id'],
        'razorpay_signature' => $data['razorpay_signature']
    ];

    $api->utility->verifyPaymentSignature($attributes);

    // Get payment details
    $payment = $api->payment->fetch($data['razorpay_payment_id']);

    if ($payment->status === 'captured') {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Insert payment record
            $sql = "INSERT INTO payments (
                booking_id, user_id, razorpay_payment_id, 
                razorpay_order_id, razorpay_signature, amount, 
                status, response_details
            ) VALUES (?, ?, ?, ?, ?, ?, 'successful', ?)";
            
            $user_id = $_SESSION['user_id'];
            $amount = $booking['total_price'];
            $response_details = json_encode($payment->toArray());
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisssds", 
                $booking_id,
                $user_id,
                $data['razorpay_payment_id'],
                $data['razorpay_order_id'],
                $data['razorpay_signature'],
                $amount,
                $response_details
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to record payment");
            }

            // Update booking status
            $update_sql = "UPDATE bookings SET 
                          status = 'confirmed',
                          payment_status = 'completed'
                          WHERE booking_id = ?";
            
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("i", $booking_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update booking status");
            }

            $conn->commit();
            unset($_SESSION['booking_details']); // Clear booking session data
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Payment verified successfully',
                'booking_id' => $booking_id
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } else {
        throw new Exception('Payment not captured');
    }
} catch (SignatureVerificationError $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payment signature']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
