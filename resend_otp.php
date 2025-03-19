<?php
session_start();
require_once 'db_connect.php';
require_once 'mail_config.php';
header('Content-Type: application/json');

// Check if the request is from AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Verify session data exists
if (!isset($_SESSION['temp_user_data']) || !isset($_SESSION['temp_user_data']['email'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please try again.']);
    exit();
}

try {
    // Generate new OTP
    $new_otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Store new OTP in session with timestamp
    $_SESSION['temp_user_data']['otp'] = $new_otp;
    $_SESSION['temp_user_data']['otp_timestamp'] = time();
    
    // Get the email from session
    $to_email = $_SESSION['temp_user_data']['email'];
    
    // Generate email content using the helper function
    $emailContent = generateOTPEmailContent($new_otp, 'verification');
    
    // Send email using the configured mailer
    if(sendMail($to_email, 'Your AutoAura Cars Verification Code', $emailContent['html'], $emailContent['text'])) {
        // Log successful OTP generation
        error_log("New OTP generated for {$to_email} at " . date('Y-m-d H:i:s'));
        
        echo json_encode([
            'success' => true,
            'message' => 'New verification code has been sent to your email.'
        ]);
    } else {
        throw new Exception('Failed to send email');
    }
} catch (Exception $e) {
    error_log("Error in resend_otp.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send verification code. Please try again.'
    ]);
}
?>
