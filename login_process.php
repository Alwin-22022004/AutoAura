<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Sanitize and validate input
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        // Input validation
        $errors = [];
        
        if (!$email) {
            $errors[] = "Please enter a valid email address.";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required.";
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            header("Location: auth-page.php");
            exit();
        }

        // Check for admin credentials
        if ($email === 'admin@gmail.com' && $password === 'Admin@123') {
            $_SESSION['user_id'] = 'admin';
            $_SESSION['user_name'] = 'Administrator';
            $_SESSION['is_admin'] = true;
            header("Location: admin.php");
            exit();
        }

        // Check for owner credentials
        if ($email === 'owner@gmail.com' && $password === 'Owner@123') {
            $_SESSION['user_id'] = 'owner';
            $_SESSION['user_name'] = 'Owner';
            $_SESSION['is_owner'] = true;
            header("Location: owner.php");
            exit();
        }

        // Check if user exists and verify password
        $stmt = $conn->prepare("SELECT id, fullname, email, password FROM users WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $_SESSION['error'] = "Invalid email or password.";
            header("Location: auth-page.php");
            exit();
        }

        $user = $result->fetch_assoc();
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Use a generic error message for security
            $_SESSION['error'] = "Invalid email or password.";
            header("Location: auth-page.php");
            exit();
        }

        // Set session variables for regular users
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['fullname'];
        $_SESSION['is_admin'] = false;
        
        // Redirect to dashboard
        header("Location: dashboard.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred. Please try again later.";
        header("Location: auth-page.php");
        exit();
    }
} else {
    header("Location: auth-page.php");
    exit();
}
?>