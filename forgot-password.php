<?php
session_start();

// Display any error or success messages from process_forgot_password.php
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';

// Clear the messages after displaying
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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

        .forgot-password-container {
            background: white;
            width: 350px;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .forgot-password-container h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .forgot-password-container p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .email-input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .email-input:focus {
            outline: none;
            border-color: #f5b754;
        }

        .send-btn {
            width: 100%;
            padding: 12px;
            background: #f5b754;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s;
        }

        .send-btn:hover {
            background: #e4a643;
            transform: translateY(-2px);
        }

        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            color: #dc3545;
            margin-top: 10px;
            font-size: 14px;
            display: none;
        }

        .success-message {
            color: #28a745;
            margin-top: 10px;
            font-size: 14px;
            display: none;
        }

        .loading {
            display: none;
            margin-top: 10px;
            color: #666;
        }

        .back-to-login {
            margin-top: 20px;
            font-size: 14px;
        }

        .back-to-login a {
            color: #f5b754;
            text-decoration: none;
        }

        .back-to-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <h2>Forgot Password</h2>
        <p>Enter your email address and we'll send you a code to reset your password.</p>
        
        <form id="forgotPasswordForm" action="process_forgot_password.php" method="POST">
            <input 
                type="email" 
                class="email-input" 
                placeholder="Enter your email" 
                required 
                id="emailInput"
                name="email"
            >
            <button type="submit" class="send-btn" id="submitBtn">Send Reset Code</button>
            
            <div id="loadingMessage" class="loading">
                Sending reset code...
            </div>
            <div id="errorMessage" class="error-message" <?php if($error_message) echo 'style="display: block;"'; ?>>
                <?php echo htmlspecialchars($error_message ?: 'Please enter a valid email address'); ?>
            </div>
            <div id="successMessage" class="success-message" <?php if($success_message) echo 'style="display: block;"'; ?>>
                <?php echo htmlspecialchars($success_message ?: 'Reset code sent successfully!'); ?>
            </div>
        </form>
        
        <div class="back-to-login">
            <a href="auth-page.php">Back to Login</a>
        </div>
    </div>

    <script>
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            const emailInput = document.getElementById('emailInput');
            const errorMessage = document.getElementById('errorMessage');
            const loadingMessage = document.getElementById('loadingMessage');
            const submitBtn = document.getElementById('submitBtn');

            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(emailInput.value)) {
                e.preventDefault(); // Prevent form submission
                errorMessage.textContent = 'Please enter a valid email address';
                errorMessage.style.display = 'block';
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            loadingMessage.style.display = 'block';
            errorMessage.style.display = 'none';
        });
    </script>
</body>
</html>
