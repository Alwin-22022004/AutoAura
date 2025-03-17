<?php
session_start();
require_once 'db_connect.php';
require_once 'mail_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Enable error reporting for debugging
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        // Validate and sanitize input
        $fullname = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $mobile = trim($_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Input validation
        $errors = [];
        
        if (empty($fullname) || strlen($fullname) > 100) {
            $errors[] = "Full name is required and must be less than 100 characters.";
        }
        
        if (!$email) {
            $errors[] = "Please enter a valid email address.";
        }

        // Validate mobile number (Indian format)
        if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $errors[] = "Please enter a valid 10-digit mobile number starting with 6-9.";
        }
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match!";
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists";
        }

        // Handle PDF file upload
        $verification_doc_path = null;
        if (isset($_FILES['verification_doc']) && $_FILES['verification_doc']['error'] !== UPLOAD_ERR_NO_FILE) {
            $filename = $_FILES['verification_doc']['name'];
            $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $filesize = $_FILES['verification_doc']['size'];

            if ($filetype !== 'pdf') {
                $errors[] = "Only PDF files are allowed.";
            } elseif ($filesize > 5242880) { // 5MB
                $errors[] = "File size must be less than 5MB.";
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/verification_docs/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Generate unique filename using timestamp and random string
                $unique_filename = time() . '_' . bin2hex(random_bytes(8)) . '.pdf';
                $verification_doc_path = $upload_dir . $unique_filename;
                
                // Move uploaded file
                if (!move_uploaded_file($_FILES['verification_doc']['tmp_name'], $verification_doc_path)) {
                    throw new Exception("Failed to save PDF file.");
                }
            }
        } else {
            $errors[] = "Verification document is required.";
        }

        if (!empty($errors)) {
            // Delete uploaded file if there are errors
            if ($verification_doc_path && file_exists($verification_doc_path)) {
                unlink($verification_doc_path);
            }
            $_SESSION['errors'] = $errors;
            header("Location: auth-page.php");
            exit();
        }

        // Generate OTP
        $otp = sprintf("%06d", mt_rand(0, 999999));
        
        // Store user data in session
        $_SESSION['temp_user_data'] = [
            'name' => $fullname,
            'email' => $email,
            'mobile' => $mobile,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'verification_doc' => $verification_doc_path,
            'otp' => $otp,
            'otp_time' => time()
        ];
        
        // Generate email content and send OTP
        $emailContent = generateOTPEmailContent($otp, 'verification');
        
        try {
            if (sendMail($email, 'Email Verification OTP', $emailContent['html'], $emailContent['text'])) {
                header("Location: otp.php");
                exit();
            }
        } catch (Exception $e) {
            // Delete uploaded file if email fails
            if ($verification_doc_path && file_exists($verification_doc_path)) {
                unlink($verification_doc_path);
            }
            
            // Clear temporary session data
            unset($_SESSION['temp_user_data']);
            
            $_SESSION['errors'] = ["Failed to send OTP. Please try again. Error: " . $e->getMessage()];
            header("Location: auth-page.php");
            exit();
        }
        
    } catch (Exception $e) {
        // Delete uploaded file if there's an error
        if (isset($verification_doc_path) && file_exists($verification_doc_path)) {
            unlink($verification_doc_path);
        }
        
        // Clear temporary session data
        unset($_SESSION['temp_user_data']);
        
        $_SESSION['errors'] = ["An unexpected error occurred: " . $e->getMessage()];
        header("Location: auth-page.php");
        exit();
    }
} else {
    header("Location: auth-page.php");
    exit();
}
?>