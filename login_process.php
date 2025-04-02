<?php
session_start();
require_once 'db_connect.php';

// Handle Google login
if (isset($_POST['google_login'])) {
    $google_email = filter_var(trim($_POST['google_email']), FILTER_VALIDATE_EMAIL);
    $google_name = trim($_POST['google_name']);
    
    if (!$google_email) {
        $_SESSION['error'] = "Invalid Google email.";
        header("Location: auth-page.php");
        exit();
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, fullname, mobile, verification_doc, active FROM users WHERE email = ?");
    $stmt->bind_param("s", $google_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Create new user with active status
        $stmt = $conn->prepare("INSERT INTO users (email, fullname, auth_type, active) VALUES (?, ?, 'google', 'active')");
        $stmt->bind_param("ss", $google_email, $google_name);
        $stmt->execute();
        $user_id = $conn->insert_id;
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $google_name;
        $_SESSION['is_admin'] = false;
        
        // Redirect to profile completion
        header("Location: user-profile.php?complete_profile=1");
        exit();
    } else {
        // Existing user
        $user = $result->fetch_assoc();
        
        // Check account status FIRST before setting any session variables
        if ($user['active'] === 'blocked') {
            $_SESSION['error'] = "Your account has been blocked. Please contact the administrator.";
            header("Location: auth-page.php");
            exit();
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['fullname'];
        $_SESSION['is_admin'] = false;
        
        // Check if profile is complete
        if (empty($user['mobile']) || empty($user['verification_doc'])) {
            header("Location: user-profile.php?complete_profile=1");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    }
}

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
        $stmt = $conn->prepare("SELECT id, fullname, email, password, active FROM users WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Invalid email or password.");
        }

        $user = $result->fetch_assoc();
        
        // Check account status FIRST
        if ($user['active'] === 'blocked') {
            $_SESSION['error'] = "Your account has been blocked. Please contact the administrator.";
            header("Location: auth-page.php");
            exit();
        }

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