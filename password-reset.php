<?php
session_start();
require_once 'db_connect.php';

// Verify if user has a valid reset session
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_otp'])) {
    header("Location: forgot-password.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    // Validate input
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $email = $_SESSION['reset_email'];

    if (empty($newPassword) || empty($confirmPassword)) {
        $response['message'] = 'Both password fields are required.';
        echo json_encode($response);
        exit();
    }

    // Validate password length and complexity
    if (strlen($newPassword) < 8) {
        $response['message'] = 'Password must be at least 8 characters long.';
        echo json_encode($response);
        exit();
    }

    if (!preg_match('/[A-Z]/', $newPassword) || 
        !preg_match('/[a-z]/', $newPassword) || 
        !preg_match('/[0-9]/', $newPassword) || 
        !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
        $response['message'] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
        echo json_encode($response);
        exit();
    }

    if ($newPassword !== $confirmPassword) {
        $response['message'] = 'Passwords do not match.';
        echo json_encode($response);
        exit();
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        // Hash the password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update the password in the database
        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        
        if ($stmt->execute()) {
            // Commit the transaction
            $conn->commit();
            
            // Clear all session variables related to password reset
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_otp']);
            unset($_SESSION['otp_timestamp']);
            
            $response['success'] = true;
            $response['message'] = 'Password updated successfully.';
            $response['redirect'] = 'auth-page.php';
        } else {
            // Rollback the transaction
            $conn->rollback();
            $response['message'] = 'Failed to update password. Please try again.';
        }
    } catch (Exception $e) {
        // Rollback the transaction
        if ($conn->connect_errno === 0) {
            $conn->rollback();
        }
        $response['message'] = 'An error occurred. Please try again later.';
        error_log("Password reset error for email {$email}: " . $e->getMessage());
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
    
    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .reset-container {
            background: white;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .reset-title {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4a90e2;
        }

        .password-strength {
            display: flex;
            margin-top: 5px;
            height: 5px;
        }

        .strength-bar {
            flex: 1;
            height: 100%;
            background: #ddd;
            margin-right: 3px;
            border-radius: 3px;
            transition: background 0.3s;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #4a90e2;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .error-message {
            color: red;
            font-size: 0.8em;
            margin-top: 5px;
            display: none;
        }

        .password-requirements {
            font-size: 0.75em;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2 class="reset-title">Reset Password</h2>
        <form id="resetPasswordForm">
            <div class="form-group">
                <label for="newPassword">New Password</label>
                <input 
                    type="password" 
                    id="newPassword" 
                    name="newPassword" 
                    required
                    minlength="8"
                >
                <div class="password-strength">
                    <div class="strength-bar"></div>
                    <div class="strength-bar"></div>
                    <div class="strength-bar"></div>
                    <div class="strength-bar"></div>
                </div>
                <div class="password-requirements">
                    Password must be at least 8 characters long and include:
                    <ul>
                        <li>Uppercase letter</li>
                        <li>Lowercase letter</li>
                        <li>Number</li>
                        <li>Special character</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="confirmPassword">Confirm Password</label>
                <input 
                    type="password" 
                    id="confirmPassword" 
                    name="confirmPassword" 
                    required
                >
                <div class="error-message" id="passwordError">
                    Passwords do not match
                </div>
            </div>

            <button type="submit" class="submit-btn" disabled>Reset Password</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const newPasswordInput = document.getElementById('newPassword');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const submitBtn = document.querySelector('.submit-btn');
            const passwordError = document.getElementById('passwordError');
            const strengthBars = document.querySelectorAll('.strength-bar');

            // Password strength evaluation
            function evaluatePasswordStrength(password) {
                const strength = {
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    specialChar: /[!@#$%^&*(),.?":{}|<>]/.test(password),
                    length: password.length >= 8
                };

                const strengthCount = Object.values(strength).filter(Boolean).length;
                strengthBars.forEach((bar, index) => {
                    bar.style.background = index < strengthCount ? getStrengthColor(strengthCount) : '#ddd';
                });

                return strengthCount;
            }

            function getStrengthColor(strength) {
                switch(strength) {
                    case 1: return '#ff4d4d';
                    case 2: return '#ff9933';
                    case 3: return '#33cc33';
                    case 4: return '#4a90e2';
                    case 5: return '#4CAF50';
                    default: return '#ddd';
                }
            }

            // Validate password inputs
            function validatePasswords() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const strengthCount = evaluatePasswordStrength(newPassword);

                const isValid = newPassword === confirmPassword && 
                                newPassword.length >= 8 && 
                                strengthCount >= 4;

                submitBtn.disabled = !isValid;
                passwordError.style.display = (newPassword !== confirmPassword && confirmPassword) ? 'block' : 'none';
            }

            newPasswordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);

            // Form submission
            document.getElementById('resetPasswordForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                submitBtn.disabled = true;
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                try {
                    const response = await fetch('update_password.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            password: newPassword,
                            confirmPassword: confirmPassword
                        })
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        alert('Password Reset Successful!');
                        window.location.href = data.redirect || 'auth-page.php';
                    } else {
                        alert(data.message || 'Failed to reset password');
                        submitBtn.disabled = false;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while resetting the password');
                    submitBtn.disabled = false;
                }
            });
        });
    </script>
</body>
</html>
