<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-page.php");
    exit();
}

require_once 'db_connect.php';

// Get user details from database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT fullname, email, mobile, address, profile_picture, auth_type, verification_doc FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Debug user data
error_log("User Data - ID: $user_id, Auth Type: " . $user['auth_type'] . ", Profile Picture: " . ($user['profile_picture'] ?? 'not set'));
error_log("Session Data - Google Login: " . (isset($_SESSION['google_login']) ? 'true' : 'false') . ", Profile Picture: " . (isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'not set'));

// Set profile picture with proper priority
$profile_picture = 'default_profile.png'; // Set a default value

if (isset($user['profile_picture']) && !empty($user['profile_picture'])) {
    $profile_picture = $user['profile_picture'];
} elseif (isset($_SESSION['google_login']) && isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
    $profile_picture = $_SESSION['profile_picture'];
}

// Debug profile picture source
error_log("Profile Picture Source: " . $profile_picture);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $mobile = trim($_POST['mobile']);
    $address = trim($_POST['address']);
    
    $errors = [];
    
    // Validate mobile number
    if (empty($mobile)) {
        $errors[] = "Mobile number is required";
    } elseif (!preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
        $errors[] = "Invalid mobile number format. Must be 10 digits starting with 6-9.";
    }
    
    // Handle verification document upload
    if (isset($_FILES['verification_doc']) && $_FILES['verification_doc']['error'] === 0) {
        $allowed_types = ['application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['verification_doc']['type'], $allowed_types)) {
            $errors[] = "Only PDF files are allowed for verification document";
        } elseif ($_FILES['verification_doc']['size'] > $max_size) {
            $errors[] = "File size must be less than 5MB";
        } else {
            $upload_dir = 'uploads/verification_docs/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = $user_id . '_' . time() . '_' . basename($_FILES['verification_doc']['name']);
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['verification_doc']['tmp_name'], $target_path)) {
                // Update verification_doc in database
                $update_doc = $conn->prepare("UPDATE users SET verification_doc = ? WHERE id = ?");
                $update_doc->bind_param("si", $target_path, $user_id);
                $update_doc->execute();
            } else {
                $errors[] = "Error uploading verification document";
            }
        }
    } elseif (empty($user['verification_doc']) && isset($_GET['complete_profile'])) {
        $errors[] = "Verification document is required";
    }
    
    if (empty($errors)) {
        // Update user details
        $update_stmt = $conn->prepare("UPDATE users SET fullname = ?, mobile = ?, address = ? WHERE id = ?");
        $update_stmt->bind_param("sssi", $fullname, $mobile, $address, $user_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Profile updated successfully!";
            if ($_SESSION['user_name'] !== $fullname) {
                $_SESSION['user_name'] = $fullname;
            }
            
            // Check if all required fields are filled
            if (!empty($mobile) && (!empty($user['verification_doc']) || 
                (isset($_FILES['verification_doc']) && $_FILES['verification_doc']['error'] === 0))) {
                header("Location: dashboard.php");
                exit();
            }
            
            // Refresh user data
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $errors[] = "Error updating profile: " . $conn->error;
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
    }
}

// Check if this is a profile completion request
$complete_profile = isset($_GET['complete_profile']) && $_GET['complete_profile'] == 1;
$profile_incomplete = empty($user['mobile']) || empty($user['verification_doc']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $complete_profile ? 'Complete Your Profile - Auto Aura' : 'Auto Aura - User Profile'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        :root {
            --primary-color: #1a1a1a;
            --secondary-color: #f5b754;
            --background-color: #f5f6fa;
            --card-background: #ffffff;
            --text-color: #333;
            --border-color: #e0e0e0;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Profile Header */
        .profile-header {
            background: var(--card-background);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-picture {
            position: relative;
            width: 150px;
            height: 150px;
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .edit-picture {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--secondary-color);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
            transition: transform 0.3s ease;
        }

        .edit-picture:hover {
            transform: scale(1.1);
        }

        .profile-info h1 {
            font-size: 2em;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        /* Main Content Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .card {
            background: var(--card-background);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-header h2 {
            color: var(--primary-color);
            font-size: 1.5em;
        }

        .card-header .icon {
            color: var(--secondary-color);
            font-size: 1.2em;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary-color);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .form-group input[type="file"] {
            padding: 8px;
            background-color: #f8f9fa;
            cursor: pointer;
        }

        .form-group input[type="file"]::-webkit-file-upload-button {
            padding: 8px 16px;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 4px;
            margin-right: 10px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .form-group input[type="file"]::-webkit-file-upload-button:hover {
            background-color: #e4a643;
        }

        .hint {
            display: block;
            margin-top: 6px;
            font-size: 0.85em;
            color: #666;
        }

        .submit-btn {
            width: 100%;
            padding: 14px 24px;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.3s ease, background-color 0.3s ease;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            background-color: #e4a643;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        /* Status Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .required-field::after {
            content: '*';
            color: red;
            margin-left: 4px;
        }
        
        .profile-completion-banner {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid #ffeeba;
            text-align: center;
        }

        .security-btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            gap: 8px;
        }

        .security-btn i {
            font-size: 1.1em;
        }

        .security-btn:hover {
            background-color: #e4a643;
            transform: translateY(-2px);
        }

        .security-btn:active {
            transform: translateY(0);
        }

        .password-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 10px;
        }

        .password-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .password-info i {
            color: var(--secondary-color);
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['errors'])): ?>
            <?php foreach ($_SESSION['errors'] as $error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if ($complete_profile && $profile_incomplete): ?>
            <div class="profile-completion-banner">
                <h2>Welcome to Auto Aura!</h2>
                <p>Please complete your profile to continue. We need some additional information to provide you with the best service.</p>
            </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-picture">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" 
                     onerror="console.log('Profile picture failed to load:', this.src); this.src='https://www.gravatar.com/avatar/default?d=mp';">
                <?php if ($user['auth_type'] !== 'google'): ?>
                <div class="edit-picture">
                    <i class="fas fa-camera"></i>
                </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['fullname']); ?></h1>
                <p>Premium Member</p>
                <p>Member since: January 2024</p>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="profile-grid">
            <!-- Personal Information -->
            <div class="card">
                <div class="card-header">
                    <h2>Personal Information</h2>
                    <i class="fas fa-user icon"></i>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="fullname">Full Name</label>
                        <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="mobile" class="<?php echo $complete_profile ? 'required-field' : ''; ?>">Mobile Number</label>
                        <input type="tel" id="mobile" name="mobile" value="<?php echo htmlspecialchars($user['mobile']); ?>" 
                               pattern="[6-9][0-9]{9}" maxlength="10" 
                               <?php echo $complete_profile ? 'required' : ''; ?>>
                        <small class="hint">Enter a valid 10-digit mobile number starting with 6-9</small>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    <?php if (empty($user['verification_doc']) || isset($_GET['complete_profile'])): ?>
                    <div class="form-group">
                        <label for="verification_doc" class="<?php echo $complete_profile ? 'required-field' : ''; ?>">Verification Document</label>
                        <input type="file" id="verification_doc" name="verification_doc" accept="application/pdf"
                               <?php echo $complete_profile ? 'required' : ''; ?>>
                        <small class="hint">Upload a valid ID document (PDF format only, max 5MB)</small>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="submit-btn">
                        <?php echo $complete_profile ? 'Complete Profile' : 'Update Profile'; ?>
                    </button>
                </form>
            </div>

            <!-- Account Details -->
            <div class="card">
                <div class="card-header">
                    <h2>Account Details</h2>
                    <i class="fas fa-id-card icon"></i>
                </div>
                <div class="form-group">
                    <label>Membership Status</label>
                    <div class="badge badge-success">Standard</div>
                </div>
                <div class="form-group">
                    <label>Loyalty Points</label>
                    <h3>0 points</h3>
                </div>
                <div class="form-group">
                    <label>Payment Methods</label>
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-credit-card" style="font-size: 24px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                        No payment methods added
                    </div>
                </div>
            </div>

            <!-- Rental History -->
            <div class="card">
                <div class="card-header">
                    <h2>Recent Rentals</h2>
                    <i class="fas fa-car icon"></i>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Car</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px;">
                                <i class="fas fa-calendar-alt" style="font-size: 24px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                No rental history available
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Security Settings -->
            <div class="card">
                <div class="card-header">
                    <h2>Security Settings</h2>
                    <i class="fas fa-shield-alt icon"></i>
                </div>
                <div class="form-group">
                    <label>Password Security</label>
                    <div class="password-section">
                        <div class="password-info">
                            <i class="fas fa-key"></i>
                            <span>Change your account password</span>
                        </div>
                        <a href="forgot-password.php" class="security-btn">
                            <i class="fas fa-lock"></i>
                            Change Password
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Profile Picture Upload
        document.querySelector('.edit-picture').addEventListener('click', () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    const formData = new FormData();
                    formData.append('profile_picture', file);
                    
                    // Upload the file
                    fetch('upload_profile_picture.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.querySelector('.profile-picture img').src = data.picture_url;
                        } else {
                            alert('Failed to upload image: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to upload image. Please try again.');
                    });
                }
            };
            input.click();
        });

        // Mobile number validation
        document.getElementById('mobile').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });

        // Security button handlers
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (e.target.textContent === 'Change Password' && e.target.type !== 'submit') {
                    // Add password change modal logic
                    alert('Password change functionality will be implemented here');
                } else if (e.target.textContent === 'Enable 2FA') {
                    // Add 2FA setup logic
                    alert('2FA setup will be implemented here');
                }
            });
        });
    </script>
</body>
</html>
