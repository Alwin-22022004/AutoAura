<?php
session_start();
require_once 'db_connect.php';
require_once 'mail_config.php';

// Function to generate a secure OTP
function generateSecureOTP() {
    try {
        return sprintf("%06d", random_int(0, 999999));
    } catch (Exception $e) {
        return sprintf("%06d", mt_rand(0, 999999)); // Fallback to mt_rand if random_int fails
    }
}

// Prevent direct access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method';
    header("Location: forgot-password.php");
    exit;
}

// Get and validate email
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (!$email) {
    $_SESSION['error_message'] = 'Please enter a valid email address';
    header("Location: forgot-password.php");
    exit;
}

try {
    // Verify database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed. Please try again later.');
    }

    // Check if email exists in database
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Database preparation failed: ' . $conn->error);
    }

    $stmt->bind_param("s", $email);
    
    if (!$stmt->execute()) {
        throw new Exception('Database execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // Check if there was a recent OTP request
        $lastRequest = $_SESSION['last_otp_request'] ?? 0;
        $currentTime = time();
        
        if ($currentTime - $lastRequest < 60) { // 60 seconds cooldown
            throw new Exception('Please wait 1 minute before requesting another code.');
        }

        // Generate and hash OTP
        $otp = generateSecureOTP();
        $hashedOTP = password_hash($otp, PASSWORD_DEFAULT);
        
        // Store OTP data in session
        $_SESSION['reset_otp'] = $hashedOTP;
        $_SESSION['reset_email'] = $email;
        $_SESSION['otp_timestamp'] = $currentTime;
        $_SESSION['last_otp_request'] = $currentTime;
        $_SESSION['otp_attempts'] = 0;

        // Generate and send email
        $emailContent = generateOTPEmailContent($otp, 'password reset');
        
        try {
            if (!sendMail($email, 'Password Reset Code - Auto Aura', $emailContent['html'], $emailContent['text'])) {
                throw new Exception('Failed to send verification code.');
            }
            
            // Clear any existing error messages
            unset($_SESSION['error_message']);
            $_SESSION['success_message'] = 'Verification code sent successfully! Please check your email.';
            header("Location: otp2.php?email=" . urlencode($email));
            exit;
            
        } catch (Exception $e) {
            // Clean up session data if email fails
            unset(
                $_SESSION['reset_otp'],
                $_SESSION['reset_email'],
                $_SESSION['otp_timestamp'],
                $_SESSION['otp_attempts']
            );
            throw new Exception('Failed to send verification code: ' . $e->getMessage());
        }
    } else {
        // Use a vague message for security
        throw new Exception('If this email exists in our system, you will receive a password reset code.');
    }

} catch (Exception $e) {
    error_log('Password reset error for ' . $email . ': ' . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: forgot-password.php");
    exit;

} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>
