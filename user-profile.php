<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-page.php");
    exit();
}

require_once 'db_connect.php';

// Display messages if any
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

// Get user details from database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT fullname, email, mobile, address, profile_picture, auth_type, verification_doc, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get rentals data with car details
$rental_query = $conn->prepare("
    SELECT b.booking_id, b.booking_date, b.start_date, b.end_date, 
           b.pickup_time, b.status, b.payment_status, c.car_name,
           DATE_FORMAT(b.booking_date, '%M %d, %Y') as formatted_booking_date,
           DATE_FORMAT(b.start_date, '%M %d, %Y') as formatted_start_date,
           DATE_FORMAT(b.end_date, '%M %d, %Y') as formatted_end_date,
           TIME_FORMAT(b.pickup_time, '%h:%i %p') as formatted_pickup_time
    FROM bookings b 
    LEFT JOIN cars c ON b.car_id = c.id 
    WHERE b.user_id = ? 
    ORDER BY b.booking_date DESC
");
$rental_query->bind_param("i", $user_id);
$rental_query->execute();
$rentals = $rental_query->get_result();

// Fetch payments for the current user with pagination
$page = isset($_GET['payment_page']) ? (int)$_GET['payment_page'] : 1;
$items_per_page = 5;
$offset = ($page - 1) * $items_per_page;

$payment_query = "SELECT p.*, b.car_id 
                 FROM payments p 
                 JOIN bookings b ON p.booking_id = b.booking_id 
                 WHERE p.user_id = ? 
                 ORDER BY p.created_at DESC 
                 LIMIT ? OFFSET ?";
        
if ($stmt = $conn->prepare($payment_query)) {
    $stmt->bind_param("iii", $user_id, $items_per_page, $offset);
    $stmt->execute();
    $payments = $stmt->get_result();
    
    // Get total number of payments for pagination
    $total_query = "SELECT COUNT(*) as total FROM payments WHERE user_id = ?";
    $total_stmt = $conn->prepare($total_query);
    $total_stmt->bind_param("i", $user_id);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_payments = $total_row['total'];
    $total_pages = ceil($total_payments / $items_per_page);
}

$profile_picture = $user['profile_picture'] ?? 'https://www.gravatar.com/avatar/default?d=mp';

// Check if this is a profile completion request
$complete_profile = isset($_GET['complete_profile']) && $_GET['complete_profile'] == 1;
$profile_incomplete = empty($user['mobile']) || empty($user['verification_doc']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Aura Premium</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #d6a04a;
            --primary-dark: #1557b0;
            --secondary-color: #f8f9fa;
            --text-color: #333;
            --text-light: #666;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --border-radius: 8px;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: var(--secondary-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo img {
            height: 60px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-color);
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links a:hover {
            background: var(--secondary-color);
            color: var(--primary-color);
        }

        .nav-links a.active {
            background: var(--primary-color);
            color: white;
        }

        .section {
            display: none;
            opacity: 0;
            transform: translateY(20px);
            transition: var(--transition);
        }

        .section.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .profile-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .profile-section:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
            align-items: center;
            background: linear-gradient(135deg, var(--secondary-color), white);
            padding: 20px;
            border-radius: var(--border-radius);
        }

        .profile-picture-container {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 20px;
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-picture-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            padding: 8px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .profile-picture-container:hover .profile-picture-overlay {
            opacity: 1;
        }

        .change-photo-btn {
            color: white;
            font-size: 14px;
            cursor: pointer;
            display: block;
            text-align: center;
        }

        .change-photo-btn i {
            margin-right: 5px;
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-info p {
            color: var(--text-light);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-info .member-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-left: 10px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            color: var(--primary-color);
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: var(--transition);
        }

        .form-group input.disabled-input {
            background-color: #f5f5f5;
            cursor: not-allowed;
            color: #666;
            border: 1px solid #ddd;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 2px 8px rgba(26, 115, 232, 0.1);
        }

        .form-group input:hover {
            border-color: var(--primary-color);
        }

        .form-group .input-icon {
            position: absolute;
            right: 12px;
            top: 38px;
            color: var(--text-light);
            transition: var(--transition);
        }

        .form-group input:focus + .input-icon {
            color: var(--primary-color);
        }

        .update-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: 0 2px 4px rgba(26, 115, 232, 0.2);
        }

        .update-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 115, 232, 0.3);
        }

        .update-btn:active {
            transform: translateY(0);
        }

        .update-btn i {
            font-size: 18px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            table-layout: fixed;
        }

        .data-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 500;
            text-align: left;
            padding: 15px;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        /* Column widths */
        .data-table th:nth-child(1), .data-table td:nth-child(1) { width: 20%; } /* Booking Details */
        .data-table th:nth-child(2), .data-table td:nth-child(2) { width: 15%; } /* Car */
        .data-table th:nth-child(3), .data-table td:nth-child(3) { width: 25%; } /* Duration & Pickup */
        .data-table th:nth-child(4), .data-table td:nth-child(4) { width: 15%; } /* Payment Status */
        .data-table th:nth-child(5), .data-table td:nth-child(5) { width: 15%; } /* Status */
        .data-table th:nth-child(6), .data-table td:nth-child(6) { 
            width: 10%; 
            text-align: left;
            padding-left: 10px;
        } /* Action */

        @media (max-width: 768px) {
            .data-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            text-transform: capitalize;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }

        .status-badge:hover {
            transform: translateY(-2px);
        }

        .status-completed {
            background-color: #e8f5e9;
            color: var(--success-color);
        }

        .status-pending {
            background-color: #fff3e0;
            color: var(--warning-color);
        }

        .status-cancelled {
            background-color: #feeef0;
            color: var(--danger-color);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination button {
            padding: 8px 16px;
            border: 2px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            cursor: pointer;
            border-radius: var(--border-radius);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }

        .pagination button:hover:not(:disabled) {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            border-color: var(--text-light);
            color: var(--text-light);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .loading::after {
            content: '';
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid var(--secondary-color);
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
            background: var(--secondary-color);
            border-radius: var(--border-radius);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .no-data i {
            font-size: 48px;
            color: var(--text-light);
            opacity: 0.5;
        }

        /* Alert styles */
        .alert {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        .alert i {
            font-size: 20px;
        }

        .alert-success {
            background-color: #e8f5e9;
            color: var(--success-color);
            border: 1px solid #c8e6c9;
        }

        .alert-danger, .alert-error {
            background-color: #feeef0;
            color: var(--danger-color);
            border: 1px solid #ffcdd2;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* File upload styles */
        .file-upload {
            margin-bottom: 25px;
            position: relative;
        }

        .file-upload label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-upload .upload-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload .upload-btn {
            background: var(--secondary-color);
            border: 2px dashed var(--primary-color);
            padding: 12px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            color: var(--text-color);
        }

        .file-upload .upload-btn:hover {
            background: white;
            border-style: solid;
        }

        .file-upload .file-info {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--secondary-color);
            border-radius: var(--border-radius);
            color: var(--text-color);
        }

        .file-upload .file-info.active {
            display: flex;
        }

        .file-upload .remove-file {
            color: #dc3545;
            cursor: pointer;
            padding: 4px;
            border-radius: 50%;
            transition: var(--transition);
        }

        .file-upload .remove-file:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        .current-doc {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: var(--secondary-color);
            border-radius: var(--border-radius);
            margin-top: 8px;
        }

        .current-doc i {
            color: var(--primary-color);
        }

        .booking-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .booking-date {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-color);
        }

        .booking-id {
            color: var(--text-light);
        }

        .duration-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .duration-details div {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-color);
        }

        .duration-details i {
            color: var(--primary-color);
            width: 16px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            text-transform: capitalize;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }

        .status-badge:hover {
            transform: translateY(-2px);
        }

        .status-completed {
            background-color: #e8f5e9;
            color: var(--success-color);
        }

        .status-pending {
            background-color: #fff3e0;
            color: var(--warning-color);
        }

        .status-cancelled {
            background-color: #feeef0;
            color: var(--danger-color);
        }

        .cancel-btn {
            background-color: var(--danger-color);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }

        .cancel-btn:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .cancel-btn:active {
            transform: translateY(0);
        }

        .cancel-btn i {
            font-size: 18px;
        }

        .cancel-form {
            display: flex;
            justify-content: flex-start;
            margin-left: -5px;
        }

        @media (max-width: 768px) {
            .data-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .data-table th,
            .data-table td {
                padding: 12px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }

            .nav-links {
                width: 100%;
                justify-content: space-between;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-info {
                text-align: center;
            }
        }
        
        .cancel-notice {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
        }

        .cancel-notice i {
            margin-right: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success']); ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error']); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="header">
            <a href="index.php" class="logo">
                <img src="assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png" alt="Auto Aura Premium Logo" style="height: 60px;">
            </a>
            <div class="nav-links">
                <a href="#" data-section="personal" class="active">
                    <i class="fas fa-user"></i> Personal Info
                </a>
                <a href="#" data-section="rentals">
                    <i class="fas fa-history"></i> Recent Rentals
                </a>
                <a href="#" data-section="payments">
                    <i class="fas fa-credit-card"></i> Payment History
                </a>
            </div>
        </div>

        <!-- Personal Info Section -->
        <div id="personal" class="section profile-section active">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                        echo htmlspecialchars($_SESSION['success']); 
                        unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                        echo htmlspecialchars($_SESSION['error']); 
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            <h2><i class="fas fa-user-circle"></i> Personal Information</h2>
            
            <div class="profile-header">
                <div class="profile-picture-container">
                    <img src="<?php echo !empty($profile_picture) ? htmlspecialchars($profile_picture) : 'assets/default-profile.png'; ?>" alt="Profile Picture" class="profile-picture" id="preview-image">
                    <div class="profile-picture-overlay">
                        <label for="profile_picture" class="change-photo-btn">
                            <i class="fas fa-camera"></i> Change Photo
                        </label>
                    </div>
                </div>
                <div class="profile-info">
                    <h1>
                        <?php echo htmlspecialchars($user['fullname']); ?>
                        <span class="member-badge">
                            <i class="fas fa-star"></i>
                            Member since March 2025
                        </span>
                    </h1>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['mobile']); ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['address']); ?></p>
                </div>
            </div>

            <form action="update_profile.php" method="POST" class="profile-form" enctype="multipart/form-data">
                <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display: none;" onchange="previewImage(this)">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        <i class="fas fa-user input-icon"></i>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled readonly class="disabled-input">
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" name="mobile" id="mobile" value="<?php echo htmlspecialchars($user['mobile']); ?>" required>
                        <i class="fas fa-phone input-icon"></i>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($user['address']); ?>" required>
                        <i class="fas fa-map-marker-alt input-icon"></i>
                    </div>
                </div>
                <div class="file-upload">
                    <label><i class="fas fa-file-pdf"></i> Verification Document (PDF only)</label>
                    <div class="upload-wrapper">
                        <label class="upload-btn" for="verification_doc">
                            <i class="fas fa-upload"></i>
                            Choose PDF File
                        </label>
                        <input type="file" id="verification_doc" name="verification_doc" accept=".pdf">
                        <div class="file-info">
                            <i class="fas fa-file-pdf"></i>
                            <span class="file-name"></span>
                            <i class="fas fa-times remove-file"></i>
                        </div>
                    </div>
                    <div class="current-doc">
                        <?php if (!empty($user['verification_doc'])): ?>
                            <i class="fas fa-file-pdf"></i>
                            <span>Current document uploaded</span>
                            <a href="uploads/verification_documents/<?php echo htmlspecialchars($user['verification_doc']); ?>" target="_blank" class="view-doc">
                                <i class="fas fa-eye"></i> View
                            </a>
                        <?php else: ?>
                            <i class="fas fa-file-upload"></i>
                            <span>No verification document uploaded yet</span>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="update-btn">
                    <i class="fas fa-save"></i>
                    Update Profile
                </button>
            </form>
        </div>

        <!-- Rentals Section -->
        <div id="rentals" class="section profile-section">
            <h2><i class="fas fa-history"></i> Recent Rentals</h2>
            <div class="loading" id="rentals-loading"></div>
            <?php if ($rentals->num_rows === 0): ?>
                <div class="no-data">
                    <i class="fas fa-car-side"></i>
                    <p>No rental history found</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Booking Details</th>
                            <th>Car</th>
                            <th>Duration & Pickup</th>
                            <th>Payment Status</th>
                            <th>Status</th>
                            <?php 
                            // Check if there are any bookings that can be cancelled
                            $has_cancellable_bookings = false;
                            $rentals_array = [];
                            while ($rental = $rentals->fetch_assoc()) {
                                $end_date = new DateTime($rental['end_date']);
                                $current_date = new DateTime();
                                $days_until_end = $current_date->diff($end_date)->days;
                                $is_future_booking = $end_date > $current_date;
                                $can_cancel = $is_future_booking && $days_until_end >= 2 && $rental['status'] !== 'cancelled';
                                
                                if ($can_cancel) {
                                    $has_cancellable_bookings = true;
                                }
                                $rentals_array[] = $rental;
                            }
                            if ($has_cancellable_bookings): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $items_per_page = 5;
                        $total_rentals = count($rentals_array);
                        $total_pages = ceil($total_rentals / $items_per_page);
                        foreach ($rentals_array as $rental): 
                            // Calculate if booking can be cancelled
                            $end_date = new DateTime($rental['end_date']);
                            $current_date = new DateTime();
                            $days_until_end = $current_date->diff($end_date)->days;
                            $is_future_booking = $end_date > $current_date;
                            $can_cancel = $is_future_booking && $days_until_end >= 2 && $rental['status'] !== 'cancelled';
                        ?>
                            <tr class="rental-row" data-page="1">
                                <td>
                                    <div class="booking-details">
                                        <div class="booking-date">
                                            <i class="fas fa-calendar-alt"></i> 
                                            <?php echo $rental['formatted_booking_date']; ?>
                                        </div>
                                        <div class="booking-id">
                                            <small>ID: #<?php echo str_pad($rental['booking_id'], 6, '0', STR_PAD_LEFT); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($rental['car_name']); ?></td>
                                <td>
                                    <div class="duration-details">
                                        <div>
                                            <i class="fas fa-calendar"></i> 
                                            <?php echo $rental['formatted_start_date']; ?> - <?php echo $rental['formatted_end_date']; ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-clock"></i> 
                                            <?php echo $rental['formatted_pickup_time']; ?>
                                        </div>
                                        <?php if (!$can_cancel && $rental['status'] !== 'cancelled' && $is_future_booking): ?>
                                        <div class="cancel-notice">
                                            <small><i class="fas fa-info-circle"></i> Cannot cancel within 2 days of end date</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($rental['payment_status']); ?>">
                                        <?php echo ucfirst($rental['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($rental['status']); ?>">
                                        <?php echo ucfirst($rental['status']); ?>
                                    </span>
                                </td>
                                <?php if ($has_cancellable_bookings): ?>
                                <td>
                                    <?php if ($can_cancel): ?>
                                    <form action="cancel_booking.php" method="POST" class="cancel-form" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                        <input type="hidden" name="booking_id" value="<?php echo $rental['booking_id']; ?>">
                                        <button type="submit" class="cancel-btn">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="pagination" id="rentals-pagination">
                    <button onclick="changePage('rentals', 'prev')" disabled>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <span id="rentals-page-info">Page 1 of <?php echo $total_pages; ?></span>
                    <button onclick="changePage('rentals', 'next')" <?php echo $total_pages <= 1 ? 'disabled' : ''; ?>>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payments Section -->
        <div id="payments" class="section profile-section">
            <h2><i class="fas fa-credit-card"></i> Payment History</h2>
            <div class="loading" id="payments-loading"></div>
            <?php if ($payments && $payments->num_rows === 0): ?>
                <div class="no-data">
                    <i class="fas fa-receipt"></i>
                    <p>No payment history found</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $payments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['order_id']); ?></td>
                                <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($payment['status']); ?>">
                                        <i class="fas fa-<?php echo $payment['status'] === 'completed' ? 'check-circle' : ($payment['status'] === 'pending' ? 'clock' : 'times-circle'); ?>"></i>
                                        <?php echo htmlspecialchars(ucfirst($payment['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <div class="pagination" id="payments-pagination">
                    <button onclick="changePage('payments', <?php echo max(1, $page - 1); ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <span id="payments-page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                    <button onclick="changePage('payments', <?php echo min($total_pages, $page + 1); ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mobile number validation with visual feedback
        document.getElementById('mobile').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
            if (this.value.length === 10) {
                this.style.borderColor = 'var(--success-color)';
            } else {
                this.style.borderColor = 'var(--text-light)';
            }
        });

        // Enhanced tab switching functionality with smooth transitions
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sectionId = this.getAttribute('data-section');
                
                // Update active tab with ripple effect
                document.querySelectorAll('.nav-links a').forEach(a => {
                    a.classList.remove('active');
                    a.style.transform = 'scale(1)';
                });
                this.classList.add('active');
                this.style.transform = 'scale(0.95)';
                setTimeout(() => this.style.transform = 'scale(1)', 150);
                
                // Show loading state
                document.querySelectorAll('.loading').forEach(loader => loader.style.display = 'none');
                const loader = document.getElementById(`${sectionId}-loading`);
                if (loader) {
                    loader.style.display = 'block';
                    loader.style.opacity = '0';
                    setTimeout(() => loader.style.opacity = '1', 50);
                }
                
                // Show selected section with fade effect
                setTimeout(() => {
                    document.querySelectorAll('.section').forEach(section => {
                        section.classList.remove('active');
                        section.style.opacity = '0';
                        section.style.transform = 'translateY(20px)';
                    });
                    const targetSection = document.getElementById(sectionId);
                    targetSection.classList.add('active');
                    setTimeout(() => {
                        targetSection.style.opacity = '1';
                        targetSection.style.transform = 'translateY(0)';
                        if (loader) loader.style.display = 'none';
                    }, 50);
                }, 300);
            });
        });

        // Enhanced pagination with smooth transitions
        function changePage(section, direction) {
            const rows = document.querySelectorAll(`.${section}-row`);
            const itemsPerPage = 5;
            const totalPages = Math.ceil(rows.length / itemsPerPage);
            const currentPage = parseInt(rows[0].getAttribute('data-page'));
            const newPage = direction === 'next' ? currentPage + 1 : currentPage - 1;

            if (newPage < 1 || newPage > totalPages) return;

            // Show loading state
            const loader = document.getElementById(`${section}-loading`);
            if (loader) {
                loader.style.display = 'block';
                loader.style.opacity = '0';
                setTimeout(() => loader.style.opacity = '1', 50);
            }

            // Update rows visibility with fade effect
            setTimeout(() => {
                rows.forEach((row, index) => {
                    const shouldShow = index >= (newPage - 1) * itemsPerPage && index < newPage * itemsPerPage;
                    row.style.opacity = '0';
                    row.style.transform = shouldShow ? 'translateX(20px)' : 'translateX(-20px)';
                    setTimeout(() => {
                        row.style.display = shouldShow ? '' : 'none';
                        if (shouldShow) {
                            setTimeout(() => {
                                row.style.opacity = '1';
                                row.style.transform = 'translateX(0)';
                            }, 50);
                        }
                    }, 300);
                    row.setAttribute('data-page', newPage);
                });

                // Update pagination controls with animation
                const pagination = document.getElementById(`${section}-pagination`);
                const prevButton = pagination.querySelector('button:first-child');
                const nextButton = pagination.querySelector('button:last-child');
                const pageInfo = document.getElementById(`${section}-page-info`);

                prevButton.disabled = newPage === 1;
                nextButton.disabled = newPage === totalPages;
                
                pageInfo.style.opacity = '0';
                setTimeout(() => {
                    pageInfo.textContent = `Page ${newPage} of ${totalPages}`;
                    pageInfo.style.opacity = '1';
                }, 300);

                if (loader) {
                    setTimeout(() => loader.style.display = 'none', 600);
                }
            }, 300);
        }

        // Add hover effect to table rows
        document.querySelectorAll('.data-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transition = 'all 0.3s ease';
                this.style.transform = 'translateX(5px)';
                this.style.backgroundColor = 'var(--secondary-color)';
            });
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
                this.style.backgroundColor = '';
            });
        });

        // Initialize the first tab
        window.onload = function() {
            document.querySelector('.nav-links a.active').click();
        };

        // File upload handling
        document.getElementById('verification_doc').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const fileInfo = document.querySelector('.file-info');
            const fileNameSpan = document.querySelector('.file-name');
            
            if (fileName) {
                if (!fileName.toLowerCase().endsWith('.pdf')) {
                    alert('Please select a PDF file only');
                    e.target.value = '';
                    fileInfo.classList.remove('active');
                    return;
                }
                
                fileNameSpan.textContent = fileName;
                fileInfo.classList.add('active');
            } else {
                fileInfo.classList.remove('active');
            }
        });

        document.querySelector('.remove-file').addEventListener('click', function() {
            const fileInput = document.getElementById('verification_doc');
            const fileInfo = document.querySelector('.file-info');
            
            fileInput.value = '';
            fileInfo.classList.remove('active');
        });

        function changePage(section, page) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set(section + '_page', page);
            window.location.href = currentUrl.toString();
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-image').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>