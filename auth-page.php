<?php
session_start();
if (isset($_SESSION['errors'])) {
    foreach ($_SESSION['errors'] as $error) {
        echo '<div class="error">' . htmlspecialchars($error) . '</div>';
    }
    unset($_SESSION['errors']);
}
if (isset($_SESSION['success'])) {
    echo '<div class="success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Auth Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Syncopate:wght@400;700&display=swap" rel="stylesheet">
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="styles2.css" />
    <style>
      .error {
        color: red;
      }
      .error-message {
        color: red;
        font-size: 0.8em;
        margin-top: 5px;
        display: block;
      }
      .success-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: #28a745;
        color: white;
        padding: 15px 25px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        display: none;
        z-index: 1000;
        animation: slideIn 0.5s ease-out;
      }
      @keyframes slideIn {
        from {
          transform: translateX(100%);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }
      .file-hint {
        color: #666;
        font-size: 0.75em;
        margin-top: 4px;
        display: block;
      }

      input[type="file"], input[type="tel"] {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        width: 100%;
        margin-top: 5px;
        font-size: 14px;
      }

      input[type="tel"]:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
      }

      input[type="tel"].invalid {
        border-color: #dc3545;
      }

      input[type="tel"].valid {
        border-color: #28a745;
      }

      input[type="file"]::file-selector-button {
        background-color: #007bff;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-right: 10px;
      }

      input[type="file"]::file-selector-button:hover {
        background-color: #0056b3;
      }

      .mobile-hint {
        color: #666;
        font-size: 0.75em;
        margin-top: 4px;
        display: block;
      }
      .google-btn {
        width: 100%;
        padding: 10px;
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        cursor: pointer;
        margin-bottom: 15px;
        transition: background-color 0.3s;
      }
      .google-btn:hover {
        background-color: #f5f5f5;
      }
      .google-btn img {
        width: 18px;
        height: 18px;
      }
      .or-divider {
        text-align: center;
        margin: 15px 0;
        position: relative;
      }
      .or-divider::before,
      .or-divider::after {
        content: "";
        position: absolute;
        top: 50%;
        width: 45%;
        height: 1px;
        background-color: #ddd;
      }
      .or-divider::before {
        left: 0;
      }
      .or-divider::after {
        right: 0;
      }
    </style>
  </head>
  <body>
    <?php if(isset($_SESSION['success'])): ?>
    <div class="success-notification" id="successNotification">
      <i class="fas fa-check-circle"></i>
      <?php echo htmlspecialchars($_SESSION['success']); ?>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const notification = document.getElementById('successNotification');
        notification.style.display = 'block';
        setTimeout(function() {
          notification.style.opacity = '0';
          setTimeout(function() {
            notification.style.display = 'none';
          }, 500);
        }, 3000);
      });
    </script>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if(isset($_SESSION['email_exists'])): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        alert("<?php echo htmlspecialchars($_SESSION['email_exists']); ?>");
      });
    </script>
    <?php unset($_SESSION['email_exists']); endif; ?>

    <header>
      <div class="nav__logo">
        <img
          src="assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png"
          alt="Car Rental Logo"
        />
      </div>
    </header>
    <div class="container">
      <!-- Left Section -->
      <div class="image-section">
        <h1>Welcome Back</h1>
        <p>
          Sign in to continue your journey with us or create a new account to
          get started.
        </p>
      </div>

      <!-- Right Section -->
      <div class="form-section">
        <div class="form-container">
          <div class="form-header">
            <h2>Authentication</h2>
          </div>

          <!-- Toggle Buttons -->
          <div class="toggle-buttons">
            <button class="toggle-btn active" data-form="login">Login</button>
            <button class="toggle-btn" data-form="signup">Sign Up</button>
          </div>

          <!-- Login Form -->
          <form class="form active" id="login-form" action="login_process.php" method="POST">
            <div class="form-group">
              <label for="login-email">Email</label>
              <input
                type="email"
                name="email"
                placeholder="Enter your email"
                required
              />
              <?php if(isset($_SESSION['email_error'])): ?>
                  <div class="error-message"><?php echo $_SESSION['email_error']; unset($_SESSION['email_error']); ?></div>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label for="login-password">Password</label>
              <input
                type="password"
                name="password"
                placeholder="Enter your password"
                required
              />
              <?php if(isset($_SESSION['error'])): ?>
                  <div class="error-message"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
              <?php endif; ?>
              <?php if(isset($_SESSION['password_error'])): ?>
                  <div class="error-message"><?php echo $_SESSION['password_error']; unset($_SESSION['password_error']); ?></div>
              <?php endif; ?>
            </div>

            <div class="or-divider">
              <span>or</span>
            </div>

            <div class="google-btn" onclick="signInWithGoogle()">
              <img src="assets/google.jpg"  " alt="Google logo">
              <span>Continue with Google</span>
            </div>
            
            <div class="form-extras">
              <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
            </div>

            <button type="submit" class="submit-btn">Login</button>
          </form>

          <!-- Signup Form -->
          <form class="form" id="signup-form" action="signup_process.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
              <label for="signup-name">Fullname</label>
              <input
                type="text"
                name="name"
                placeholder="Enter your full name" required
              />
            </div>

            <div class="form-group">
              <label for="signup-email">Email</label>
              <input
                type="text"
                name="email"
                placeholder="Enter your email" required
              />
            </div>

            <div class="form-group">
              <label for="mobile">Mobile Number</label>
              <input
                type="tel"
                name="mobile"
                id="mobile"
                placeholder="Enter your 10-digit mobile number"
                pattern="[6-9][0-9]{9}"
                maxlength="10"
                required
                oninput="validateMobile(this)"
              />
              <span class="mobile-hint">Enter a valid 10-digit mobile number starting with 6-9</span>
              <span id="mobile-error" class="error-message"></span>
              <?php if(isset($_SESSION['mobile_error'])): ?>
                  <div class="error-message"><?php echo htmlspecialchars($_SESSION['mobile_error']); unset($_SESSION['mobile_error']); ?></div>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label for="signup-password">Password</label>
              <input
                type="password"
                name="password"
                placeholder="Create a password" required
              />
            </div>

            <div class="form-group">
              <label for="confirm-password">Confirm Password</label>
              <input
                type="password"
                name="confirm_password"
                placeholder="Confirm your password" required
              />
            </div>

            <div class="form-group">
              <label for="verification_doc">Verification Document</label>
              <input
                type="file"
                name="verification_doc"
                id="verification_doc"
                accept="application/pdf"
                required
              />
              <span class="file-hint">Upload a valid ID document (PDF format only, max 5MB)</span>
              <?php if(isset($_SESSION['file_error'])): ?>
                  <div class="error-message"><?php echo $_SESSION['file_error']; unset($_SESSION['file_error']); ?></div>
              <?php endif; ?>
            </div>

            <button type="submit" class="submit-btn">Sign Up</button>
          </form>

        </div>
      </div>
    </div>

    <script src="login.js"></script>
    <script>
      function signInWithGoogle() {
        window.location.href = 'google_login.php';
      }
    </script>
  </body>
</html>
