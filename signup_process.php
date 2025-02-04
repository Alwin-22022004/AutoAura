<?php
session_start();
require_once 'db_connect.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

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

        // Handle file upload
        $verification_doc_path = '';
        if (isset($_FILES['verification_doc']) && $_FILES['verification_doc']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['verification_doc']['name'];
            $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $filesize = $_FILES['verification_doc']['size'];

            if (!in_array($filetype, $allowed)) {
                $errors[] = "Only JPG and PNG files are allowed.";
            } elseif ($filesize > 5242880) { // 5MB
                $errors[] = "File size must be less than 5MB.";
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/verification/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Generate unique filename
                $new_filename = time() . '_' . uniqid() . '.' . $filetype;
                $upload_path = $upload_dir . $new_filename;

                if (!move_uploaded_file($_FILES['verification_doc']['tmp_name'], $upload_path)) {
                    throw new Exception("Failed to upload verification document.");
                }
                $verification_doc_path = $upload_path;
            }
        } else {
            $errors[] = "Verification document is required.";
        }

        if (!empty($errors)) {
            // If there are errors, clean up any uploaded file
            if (!empty($verification_doc_path) && file_exists($verification_doc_path)) {
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
        
        // Send OTP via email
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->SMTPDebug = 0;                      // Enable verbose debug output (set to 2 for debugging)
            $mail->isSMTP();                          
            $mail->Host       = 'smtp.gmail.com';      
            $mail->SMTPAuth   = true;                  
            $mail->Username   = 'autoauracars@gmail.com';
            $mail->Password   = 'vqqjrpmrjsnlgmjf';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  
            $mail->Port       = 465;                   
            
            // Disable SSL verification (only if needed for testing)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Recipients
            $mail->setFrom('autoauracars@gmail.com', 'Car Rental');
            $mail->addAddress($email);     // Add recipient
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Email Verification OTP';
            $mail->Body    = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <div style='background-color: #f6f6f6; padding: 20px;'>
                        <h2 style='color: #333;'>Email Verification</h2>
                        <p>Your OTP for email verification is:</p>
                        <h1 style='color: #007bff; font-size: 32px; letter-spacing: 5px;'>{$otp}</h1>
                        <p>This OTP will expire in 10 minutes.</p>
                        <p style='color: #666; font-size: 12px;'>If you didn't request this OTP, please ignore this email.</p>
                    </div>
                </body>
                </html>";
            $mail->AltBody = "Your OTP for email verification is: {$otp}. This OTP will expire in 10 minutes.";
            
            if(!$mail->send()) {
                throw new Exception("Failed to send OTP email: " . $mail->ErrorInfo);
            }
            
            header("Location: otp.php");
            exit();
            
        } catch (Exception $e) {
            // Clean up uploaded file if email sending fails
            if (!empty($verification_doc_path) && file_exists($verification_doc_path)) {
                unlink($verification_doc_path);
            }
            // Clear temporary session data
            unset($_SESSION['temp_user_data']);
            
            $_SESSION['errors'] = ["Failed to send OTP. Please try again. Error: " . $e->getMessage()];
            header("Location: auth-page.php");
            exit();
        }
        
    } catch (Exception $e) {
        // Clean up uploaded file if any other error occurs
        if (!empty($verification_doc_path) && file_exists($verification_doc_path)) {
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