<?php
session_start();
require_once 'db_connect.php';

// Redirect if no temporary user data exists
if (!isset($_SESSION['temp_user_data']) || !isset($_SESSION['temp_user_data']['otp'])) {
    header("Location: auth-page.php");
    exit();
}

// Display error message if exists
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Car Rental - Verify OTP</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Syncopate:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),
                        url('assets/otp back.jpg') center/cover;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #333;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 450px;
            backdrop-filter: blur(10px);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h1 {
            color: #1a1a1a;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .logo p {
            color: #666;
            font-size: 0.9rem;
        }

        .otp-container {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            gap: 10px;
        }

        .otp-input {
            width: 50px;
            height: 50px;
            border: 2px solid #ddd;
            border-radius: 10px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            background: white;
            transition: all 0.3s ease;
        }

        .otp-input:focus {
            border-color: #c4a47c;
            outline: none;
            box-shadow: 0 0 10px rgba(196, 164, 124, 0.3);
        }

        .verify-btn {
            width: 100%;
            padding: 1rem;
            background: #c4a47c;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .verify-btn:hover {
            background: #b3936b;
            transform: translateY(-2px);
        }

        .verify-btn:disabled {
            background: #ddd;
            cursor: not-allowed;
            transform: none;
        }

        .timer {
            text-align: center;
            margin-top: 1rem;
            color: #666;
        }

        .resend {
            text-align: center;
            margin-top: 1rem;
        }

        .resend a {
            color: #c4a47c;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .resend a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #ff4444;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
            display: none;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .shake {
            animation: shake 0.3s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>AUTOAURA CARS</h1>
            <p>Verify your email to continue</p>
        </div>
        
        <form action="verify_otp.php" method="POST" id="otp-form">
            <div class="otp-container">
                <input type="text" class="otp-input" name="otp1" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" name="otp2" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" name="otp3" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" name="otp4" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" name="otp5" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" name="otp6" maxlength="1" pattern="[0-9]" required>
            </div>

            <button type="submit" class="verify-btn" disabled>Verify OTP</button>
        </form>
        
        <div class="timer">
            Code expires in: <span id="countdown">02:00</span>
        </div>

        <div class="resend">
            <a href="#" id="resend-link" style="display: none;">Resend Code</a>
        </div>

        <div class="error-message" <?php if($error_message) echo 'style="display: block;"'; ?>>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const inputs = document.querySelectorAll('.otp-input');
            const verifyBtn = document.querySelector('.verify-btn');
            const errorMessage = document.querySelector('.error-message');
            const container = document.querySelector('.container');
            const resendLink = document.getElementById('resend-link');
            const form = document.getElementById('otp-form');
            let timeLeft = 120; // 2 minutes in seconds

            // Set up OTP input handling
            inputs.forEach((input, index) => {
                input.addEventListener('input', (e) => {
                    const value = e.target.value;
                    
                    // Only allow numbers
                    if (!/^\d*$/.test(value)) {
                        input.value = '';
                        return;
                    }
                    
                    if (value.length === 1) {
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    }

                    // Enable/disable verify button
                    const isComplete = Array.from(inputs).every(input => input.value.length === 1);
                    verifyBtn.disabled = !isComplete;
                });

                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });

                // Prevent paste except for numbers
                input.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text');
                    if (/^\d*$/.test(pastedData)) {
                        input.value = pastedData.charAt(0);
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    }
                });
            });

            // Timer functionality
            const updateTimer = () => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                document.getElementById('countdown').textContent = 
                    `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                
                if (timeLeft === 0) {
                    resendLink.style.display = 'inline';
                    verifyBtn.disabled = true;
                    return;
                }
                
                timeLeft--;
                setTimeout(updateTimer, 1000);
            };

            updateTimer();

            // Resend link handler
            resendLink.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Make an AJAX request to resend OTP
                fetch('resend_otp.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            timeLeft = 120;
                            resendLink.style.display = 'none';
                            updateTimer();
                            
                            // Clear inputs
                            inputs.forEach(input => {
                                input.value = '';
                            });
                            inputs[0].focus();
                            verifyBtn.disabled = true;
                            
                            alert('New OTP has been sent to your email!');
                        } else {
                            alert(data.message || 'Failed to resend OTP. Please try again.');
                        }
                    })
                    .catch(error => {
                        alert('An error occurred. Please try again.');
                    });
            });
        });
    </script>
</body>
</html>
