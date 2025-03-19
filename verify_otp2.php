<?php
session_start();
require_once 'db_connect.php';

// Function to redirect with error message
function redirectWithError($message) {
    $_SESSION['error_message'] = $message;
    header("Location: otp2.php?email=" . urlencode($_SESSION['reset_email']));
    exit();
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithError('Invalid request method');
}

// Check if session variables exist
if (!isset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['otp_timestamp'])) {
    redirectWithError('OTP session expired. Please request a new code.');
}

// Check if OTP has expired (10 minutes)
if (time() - $_SESSION['otp_timestamp'] > 600) {
    unset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['otp_timestamp']);
    redirectWithError('OTP has expired. Please request a new code.');
}

// Get the entered OTP from form inputs
$entered_otp = '';
for ($i = 1; $i <= 6; $i++) {
    if (!isset($_POST['otp' . $i]) || !ctype_digit($_POST['otp' . $i])) {
        redirectWithError('Please enter a valid 6-digit code.');
    }
    $entered_otp .= $_POST['otp' . $i];
}

// Verify OTP using password_verify since the stored OTP is hashed
if (password_verify($entered_otp, $_SESSION['reset_otp'])) {
    // OTP is correct - set verification flag and redirect to password reset
    $_SESSION['password_reset_verified'] = true;
    
    // Clean up OTP session data but keep reset_email for the password reset page
    unset($_SESSION['reset_otp'], $_SESSION['otp_timestamp'], $_SESSION['otp_attempts']);
    
    // Redirect to password reset page
    header("Location: reset_password.php");
    exit();
} else {
    // Increment attempt counter
    $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
    
    // Check if maximum attempts reached (3 attempts)
    if ($_SESSION['otp_attempts'] >= 3) {
        // Clean up all session data
        unset(
            $_SESSION['reset_otp'],
            $_SESSION['reset_email'],
            $_SESSION['otp_timestamp'],
            $_SESSION['otp_attempts']
        );
        redirectWithError('Too many invalid attempts. Please request a new code.');
    }
    
    // Calculate remaining attempts
    $remaining_attempts = 3 - $_SESSION['otp_attempts'];
    redirectWithError("Invalid code. {$remaining_attempts} attempts remaining.");
}
?>