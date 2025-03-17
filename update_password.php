<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Check if user has valid session
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['password_reset_verified'])) {
    $response['message'] = 'Invalid session. Please try the password reset process again.';
    echo json_encode($response);
    exit();
}

// Get JSON data
$json_data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($json_data['password']) || !isset($json_data['confirmPassword'])) {
    $response['message'] = 'Both password and confirm password are required.';
    echo json_encode($response);
    exit();
}

$password = $json_data['password'];
$confirmPassword = $json_data['confirmPassword'];

// Validate password match
if ($password !== $confirmPassword) {
    $response['message'] = 'Passwords do not match.';
    echo json_encode($response);
    exit();
}

// Validate password strength
if (strlen($password) < 8) {
    $response['message'] = 'Password must be at least 8 characters long.';
    echo json_encode($response);
    exit();
}

if (!preg_match('/[A-Z]/', $password) || 
    !preg_match('/[a-z]/', $password) || 
    !preg_match('/[0-9]/', $password) || 
    !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
    $response['message'] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
    echo json_encode($response);
    exit();
}

$email = $_SESSION['reset_email'];
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    // First verify if the email exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$check_stmt) {
        throw new Exception("Prepare check statement failed: " . $conn->error);
    }
    
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No user found with this email address");
    }
    
    $check_stmt->close();

    // Start transaction
    if (!$conn->begin_transaction()) {
        throw new Exception("Could not start transaction");
    }

    // Update password in database (removed updated_at as it's not in the table)
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Prepare update statement failed: " . $conn->error);
    }

    $stmt->bind_param("ss", $hashedPassword, $email);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    if ($stmt->affected_rows > 0) {
        // Commit transaction
        if (!$conn->commit()) {
            throw new Exception("Commit failed");
        }
        
        // Clear all session variables related to password reset
        unset($_SESSION['reset_email']);
        unset($_SESSION['password_reset_verified']);
        unset($_SESSION['reset_otp']);
        unset($_SESSION['otp_timestamp']);
        
        $response['success'] = true;
        $response['message'] = 'Password updated successfully.';
        $response['redirect'] = 'auth-page.php';
    } else {
        throw new Exception("No rows were updated");
    }
} catch (Exception $e) {
    // Rollback transaction if it was started
    if ($conn && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    error_log("Password reset error: " . $e->getMessage());
    $response['message'] = 'An error occurred while updating the password. Please try again.';
} finally {
    // Clean up
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}

echo json_encode($response);
exit();
?>
