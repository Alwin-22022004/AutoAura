<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

try {
    // Get the payment data from POST request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid payment data received');
    }

    // Verify required fields are present
    $required_fields = ['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Your Razorpay API key and secret
    $api_key = 'YOUR_RAZORPAY_KEY_ID';
    $api_secret = 'YOUR_RAZORPAY_KEY_SECRET';

    // Verify the payment signature
    $generated_signature = hash_hmac('sha256', 
        $data['razorpay_order_id'] . '|' . $data['razorpay_payment_id'], 
        $api_secret
    );

    if ($generated_signature !== $data['razorpay_signature']) {
        throw new Exception('Payment signature verification failed');
    }

    // If signature is valid, update the payment status in database
    $stmt = $conn->prepare("UPDATE payments SET status = 'completed' WHERE razorpay_payment_id = ?");
    $stmt->bind_param("s", $data['razorpay_payment_id']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update payment status in database');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 