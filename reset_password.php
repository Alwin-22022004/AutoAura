<?php
session_start();

// Verify that user has completed OTP verification
if (!isset($_SESSION['password_reset_verified']) || !isset($_SESSION['reset_email'])) {
    $_SESSION['error_message'] = 'Please complete email verification first.';
    header("Location: forgot-password.php");
    exit();
}

// Get any error messages
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';

// Clear messages after displaying
unset($_SESSION['error_message'], $_SESSION['success_message']);
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
            background: url('assets/otp-back2.jpg') no-repeat center center fixed;
            background-size: cover;
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
                <input type="password" id="newPassword" name="newPassword" required minlength="8">
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
                <input type="password" id="confirmPassword" name="confirmPassword" required>
                <div class="error-message" id="passwordError">Passwords do not match</div>
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
            const form = document.getElementById('resetPasswordForm');

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

            function validatePasswords() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const strengthCount = evaluatePasswordStrength(newPassword);
                const isValid = newPassword === confirmPassword && newPassword.length >= 8 && strengthCount >= 4;
                submitBtn.disabled = !isValid;
                passwordError.style.display = (newPassword !== confirmPassword && confirmPassword) ? 'block' : 'none';
            }

            newPasswordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                submitBtn.disabled = true;

                try {
                    const response = await fetch('update_password.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            password: newPasswordInput.value,
                            confirmPassword: confirmPasswordInput.value
                        })
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        alert(data.message);
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                    } else {
                        alert(data.message || 'Failed to reset password');
                        submitBtn.disabled = false;
                    }
                } catch (error) {
                    alert('An error occurred while resetting the password');
                    submitBtn.disabled = false;
                }
            });
        });
    </script>
</body>
</html>
