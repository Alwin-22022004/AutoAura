<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email not found in session']);
    exit();
}

// Generate new OTP
$new_otp = mt_rand(100000, 999999);
$_SESSION['otp'] = $new_otp;

// Get the email from session
$to_email = $_SESSION['email'];

// Email subject
$subject = "Your New OTP for LUXE DRIVE Registration";

// Email message
$message = "Your new OTP for LUXE DRIVE registration is: " . $new_otp . "\n\n";
$message .= "This OTP will expire in 2 minutes.\n";
$message .= "If you didn't request this OTP, please ignore this email.";

// Headers
$headers = "From: LUXE DRIVE <noreply@luxedrive.com>\r\n";
$headers .= "Reply-To: noreply@luxedrive.com\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Send email
if(mail($to_email, $subject, $message, $headers)) {
    echo json_encode(['success' => true, 'otp' => $new_otp]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send email']);
}
?>
