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
$stmt = $conn->prepare("SELECT fullname, email, mobile, address, profile_picture, auth_type, verification_doc, created_at FROM users WHERE id = ?");
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
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .card-header {
            background: #fff;
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: #333;
            font-weight: 600;
        }

        .card-header .icon {
            font-size: 1.5rem;
            color: #f5b754;
            margin-left: 15px;
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
        .payment-history {
            max-height: 400px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #f5b754 #f1f1f1;
            padding: 5px 15px 15px;
        }

        .payment-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 10px 0;
        }

        .payment-table th,
        .payment-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .payment-table th {
            position: sticky;
            top: 0;
            background: #fff;
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .payment-table tbody tr {
            transition: all 0.2s ease;
        }

        .payment-table tbody tr:hover {
            background-color: #fff9f0;
        }

        .payment-table td {
            font-size: 0.95rem;
            color: #444;
        }

        .payment-table td:nth-child(4) {
            font-weight: 600;
            color: #2c3e50;
        }

        .payment-table tfoot {
            position: sticky;
            bottom: 0;
            background: #fff;
            box-shadow: 0 -2px 4px rgba(0,0,0,0.05);
        }

        .payment-table tfoot td {
            font-weight: 600;
            color: #333;
            background: #f8f9fa;
            padding: 15px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            text-transform: capitalize;
        }

        .status-badge.completed {
            background-color: #e7f5ea;
            color: #0a6c1f;
            border: 1px solid #c3e6cb;
        }

        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .status-badge.failed {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-payments {
            text-align: center;
            padding: 40px 20px;
            background: #f9f9f9;
            border-radius: 8px;
            margin: 15px;
        }

        .no-payments i {
            font-size: 32px;
            color: #ccc;
            margin-bottom: 10px;
        }

        .no-payments p {
            margin: 0;
            font-size: 1rem;
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

        .rental-history {
            padding: 15px;
            max-height: 500px;
            overflow-y: auto;
        }
        .rental-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 10px;
        }
        .rental-table th,
        .rental-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .rental-table th {
            background: #fff;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .rental-table tr:hover {
            background-color: #fff9f0;
            transition: background-color 0.2s ease;
        }
        .car-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .car-thumbnail {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .car-info div {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .car-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .car-brand {
            color: #666;
            font-size: 0.9rem;
        }
        .rental-dates {
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: #555;
        }
        .rental-dates div {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .rental-dates i {
            width: 16px;
            color: #f5b754;
        }
        .total-amount {
            color: #2c3e50;
            font-size: 1.1rem;
        }
        .status-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            text-align: center;
            min-width: 100px;
        }
        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .status-badge.confirmed {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-badge.cancelled {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-badge.completed {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        .no-rentals {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            background: #f9f9f9;
            border-radius: 8px;
            margin: 15px;
        }
        .no-rentals i {
            font-size: 32px;
            color: #ccc;
            margin-bottom: 15px;
            display: block;
        }
        .no-rentals p {
            margin: 0;
            font-size: 1rem;
        }

        /* Personal Information Card */
        .personal-info-card {
            background: var(--card-background);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 25px;
        }

        .personal-info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            padding: 25px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1));
            pointer-events: none;
        }

        .card-header h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            font-size: 1.4rem;
            font-weight: 500;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .card-header h2 i {
            font-size: 1.6rem;
            opacity: 0.9;
        }

        .card-content {
            padding: 30px;
            background: white;
        }

        .personal-info-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            color: #666;
            transition: color 0.3s ease;
        }

        .input-group input,
        .input-group textarea {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .input-group input:hover,
        .input-group textarea:hover {
            border-color: var(--secondary-color);
        }

        .input-group input:focus,
        .input-group textarea:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(245, 183, 84, 0.1);
            outline: none;
        }

        .input-group input:focus + .input-icon,
        .input-group textarea:focus + .input-icon {
            color: var(--secondary-color);
        }

        .input-group textarea {
            min-height: 100px;
            resize: vertical;
            line-height: 1.6;
        }

        .hint {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            font-size: 0.85rem;
            color: #666;
            opacity: 0.9;
        }

        .hint i {
            color: var(--secondary-color);
        }

        .document-upload {
            margin-top: 10px;
        }

        .upload-area {
            position: relative;
            padding: 30px;
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #fafafa;
        }

        .upload-area:hover {
            border-color: var(--secondary-color);
            background: rgba(245, 183, 84, 0.05);
        }

        .upload-area input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .upload-info i {
            font-size: 2rem;
            color: var(--secondary-color);
            opacity: 0.9;
        }

        .upload-info span {
            font-size: 1rem;
            color: #666;
        }

        .form-actions {
            margin-top: 30px;
        }

        .submit-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn i {
            font-size: 1.1rem;
        }

        /* Add animation for required fields */
        .required-field::after {
            content: '*';
            color: var(--secondary-color);
            margin-left: 4px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.5; }
            50% { opacity: 1; }
            100% { opacity: 0.5; }
        }

        /* Rental History Styles */
        .rental-history {
            padding: 15px;
            max-height: 500px;
            overflow-y: auto;
        }

        .rental-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 10px;
        }

        .rental-table th,
        .rental-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .rental-table th {
            background: #fff;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .rental-table tr:hover {
            background-color: #fff9f0;
            transition: all 0.2s ease;
        }

        .car-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .car-thumbnail {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .car-thumbnail:hover {
            transform: scale(1.05);
        }

        .car-info div {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .car-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }

        .car-brand {
            color: #666;
            font-size: 0.9rem;
        }

        .car-price {
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .rental-dates {
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: #555;
        }

        .rental-dates div {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rental-dates i {
            width: 16px;
            color: #f5b754;
        }

        .rental-duration {
            margin-top: 4px;
            font-size: 0.9rem;
            color: #666;
        }

        .rental-duration span {
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
        }

        .location-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
            font-size: 0.95rem;
        }

        .location-info i {
            color: #f5b754;
        }

        .status-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            text-align: center;
            min-width: 100px;
            transition: transform 0.2s ease;
        }

        .status-badge:hover {
            transform: scale(1.05);
        }

        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .status-badge.confirmed {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-badge.cancelled {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-badge.completed {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .no-rentals {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            background: #f9f9f9;
            border-radius: 8px;
            margin: 15px;
        }

        .no-rentals i {
            font-size: 32px;
            color: #ccc;
            margin-bottom: 15px;
            display: block;
        }

        .no-rentals p {
            margin: 0 0 5px;
            font-size: 1.1rem;
            color: #444;
        }

        .no-rentals small {
            color: #888;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .card-content {
                padding: 20px;
            }

            .upload-area {
                padding: 20px;
            }

            .rental-table th,
            .rental-table td {
                padding: 12px 8px;
            }

            .car-info {
                flex-direction: column;
                gap: 8px;
            }

            .car-thumbnail {
                width: 60px;
                height: 45px;
            }
        }

        /* Card Base Styles */
        .profile-card {
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin: 0 15px 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(135deg, #b8860b, #daa520, #ffd700);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-card:hover::before {
            opacity: 0.1;
        }

        .card-header {
            padding: 25px 30px;
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
            position: relative;
            overflow: hidden;
        }

        .card-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(218, 165, 32, 0.8), rgba(255, 215, 0, 0.4));
            mix-blend-mode: overlay;
        }

        .card-header h2 {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
            letter-spacing: 0.5px;
        }

        .card-header h2 i {
            font-size: 1.8rem;
            color: #ffd700;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .card-content {
            padding: 30px;
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-top: none;
        }

        /* Card Grid Layout */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Interactive Elements */
        .profile-card .interactive-element {
            position: relative;
            overflow: hidden;
        }

        .profile-card .interactive-element::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 215, 0, 0.1) 0%, transparent 70%);
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .profile-card:hover .interactive-element::after {
            opacity: 1;
            transform: scale(1);
        }

        /* Label & Text Styling */
        .card-label {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
            font-size: 1.1rem;
            letter-spacing: 0.3px;
        }

        .card-text {
            color: #333333;
            font-size: 1rem;
            line-height: 1.6;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 15px;
            }

            .profile-card {
                margin: 0 10px 20px;
            }

            .card-header {
                padding: 20px 25px;
            }

            .card-header h2 {
                font-size: 1.3rem;
            }

            .card-content {
                padding: 25px;
            }
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
                <p>Member since: <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="profile-grid">
            <!-- Personal Information -->
            <div class="profile-card">
                <div class="card-header">
                    <h2><i class="fas fa-user icon"></i> Personal Information</h2>
                </div>
                <div class="card-content">
                    <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" enctype="multipart/form-data" class="personal-info-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fullname">Full Name</label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-user"></i></span>
                                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                                    <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="mobile" class="<?php echo $complete_profile ? 'required-field' : ''; ?>">Mobile Number</label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-phone"></i></span>
                                    <input type="tel" id="mobile" name="mobile" value="<?php echo htmlspecialchars($user['mobile']); ?>" 
                                           pattern="[6-9][0-9]{9}" maxlength="10" 
                                           <?php echo $complete_profile ? 'required' : ''; ?>>
                                </div>
                                <small class="hint"><i class="fas fa-info-circle"></i> Enter a valid 10-digit mobile number starting with 6-9</small>
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                                    <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <?php if (empty($user['verification_doc']) || isset($_GET['complete_profile'])): ?>
                        <div class="form-group document-upload">
                            <label for="verification_doc" class="<?php echo $complete_profile ? 'required-field' : ''; ?>">
                                <i class="fas fa-file-pdf"></i> Verification Document
                            </label>
                            <div class="upload-area">
                                <input type="file" id="verification_doc" name="verification_doc" accept="application/pdf"
                                       <?php echo $complete_profile ? 'required' : ''; ?>>
                                <div class="upload-info">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Drop your file here or click to browse</span>
                                    <small class="hint">Upload a valid ID document (PDF format only, max 5MB)</small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="form-actions">
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-save"></i>
                                <?php echo $complete_profile ? 'Complete Profile' : 'Update Profile'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payment History -->
            <div class="profile-card">
                <div class="card-header">
                    <h2>Payment History</h2>
                    <i class="fas fa-receipt icon"></i>
                </div>
                <?php
                // Fetch user's payment history
                $payment_query = $conn->prepare("SELECT payment_id, order_id, amount, status, created_at FROM payments WHERE user_id = ? ORDER BY created_at DESC");
                $payment_query->bind_param("i", $user_id);
                $payment_query->execute();
                $payments = $payment_query->get_result();
                
                if ($payments->num_rows > 0) {
                    $total_amount = 0;
                    ?>
                    <div class="payment-history">
                        <table class="payment-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Order ID</th>
                                    <th>Payment ID</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $payments->fetch_assoc()): 
                                    $total_amount += $payment['amount'];
                                ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($payment['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['order_id']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                    <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><span class="status-badge <?php echo strtolower($payment['status']); ?>"><?php echo htmlspecialchars($payment['status']); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3"><strong>Total Amount</strong></td>
                                    <td colspan="2"><strong>₹<?php echo number_format($total_amount, 2); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="no-payments">
                        <i class="fas fa-receipt" style="font-size: 24px; color: #ccc; margin: 20px 0;"></i>
                        <p>No payment history available</p>
                    </div>
                    <?php
                }
                ?>
            </div>

            <!-- Rental History -->
            <div class="profile-card">
                <div class="card-header">
                    <h2>Recent Rentals</h2>
                    <i class="fas fa-car icon"></i>
                </div>
                <div class="rental-history">
                    <?php
                    // Fetch user's recent rentals with car details and payment status
                    $rental_query = $conn->prepare("
                        SELECT b.booking_id, b.start_date, b.end_date, b.total_price, 
                               b.status as booking_status, b.payment_status, b.pickup_location,
                               c.car_name, c.car_features, c.images, c.price
                        FROM bookings b
                        LEFT JOIN cars c ON b.car_id = c.id
                        WHERE b.user_id = ?
                        ORDER BY b.booking_date DESC
                        LIMIT 5
                    ");
                    $rental_query->bind_param("i", $user_id);
                    $rental_query->execute();
                    $rentals = $rental_query->get_result();
                    
                    if ($rentals->num_rows > 0) {
                    ?>
                        <table class="rental-table">
                            <thead>
                                <tr>
                                    <th>Car Details</th>
                                    <th>Rental Period</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($rental = $rentals->fetch_assoc()): 
                                    // Default image path
                                    $first_image = 'assets/images/default-car.jpg';
                                    
                                    // Handle car images
                                    if (!empty($rental['images']) && $rental['images'] !== 'null') {
                                        $decoded_images = json_decode($rental['images'], true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images)) {
                                            foreach ($decoded_images as $img) {
                                                if (!empty($img) && file_exists($img)) {
                                                    $first_image = $img;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Handle car features
                                    $features = [];
                                    if (!empty($rental['car_features']) && $rental['car_features'] !== 'null') {
                                        $decoded_features = json_decode($rental['car_features'], true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_features)) {
                                            $features = $decoded_features;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="car-info">
                                            <img src="<?php echo htmlspecialchars($first_image); ?>" alt="Car Image" class="car-thumbnail">
                                            <div>
                                                <span class="car-name"><?php echo htmlspecialchars($rental['car_name']); ?></span>
                                                <span class="car-features">
                                                    <?php 
                                                        if (!empty($features)) {
                                                            $main_features = array_slice($features, 0, 2);
                                                            echo htmlspecialchars(implode(' • ', $main_features));
                                                        }
                                                    ?>
                                                </span>
                                                <span class="car-price">₹<?php echo number_format($rental['total_price'], 2); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="rental-dates">
                                            <div>
                                                <i class="fas fa-calendar-check"></i>
                                                <?php echo date('d M Y', strtotime($rental['start_date'])); ?>
                                            </div>
                                            <div>
                                                <i class="fas fa-calendar-times"></i>
                                                <?php echo date('d M Y', strtotime($rental['end_date'])); ?>
                                            </div>
                                            <div class="rental-duration">
                                                <?php 
                                                    $start = new DateTime($rental['start_date']);
                                                    $end = new DateTime($rental['end_date']);
                                                    $days = $end->diff($start)->days + 1;
                                                    echo "<span>{$days} " . ($days == 1 ? "day" : "days") . "</span>";
                                                ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="location-info">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($rental['pickup_location']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="status-group">
                                            <span class="status-badge <?php echo strtolower($rental['booking_status']); ?>">
                                                <?php echo ucfirst($rental['booking_status']); ?>
                                            </span>
                                            <span class="status-badge <?php echo strtolower($rental['payment_status']); ?>">
                                                <?php echo ucfirst($rental['payment_status']); ?>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php
                    } else {
                    ?>
                        <div class="no-rentals">
                            <i class="fas fa-car-alt"></i>
                            <p>No rental history available</p>
                            <small>Your rental bookings will appear here</small>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="profile-card">
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
