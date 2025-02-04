<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Function to get concatenated OTP from POST data
    function getEnteredOTP() {
        $entered_otp = '';
        for ($i = 1; $i <= 6; $i++) {
            if (isset($_POST["otp$i"]) && is_numeric($_POST["otp$i"])) {
                $entered_otp .= trim($_POST["otp$i"]);
            }
        }
        return $entered_otp;
    }

    // Registration OTP verification
    if (isset($_SESSION['temp_user_data']) && isset($_SESSION['temp_user_data']['otp'])) {
        $entered_otp = getEnteredOTP();
        $temp_user_data = $_SESSION['temp_user_data'];

        // Check if OTP is empty
        if (empty($entered_otp)) {
            $_SESSION['error'] = "Please enter the OTP.";
            header("Location: otp.php");
            exit();
        }

        try {
            // Verify OTP using strict comparison
            if (strcmp($entered_otp, $temp_user_data['otp']) === 0) {
                // Insert user data into the database
                $stmt = $conn->prepare("INSERT INTO users (fullname, email, mobile, password, verification_doc) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", 
                    $temp_user_data['name'],
                    $temp_user_data['email'],
                    $temp_user_data['mobile'],
                    $temp_user_data['password'],
                    $temp_user_data['verification_doc']
                );

                if (!$stmt->execute()) {
                    throw new Exception("Failed to create account: " . $stmt->error);
                }

                // Clear temporary user data
                unset($_SESSION['temp_user_data']);

                // Show success message as alert and redirect
                echo "<script>
                    alert('Registration successful! Please login to continue.');
                    window.location.href = 'auth-page.php';
                </script>";
                exit();
            } else {
                $_SESSION['error'] = "Invalid OTP. Please try again.";
                header("Location: otp.php");
                exit();
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: auth-page.php");
            exit();
        }
    } else {
        header("Location: auth-page.php");
        exit();
    }
} else {
    header("Location: auth-page.php");
    exit();
}
?>