<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - OTP Verification</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            background-image: url('assets/otp-back2.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .container {
            background: #fff;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 400px;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .email-display {
            text-align: center;
            margin-bottom: 2rem;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
            color: #2c3e50;
            font-weight: 500;
        }

        .otp-container {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 2rem;
        }

        .otp-input {
            width: 45px;
            height: 45px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
            transition: all 0.3s ease;
        }

        .otp-input:focus {
            border-color: #f5b754;
            outline: none;
            box-shadow: 0 0 0 2px rgba(245, 183, 84, 0.2);
        }

        .otp-input.error {
            border-color: #ff4444;
            animation: shake 0.3s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .verify-btn {
            width: 100%;
            padding: 1rem;
            background: #f5b754;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .verify-btn:hover {
            background: #f4a833;
            transform: translateY(-2px);
        }

        .verify-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .timer {
            text-align: center;
            margin-bottom: 1rem;
            color: #666;
            font-size: 0.9rem;
        }

        .resend {
            text-align: center;
        }

        .resend-link {
            color: #f5b754;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            display: none;
        }

        .resend-link:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #ff4444;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verify Your Email</h1>
            <p>We've sent a verification code to your email address</p>
        </div>

        <div class="email-display" id="email-display">
            <!-- Email will be inserted here via JavaScript -->
        </div>

        <form action="verify_otp2.php" method="POST" id="otp-form">
            <div class="otp-container">
                <input type="text" class="otp-input" name="otp1" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" name="otp2" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" name="otp3" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" name="otp4" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" name="otp5" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" name="otp6" maxlength="1" pattern="[0-9]" required>
            </div>

            <button type="submit" class="verify-btn" disabled>Verify Code</button>
        </form>

        <div class="timer">
            Code expires in: <span id="countdown">02:00</span>
        </div>

        <div class="resend">
            <a href="process_forgot_password.php" class="resend-link" id="resend-link">Resend Code</a>
        </div>

        <div class="error-message" id="error-message" <?php if(isset($_SESSION['error_message'])) echo 'style="display: block;"'; ?>>
            <?php 
                if(isset($_SESSION['error_message'])) {
                    echo htmlspecialchars($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                } else {
                    echo 'Invalid verification code. Please try again.';
                }
            ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('otp-form');
            const inputs = document.querySelectorAll('.otp-input');
            const verifyBtn = document.querySelector('.verify-btn');
            const resendLink = document.getElementById('resend-link');
            const errorMessage = document.getElementById('error-message');
            let timeLeft = 120; // 2 minutes in seconds

            // Get email from URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const userEmail = urlParams.get('email') || 'your email';
            document.getElementById('email-display').textContent = userEmail;

            // Handle input behavior
            inputs.forEach((input, index) => {
                // Only allow numbers
                input.addEventListener('keypress', (e) => {
                    if (!/[0-9]/.test(e.key)) {
                        e.preventDefault();
                    }
                });

                input.addEventListener('input', (e) => {
                    // Remove any non-numeric characters
                    e.target.value = e.target.value.replace(/[^0-9]/g, '');
                    
                    if (e.target.value) {
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                        checkInputs();
                    }
                });

                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        inputs[index - 1].focus();
                        checkInputs();
                    }
                });

                // Handle paste event
                input.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').split('');
                    
                    inputs.forEach((input, i) => {
                        if (pastedData[i]) {
                            input.value = pastedData[i];
                        }
                    });
                    
                    checkInputs();
                });
            });

            function checkInputs() {
                const allFilled = Array.from(inputs).every(input => input.value.length === 1);
                verifyBtn.disabled = !allFilled;
            }

            // Timer functionality
            function updateTimer() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                document.getElementById('countdown').textContent = 
                    `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                
                if (timeLeft > 0) {
                    timeLeft--;
                    setTimeout(updateTimer, 1000);
                } else {
                    resendLink.style.display = 'inline';
                }
            }

            updateTimer();
        });
    </script>
</body>
</html>
