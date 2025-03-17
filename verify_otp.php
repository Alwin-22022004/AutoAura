<?php
session_start();
require_once 'db_connect.php';

// Get JSON data for password reset
$json_data = json_decode(file_get_contents('php://input'), true);

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Password Reset OTP verification
    if (isset($json_data['email']) && isset($json_data['otp'])) {
        $email = $json_data['email'];
        $entered_otp = $json_data['otp'];

        // Verify if the session variables exist
        if (!isset($_SESSION['reset_otp']) || !isset($_SESSION['reset_email']) || !isset($_SESSION['otp_timestamp'])) {
            $response['message'] = 'OTP session expired. Please request a new OTP.';
            echo json_encode($response);
            exit;
        }

        // Check if OTP has expired (10 minutes)
        if (time() - $_SESSION['otp_timestamp'] > 600) {
            unset($_SESSION['reset_otp']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['otp_timestamp']);
            $response['message'] = 'OTP has expired. Please request a new OTP.';
            echo json_encode($response);
            exit;
        }

        // Verify email matches
        if ($email !== $_SESSION['reset_email']) {
            $response['message'] = 'Invalid email address.';
            echo json_encode($response);
            exit;
        }

        // Verify OTP
        if ($entered_otp === $_SESSION['reset_otp']) {
            $response['success'] = true;
            $response['message'] = 'OTP verified successfully.';
            // Keep the session variables for password reset page
            echo json_encode($response);
            exit;
        } else {
            $response['message'] = 'Invalid OTP. Please try again.';
            echo json_encode($response);
            exit;
        }
    }
    // Registration OTP verification
    else if (isset($_SESSION['temp_user_data']) && isset($_SESSION['temp_user_data']['otp'])) {
        function getEnteredOTP() {
            $entered_otp = '';
            for ($i = 1; $i <= 6; $i++) {
                if (isset($_POST["otp$i"]) && is_numeric($_POST["otp$i"])) {
                    $entered_otp .= trim($_POST["otp$i"]);
                }
            }
            return $entered_otp;
        }

        $entered_otp = getEnteredOTP();
        $temp_user_data = $_SESSION['temp_user_data'];

        if (empty($entered_otp)) {
            $_SESSION['error'] = "Please enter the OTP.";
            header("Location: otp.php");
            exit();
        }

        try {
            if (strcmp($entered_otp, $temp_user_data['otp']) === 0) {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/verification_docs/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Insert user data into database
                $stmt = $conn->prepare("INSERT INTO users (fullname, email, mobile, password, verification_doc) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", 
                    $temp_user_data['name'],
                    $temp_user_data['email'],
                    $temp_user_data['mobile'],
                    $temp_user_data['password'],
                    $temp_user_data['verification_doc']
                );

                if (!$stmt->execute()) {
                    // Delete the uploaded file if database insert fails
                    if (file_exists($temp_user_data['verification_doc'])) {
                        unlink($temp_user_data['verification_doc']);
                    }
                    throw new Exception("Failed to create account: " . $stmt->error);
                }

                unset($_SESSION['temp_user_data']);

                echo "<script>
                    alert('Registration successful! Please login to continue.');
                    window.location.href = 'auth-page.php';
                </script>";
                exit();
            } else {
                // Delete the uploaded file if OTP verification fails
                if (isset($temp_user_data['verification_doc']) && file_exists($temp_user_data['verification_doc'])) {
                    unlink($temp_user_data['verification_doc']);
                }
                $_SESSION['error'] = "Invalid OTP. Please try again.";
                header("Location: otp.php");
                exit();
            }
        } catch (Exception $e) {
            // Delete the uploaded file if any error occurs
            if (isset($temp_user_data['verification_doc']) && file_exists($temp_user_data['verification_doc'])) {
                unlink($temp_user_data['verification_doc']);
            }
            $_SESSION['error'] = $e->getMessage();
            header("Location: auth-page.php");
            exit();
        }
    } else {
        $response['message'] = 'Invalid request.';
        echo json_encode($response);
        exit;
    }
} else {
    header("Location: auth-page.php");
    exit();
}
?>