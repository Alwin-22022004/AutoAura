<?php

session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Generate a unique token if not exists
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

include 'db_connect.php';

// Get active listings count
$active_listings_query = "SELECT COUNT(*) as active_count FROM cars WHERE is_active = 1";
$active_result = $conn->query($active_listings_query);
$active_count = $active_result->fetch_assoc()['active_count'];

// Get total bookings count
$bookings_query = "SELECT COUNT(*) as booking_count FROM bookings";
$bookings_result = $conn->query($bookings_query);
$booking_count = $bookings_result->fetch_assoc()['booking_count'];

// Get filter type from URL parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Base query
$all_bookings_query = "SELECT 
    b.booking_id,
    b.start_date,
    b.end_date,
    b.pickup_time,
    b.pickup_location,
    b.center,
    b.payment_method,
    b.status as booking_status,
    b.total_price,
    u.fullname as user_name,
    u.email as user_email,
    u.mobile as user_phone,
    c.car_name,
    c.car_features,
    p.amount,
    CASE 
        WHEN b.end_date < CURRENT_DATE() THEN 'Completed'
        ELSE p.status 
    END as payment_status,
    p.payment_id as transaction_id
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN cars c ON b.car_id = c.id
LEFT JOIN payments p ON b.booking_id = p.booking_id";

// Add WHERE clause based on filter
if ($filter === 'completed') {
    $all_bookings_query .= " WHERE b.status = 'completed' OR (b.end_date < CURRENT_DATE() AND b.status != 'cancelled')";
} elseif ($filter === 'cancelled') {
    $all_bookings_query .= " WHERE b.status = 'cancelled'";
}

$all_bookings_query .= " ORDER BY b.booking_date DESC";

$all_bookings_result = $conn->query($all_bookings_query);

// Update the earnings query to get total of all payments
$earnings_query = "SELECT SUM(amount) as total_earnings FROM payments";
$earnings_result = $conn->query($earnings_query);
$total_earnings = $earnings_result->fetch_assoc()['total_earnings'] ?? 0;

// Get payment analytics
$payment_analytics_query = "SELECT 
    DATE_FORMAT(p.created_at, '%Y-%m') as month,
    COUNT(DISTINCT p.id) as total_transactions,
    SUM(p.amount) as monthly_revenue,
    COUNT(DISTINCT p.user_id) as unique_customers,
    AVG(p.amount) as average_transaction,
    p.status,
    COUNT(DISTINCT b.car_id) as cars_booked
FROM payments p
JOIN bookings b ON p.booking_id = b.booking_id
GROUP BY DATE_FORMAT(p.created_at, '%Y-%m'), p.status
ORDER BY month DESC
LIMIT 12";

$payment_analytics = $conn->query($payment_analytics_query);

// Get payment method distribution
$payment_methods_query = "SELECT 
    b.payment_method,
    COUNT(*) as count,
    SUM(p.amount) as total_amount,
    AVG(p.amount) as avg_transaction
FROM bookings b
JOIN payments p ON b.booking_id = p.booking_id
GROUP BY b.payment_method";

$payment_methods = $conn->query($payment_methods_query);

// Get top performing cars
$top_cars_query = "SELECT 
    c.car_name,
    COUNT(b.booking_id) as total_bookings,
    SUM(p.amount) as total_revenue,
    AVG(p.amount) as avg_booking_value
FROM cars c
JOIN bookings b ON c.id = b.car_id
JOIN payments p ON b.booking_id = p.booking_id
GROUP BY c.id
ORDER BY total_revenue DESC
LIMIT 5";

$top_cars = $conn->query($top_cars_query);

// Get customer insights
$customer_insights_query = "SELECT 
    COUNT(DISTINCT u.id) as total_customers,
    COUNT(DISTINCT CASE WHEN b.status = 'confirmed' THEN u.id END) as active_customers,
    COUNT(DISTINCT CASE WHEN DATE(u.created_at) = CURDATE() THEN u.id END) as new_customers,
    ROUND(AVG(p.amount), 2) as avg_customer_spend,
    COUNT(b.booking_id) / COUNT(DISTINCT u.id) as bookings_per_customer
FROM users u
LEFT JOIN bookings b ON u.id = b.user_id
LEFT JOIN payments p ON b.booking_id = p.booking_id";

$customer_insights = $conn->query($customer_insights_query);

// Get booking trends
$booking_trends_query = "SELECT 
    DATE_FORMAT(b.booking_date, '%Y-%m') as month,
    COUNT(*) as total_bookings,
    COUNT(DISTINCT b.user_id) as unique_customers,
    SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
    AVG(DATEDIFF(b.end_date, b.start_date)) as avg_booking_duration
FROM bookings b
GROUP BY DATE_FORMAT(b.booking_date, '%Y-%m')
ORDER BY month DESC
LIMIT 6";

$booking_trends = $conn->query($booking_trends_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify token and check if it's not already used
    if (isset($_POST['form_token']) && 
        isset($_SESSION['form_token']) && 
        $_POST['form_token'] === $_SESSION['form_token'] &&
        !isset($_SESSION['form_submitted'])) {
        
        $_SESSION['form_submitted'] = true; // Mark form as submitted
        
        // Get form data
        $car_name = $_POST['car_name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $car_type = $_POST['car_type'];
        $mileage = $_POST['mileage'];
        $transmission = $_POST['transmission'];
        $seats = $_POST['seats'];
        $luggage = $_POST['luggage'];
        $ac = $_POST['ac'];
        
        // Handle file uploads
        $target_dir = "uploads/cars/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Handle main image
        $main_image = "";
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
            $main_image = $target_dir . time() . "_" . basename($_FILES['main_image']['name']);
            move_uploaded_file($_FILES['main_image']['tmp_name'], $main_image);
        }

        // Handle thumbnail images
        $thumbnails = array();
        if (isset($_FILES['thumbnails'])) {
            foreach ($_FILES['thumbnails']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['thumbnails']['error'][$key] == 0) {
                    $thumbnail_path = $target_dir . time() . "_thumb" . $key . "_" . basename($_FILES['thumbnails']['name'][$key]);
                    if (move_uploaded_file($tmp_name, $thumbnail_path)) {
                        $thumbnails[] = $thumbnail_path;
                    }
                }
            }
        }

        // Handle RC document
        $rc_document = "";
        if (isset($_FILES['rc_document']) && $_FILES['rc_document']['error'] == 0) {
            $rc_document = $target_dir . time() . "_" . basename($_FILES['rc_document']['name']);
            move_uploaded_file($_FILES['rc_document']['tmp_name'], $rc_document);
        }

        // Convert image paths to JSON
        $images = [
            'main_image' => $main_image,
            'thumbnails' => $thumbnails,
            'rc_document' => $rc_document
        ];
        $images_json = json_encode($images);

        // Prepare car features
        $features = [
            'car_type' => $car_type,
            'mileage' => $mileage,
            'transmission' => $transmission,
            'seats' => $seats,
            'luggage_capacity' => $luggage,
            'ac_type' => $ac
        ];
        $features_json = json_encode($features);

        // Insert into database
        $sql = "INSERT INTO cars (car_name, car_description, car_features, price, images, status, owner_id) VALUES (?, ?, ?, ?, ?, 'pending', ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssdsi", 
            $car_name,
            $description,
            $features_json,
            $price,
            $images_json,
            $_SESSION['user_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Car listing added successfully!";
            $_SESSION['message_type'] = "success";
            
            // Generate new token for next submission
            $_SESSION['form_token'] = bin2hex(random_bytes(32));
            unset($_SESSION['form_submitted']);
            
            // Redirect to prevent form resubmission
            header("Location: owner.php?section=cars&status=success");
            exit();
        } else {
            $_SESSION['message'] = "Error: " . $stmt->error;
            $_SESSION['message_type'] = "error";
            header("Location: owner.php?section=cars&status=error");
            exit();
        }
        
        $stmt->close();
    } else {
        // Invalid or reused token
        $_SESSION['message'] = "Invalid form submission or form already submitted.";
        $_SESSION['message_type'] = "error";
        header("Location: owner.php?section=cars&status=invalid");
        exit();
    }
}

// Get message from session if exists
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    // Clear the message after displaying
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Clear form_submitted flag if we're not on a post-submission page
if (!isset($_GET['status'])) {
    unset($_SESSION['form_submitted']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Owner Dashboard - Premium Car Services</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            overflow-x: hidden;
        }

        .container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
            background: linear-gradient(to right, #f5f5f5, #ffffff);
        }

        .sidebar {
            background: #1a1a1a;
            color: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out;
        }

        .sidebar h2 {
            margin-bottom: 30px;
            font-size: 24px;
            color: #ffd700;
            text-align: center;
        }

        .nav-item {
            padding: 15px 20px;
            margin: 10px 0;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .nav-item:hover {
            background: #333;
            transform: translateX(10px);
        }

        .nav-item.active {
            background: #444;
        }

        .main-content {
            padding: 30px;
            background: #f5f5f5;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            animation: slideIn 0.6s ease-in-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(-100%);
            }
            to {
                transform: translateX(0);
            }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, #007bff, #0056b3);
            animation: slideBar 2s infinite;
        }

        @keyframes slideBar {
            0% {
                transform: translateX(-100%);
            }
            50% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(100%);
            }
        }

        .stat-card h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .content-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transform: scale(0.98);
            transition: transform 0.3s ease;
        }

        .content-section:hover {
            transform: scale(1);
        }

        .listing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .listing-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #eee;
            transition: box-shadow 0.3s;
        }

        .listing-card:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .button:hover {
            background: #0056b3;
            transform: translateY(-3px);
        }

        .hidden {
            display: none;
        }

        .add-part-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 500px;
        }

        .add-part-form input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .add-part-form input[type="file"] {
            padding: 5px;
        }

        #parts-section:not(.hidden) {
            animation: fadeIn 0.5s ease-in-out;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }

        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            text-align: center;
        }

        .success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }

        .error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }

        /* New Car Listing Styles */
        #cars-section .container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        #cars-section h2 {
            color: #333;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 2rem;
            position: relative;
        }

        #cars-section h2::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: #007bff;
            margin: 10px auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .feature-item {
            margin-bottom: 0.5rem;
        }

        .file-input {
            display: block;
            margin-top: 0.5rem;
            padding: 0.5rem;
            border: 1px dashed #ddd;
            border-radius: 5px;
            background: #f9f9f9;
            width: 100%;
        }

        .submit-btn {
            background-color: #007bff;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 500;
            transition: background-color 0.3s ease;
            margin-top: 1rem;
        }

        .submit-btn:hover {
            background-color: #0056b3;
        }

        @media (max-width: 600px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            #cars-section .container {
                padding: 1rem;
                margin: 10px;
            }
        }

        .user-info {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: linear-gradient(to right, transparent 50%, #f0f0f0 50%);
            background-size: 200% 100%;
            background-position: left bottom;
            z-index: 9999;
        }

        .user-info:hover {
            background-position: right bottom;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .user-info:hover img {
            transform: scale(1.1);
            border-color: #f5b754;
        }

        .user-info span {
            font-weight: 500;
            color: #333;
            transition: all 0.3s ease;
        }

        .user-info:hover span {
            color: #f5b754;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 99999;
        }

        .user-info:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .dropdown-menu a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background-color: #f5b754;
            opacity: 0.1;
            transition: width 0.3s ease;
        }

        .dropdown-menu a:hover::before {
            width: 100%;
        }

        .dropdown-menu a:hover {
            color: #f5b754;
            padding-left: 20px;
        }

        .dropdown-menu i {
            width: 20px;
            color: #666;
            transition: all 0.3s ease;
        }

        .dropdown-menu a:hover i {
            color: #f5b754;
            transform: scale(1.2);
        }

        @keyframes slideIn {
            0% {
                opacity: 0;
                transform: translateX(-10px);
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .dropdown-menu a {
            animation: slideIn 0.3s ease forwards;
            animation-delay: calc(0.1s * var(--i));
        }

        /* Bookings Section Styles */
        .bookings-wrapper {
            padding: 2rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .bookings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }

        .bookings-title {
            font-size: 2rem;
            color: #2c3e50;
            font-weight: 700;
            position: relative;
        }

        .bookings-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #00d2ff);
            border-radius: 2px;
        }

        .booking-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .filter-button {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 30px;
            background: white;
            color: #495057;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-button.active {
            background: linear-gradient(90deg, #007bff, #00d2ff);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
        }

        .filter-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .bookings-table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.05);
        }

        .bookings-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 1000px;
        }

        .bookings-table th {
            background: #f8f9fa;
            padding: 1.2rem 1rem;
            font-weight: 600;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
        }

        .bookings-table td {
            padding: 1.2rem 1rem;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
            vertical-align: middle;
        }

        .bookings-table tbody tr {
            transition: all 0.3s ease;
        }

        .bookings-table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.003);
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
        }

        .customer-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #007bff;
        }

        .customer-details {
            display: flex;
            flex-direction: column;
        }

        .customer-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .customer-contact {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .booking-dates {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .date-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
        }

        .date-value {
            font-weight: 500;
            color: #2c3e50;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            display: inline-block;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .amount-cell {
            font-weight: 600;
            color: #28a745;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-btn {
            background: #007bff;
            color: white;
        }

        .edit-btn {
            background: #6c757d;
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .bookings-wrapper {
                padding: 1rem;
            }

            .booking-filters {
                flex-wrap: wrap;
            }

            .filter-button {
                width: calc(50% - 0.5rem);
            }
        }

        /* Earnings Analytics Styles */
        .earnings-wrapper {
            padding: 2rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .earnings-header {
            margin-bottom: 2rem;
        }

        .earnings-title {
            font-size: 2rem;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .earnings-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .analytics-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .analytics-card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.1rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .revenue-icon { background: linear-gradient(135deg, #00b09b, #96c93d); }
        .customers-icon { background: linear-gradient(135deg, #5f2c82, #49a09d); }
        .bookings-icon { background: linear-gradient(135deg, #2193b0, #6dd5ed); }
        .conversion-icon { background: linear-gradient(135deg, #ee0979, #ff6a00); }

        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0.5rem 0;
        }

        .card-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            height: 400px;
        }

        canvas {
            max-width: 100% !important;
            max-height: 350px !important;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .chart-legend {
            display: flex;
            gap: 1rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .payment-method {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .method-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #007bff;
        }

        .method-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .method-stats {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .top-performers {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .performers-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .performer-list {
            display: grid;
            gap: 1rem;
        }

        .performer-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .performer-item:hover {
            transform: translateX(5px);
            background: #e9ecef;
        }

        .performer-rank {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .performer-details {
            flex: 1;
        }

        .performer-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .performer-stats {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .performer-revenue {
            text-align: right;
        }

        .revenue-amount {
            font-weight: 600;
            color: #28a745;
        }

        .revenue-count {
            font-size: 0.85rem;
            color: #6c757d;
        }

        #revenueChart, #bookingChart {
            width: 100%;
            height: 300px;
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }

        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background-color: #f39c12;
            color: white;
        }

        .status-captured {
            background-color: #27ae60;
            color: white;
        }

        .status-cancelled {
            background-color: #e74c3c;
            color: white;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .edit-btn {
            padding: 5px 10px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Owner Dashboard</h2>
            <div class="nav-item active" data-section="dashboard">Dashboard</div>
            <div class="nav-item" data-section="cars">Car Listings</div>
            <div class="nav-item" data-section="parts">Spare Parts</div>
            <div class="nav-item" data-section="bookings">Bookings</div>
            <div class="nav-item" data-section="workshop">Workshop</div>
            <div class="nav-item" data-section="earnings">Earnings</div>
            <div class="nav-item" data-section="messages">Messages</div>
        </div>

        <div class="main-content">
            <div id="cars-section" class="hidden">
                <div class="container">
                    <h2>Add a Car</h2>
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    <form action="owner.php" method="POST" enctype="multipart/form-data" id="carForm">
                        <input type="hidden" name="form_token" value="<?php echo $_SESSION['form_token']; ?>">
                        <div class="form-group">
                            <label for="car_name">Car Name:</label>
                            <input type="text" id="car_name" name="car_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="car_features">Features:</label>
                            <div class="features-grid">
                                <div class="feature-item">
                                    <label for="car_type">Car Type:</label>
                                    <input type="text" id="car_type" name="car_type" placeholder="e.g., Sedan, SUV" required>
                                </div>
                                
                                <div class="feature-item">
                                    <label for="mileage">Mileage:</label>
                                    <input type="text" id="mileage" name="mileage" placeholder="e.g., 15 km/l" required>
                                </div>
                                
                                <div class="feature-item">
                                    <label for="transmission">Transmission:</label>
                                    <input type="text" id="transmission" name="transmission" placeholder="e.g., Automatic" required>
                                </div>
                                
                                <div class="feature-item">
                                    <label for="seats">Number of Seats:</label>
                                    <input type="number" id="seats" name="seats" required>
                                </div>
                                
                                <div class="feature-item">
                                    <label for="luggage">Luggage Capacity:</label>
                                    <input type="number" id="luggage" name="luggage" placeholder="Number of bags" required>
                                </div>
                                
                                <div class="feature-item">
                                    <label for="ac">AC Type:</label>
                                    <input type="text" id="ac" name="ac" placeholder="e.g., Climate Control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price per Day (₹):</label>
                            <input type="number" id="price" name="price" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Main Image:</label>
                            <input type="file" name="main_image" accept="image/*" class="file-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Thumbnail Images (Select 3):</label>
                            <input type="file" name="thumbnails[]" accept="image/*" multiple class="file-input" required>
                            <small>Please select exactly 3 thumbnail images</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Vehicle RC Document:</label>
                            <input type="file" name="rc_document" accept="application/pdf" class="file-input" required>
                            <small>Please upload RC document in PDF format only</small>
                        </div>
                        
                        <button type="submit" class="submit-btn" onclick="this.disabled=true; this.form.submit();">Submit Car Details</button>
                    </form>
                </div>
            </div>

            <div id="bookings-section" class="hidden">
                <div class="bookings-wrapper">
                    <div class="bookings-header">
                        <h2 class="bookings-title">Booking Management</h2>
                        <div class="booking-filters">
                            <button class="filter-button <?php echo $filter === 'all' ? 'active' : ''; ?>" data-filter="all">All Bookings</button>
                            <button class="filter-button <?php echo $filter === 'completed' ? 'active' : ''; ?>" data-filter="completed">Completed</button>
                            <button class="filter-button <?php echo $filter === 'cancelled' ? 'active' : ''; ?>" data-filter="cancelled">Cancelled</button>
                        </div>
                    </div>

                    <div class="bookings-table-container">
                        <table class="bookings-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Car Details</th>
                                    <th>Booking Period</th>
                                    <th>Payment Info</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($all_bookings_result && $all_bookings_result->num_rows > 0) {
                                    while($booking = $all_bookings_result->fetch_assoc()): 
                                        $car_features = json_decode($booking['car_features'], true);
                                        $first_letter = strtoupper(substr($booking['user_name'], 0, 1));
                                ?>
                                    <tr>
                                        <td>
                                            <div class="customer-info">
                                                <div class="customer-avatar"><?php echo $first_letter; ?></div>
                                                <div class="customer-details">
                                                    <span class="customer-name"><?php echo htmlspecialchars($booking['user_name']); ?></span>
                                                    <span class="customer-contact">
                                                        <?php echo htmlspecialchars($booking['user_email']); ?><br>
                                                        <?php echo htmlspecialchars($booking['user_phone']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['car_name']); ?></strong><br>
                                            <small><?php echo $car_features['car_type'] ?? 'N/A'; ?></small>
                                        </td>
                                        <td>
                                            <div class="booking-dates">
                                                <div>
                                                    <span class="date-label">Start:</span>
                                                    <span class="date-value"><?php echo date('d M Y', strtotime($booking['start_date'])); ?></span>
                                                </div>
                                                <div>
                                                    <span class="date-label">End:</span>
                                                    <span class="date-value"><?php echo date('d M Y', strtotime($booking['end_date'])); ?></span>
                                                </div>
                                                <div>
                                                    <span class="date-label">Pickup:</span>
                                                    <span class="date-value"><?php echo date('h:i A', strtotime($booking['pickup_time'])); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="amount-cell">₹<?php 
                                                if ($booking['payment_method'] === 'cash') {
                                                    echo number_format($booking['total_price'], 2);
                                                } else {
                                                    echo number_format($booking['amount'], 2);
                                                }
                                            ?></div>
                                            <small><?php echo htmlspecialchars($booking['payment_method']); ?></small><br>
                                            <small class="text-muted"><?php echo $booking['transaction_id'] ?? 'N/A'; ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($booking['payment_status'] ?? 'pending'); ?>">
                                                <?php echo htmlspecialchars($booking['payment_status'] ?? 'Pending'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn view-btn" 
                                                    data-id="<?php echo $booking['booking_id']; ?>"
                                                    data-location="<?php echo htmlspecialchars($booking['pickup_location']); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if (strtolower($booking['payment_status'] ?? 'pending') === 'pending'): ?>
                                                <button class="action-btn edit-btn" 
                                                    data-id="<?php echo $booking['booking_id']; ?>"
                                                    data-amount="<?php echo $booking['total_price']; ?>"
                                                    data-status="<?php echo $booking['payment_status']; ?>">
                                                    Update Status
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile;
                                } else {
                                ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 2rem;">
                                            <div style="color: #6c757d;">
                                                <i class="fas fa-inbox fa-3x"></i>
                                                <p style="margin-top: 1rem;">No bookings found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="parts-section" class="hidden">
                <div class="header">
                    <h1>Spare Parts Management</h1>
                </div>

                <div class="parts-form content-section">
                    <h2>Add New Spare Part</h2>
                    <form id="addPartForm" class="add-part-form">
                        <input
                            type="text"
                            id="partName"
                            placeholder="Part Name"
                            required
                        />
                        <input
                            type="number"
                            id="partPrice"
                            placeholder="Price"
                            required
                        />
                        <input type="file" id="partImage" accept="image/*" required />
                        <button type="submit" class="button">Post Part</button>
                    </form>
                </div>

                <div class="content-section">
                    <h2>Parts Inventory</h2>
                    <div id="partsGrid" class="listing-grid">
                        <!-- Parts will be displayed here -->
                    </div>
                </div>
            </div>

            <div id="dashboard-section">
                <div class="header">
                    <h1>Dashboard Overview</h1>
                    <div class="user-info" id="profileDropdown">
                        <span>Admin User</span>
                        <img src="assets/profile.jpg" alt="Admin" />
                        <div class="dropdown-menu">
                            <a href="#" style="--i:1"><i class="fas fa-user"></i>My Profile</a>
                            <a href="#" style="--i:2"><i class="fas fa-cog"></i>Account Settings</a>
                            <a href="#" style="--i:3"><i class="fas fa-bell"></i>Notifications</a>
                            <a href="logout.php" style="--i:4"><i class="fas fa-sign-out-alt"></i>Logout</a>
                        </div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Active Listings</h3>
                        <div class="value"><?php echo $active_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Bookings</h3>
                        <div class="value"><?php echo $booking_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Earnings</h3>
                        <div class="value">₹<?php echo number_format($total_earnings, 2); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Reviews</h3>
                        <div class="value">128</div>
                    </div>
                </div>

                <div class="content-section">
                    <h2>Recent Listings</h2>
                    <div class="listing-grid">
                        <div class="listing-card">
                            <h3>Mercedes-Benz S-Class</h3>
                            <p>Premium Sedan | $200/day</p>
                            <p>Status: Available</p>
                        </div>
                        <div class="listing-card">
                            <h3>BMW 7 Series Parts</h3>
                            <p>Original Brake Pads | $350</p>
                            <p>Status: In Stock</p>
                        </div>
                        <div class="listing-card">
                            <h3>Luxury Workshop Services</h3>
                            <p>Premium Maintenance | Various</p>
                            <p>Status: Available</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="earnings-section" class="hidden">
                <div class="earnings-wrapper">
                    <div class="earnings-header">
                        <h2 class="earnings-title">Financial Analytics</h2>
                        <p class="earnings-subtitle">Comprehensive overview of your business performance</p>
                    </div>

                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <div class="card-title">Total Revenue</div>
                                <div class="card-icon revenue-icon">
                                    <i class="fas fa-rupee-sign"></i>
                                </div>
                            </div>
                            <div class="card-value">₹<?php echo number_format($total_earnings, 2); ?></div>
                            <?php 
                            $monthly_growth = 0;
                            if ($payment_analytics && $payment_analytics->num_rows > 1) {
                                $months = array();
                                while ($row = $payment_analytics->fetch_assoc()) {
                                    $months[$row['month']] = $row['monthly_revenue'];
                                }
                                $current_month = array_key_first($months);
                                $prev_month = array_key_first(array_slice($months, 1, 1, true));
                                if ($months[$prev_month] > 0) {
                                    $monthly_growth = (($months[$current_month] - $months[$prev_month]) / $months[$prev_month]) * 100;
                                }
                            }
                            ?>
                            <div class="card-trend <?php echo $monthly_growth < 0 ? 'trend-down' : 'trend-up'; ?>">
                                <i class="fas fa-<?php echo $monthly_growth < 0 ? 'arrow-down' : 'arrow-up'; ?>"></i>
                                <span><?php echo abs(round($monthly_growth, 1)); ?>% from last month</span>
                            </div>
                        </div>

                        <?php if ($customer_insights && $row = $customer_insights->fetch_assoc()): ?>
                        <div class="analytics-card">
                            <div class="card-header">
                                <div class="card-title">Customer Base</div>
                                <div class="card-icon customers-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="card-value"><?php echo $row['total_customers']; ?></div>
                            <div class="card-trend trend-up">
                                <i class="fas fa-user-plus"></i>
                                <span><?php echo $row['new_customers']; ?> new today</span>
                            </div>
                        </div>

                        <div class="analytics-card">
                            <div class="card-header">
                                <div class="card-title">Average Spend</div>
                                <div class="card-icon conversion-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                            <div class="card-value">₹<?php echo number_format($row['avg_customer_spend'], 2); ?></div>
                            <div class="card-trend">
                                <i class="fas fa-shopping-cart"></i>
                                <span><?php echo round($row['bookings_per_customer'], 1); ?> bookings/customer</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="chart-grid">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title">Revenue Trends</h3>
                                <div class="chart-legend">
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #007bff"></div>
                                        <span>Monthly Revenue</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #28a745"></div>
                                        <span>Completed Payments</span>
                                    </div>
                                </div>
                            </div>
                            <canvas id="revenueChart"></canvas>
                        </div>

                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title">Booking Analysis</h3>
                            </div>
                            <canvas id="bookingChart"></canvas>
                        </div>
                    </div>

                    <div class="payment-methods">
                        <?php if ($payment_methods && $payment_methods->num_rows > 0): 
                            while ($method = $payment_methods->fetch_assoc()): 
                                $icon_class = 'fa-credit-card';
                                if (stripos($method['payment_method'], 'upi') !== false) {
                                    $icon_class = 'fa-mobile-alt';
                                } elseif (stripos($method['payment_method'], 'net') !== false) {
                                    $icon_class = 'fa-university';
                                }
                        ?>
                            <div class="payment-method">
                                <div class="method-icon">
                                    <i class="fas <?php echo $icon_class; ?>"></i>
                                </div>
                                <h4 class="method-name"><?php echo ucfirst($method['payment_method']); ?></h4>
                                <div class="method-stats">
                                    <div class="amount">₹<?php echo number_format($method['total_amount'], 2); ?></div>
                                    <div class="count"><?php echo $method['count']; ?> transactions</div>
                                    <div class="average">Avg. ₹<?php echo number_format($method['avg_transaction'], 2); ?></div>
                                </div>
                            </div>
                        <?php endwhile; endif; ?>
                    </div>

                    <div class="top-performers">
                        <h3 class="performers-title">Top Performing Cars</h3>
                        <div class="performer-list">
                            <?php if ($top_cars && $top_cars->num_rows > 0):
                                $rank = 1;
                                while ($car = $top_cars->fetch_assoc()): 
                            ?>
                                <div class="performer-item">
                                    <div class="performer-rank"><?php echo $rank++; ?></div>
                                    <div class="performer-details">
                                        <div class="performer-name"><?php echo htmlspecialchars($car['car_name']); ?></div>
                                        <div class="performer-stats"><?php echo $car['total_bookings']; ?> bookings</div>
                                    </div>
                                    <div class="performer-revenue">
                                        <div class="revenue-amount">₹<?php echo number_format($car['total_revenue'], 2); ?></div>
                                        <div class="revenue-count">Avg. ₹<?php echo number_format($car['avg_booking_value'], 2); ?>/booking</div>
                                    </div>
                                </div>
                            <?php endwhile; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Location Popup Modal -->
    <div id="locationModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Pickup Location</h3>
            <div id="locationDetails"></div>
        </div>
    </div>

    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #666;
        }

        .close-modal:hover {
            color: #000;
        }

        #locationDetails {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            line-height: 1.5;
        }
    </style>

    <script>
        // Get modal elements
        const modal = document.getElementById('locationModal');
        const locationDetails = document.getElementById('locationDetails');
        const closeBtn = document.querySelector('.close-modal');

        // Add click event listeners to all view buttons
        document.querySelectorAll('.view-btn').forEach(button => {
            button.addEventListener('click', function() {
                const location = this.getAttribute('data-location');
                locationDetails.textContent = location;
                modal.style.display = 'block';
            });
        });

        // Close modal when clicking the close button
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
    <script>
        document.querySelectorAll(".nav-item").forEach((item) => {
            item.addEventListener("click", () => {
                document.querySelectorAll(".nav-item").forEach((navItem) => {
                    navItem.classList.remove("active");
                });
                item.classList.add("active");
                const section = item.dataset.section;

                // Hide all sections
                document.querySelectorAll("#dashboard-section, #parts-section, #cars-section, #bookings-section, #earnings-section").forEach((section) => {
                    section.classList.add("hidden");
                });

                // Show selected section
                if (section === "parts") {
                    document.querySelector("#parts-section").classList.remove("hidden");
                } else if (section === "dashboard") {
                    document.querySelector("#dashboard-section").classList.remove("hidden");
                } else if (section === "cars") {
                    document.querySelector("#cars-section").classList.remove("hidden");
                } else if (section === "bookings") {
                    document.querySelector("#bookings-section").classList.remove("hidden");
                } else if (section === "earnings") {
                    document.querySelector("#earnings-section").classList.remove("hidden");
                }
            });
        });

        // Profile dropdown functionality
        const profileDropdown = document.getElementById('profileDropdown');
        const dropdownMenu = profileDropdown.querySelector('.dropdown-menu');

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!profileDropdown.contains(e.target)) {
                dropdownMenu.style.display = 'none';
            }
        });

        // Toggle dropdown on click (for mobile devices)
        profileDropdown.addEventListener('click', (e) => {
            const currentDisplay = dropdownMenu.style.display;
            dropdownMenu.style.display = currentDisplay === 'block' ? 'none' : 'block';
            e.stopPropagation();
        });

        function updateStats() {
            const stats = {
                listings: Math.floor(Math.random() * 20) + 10,
                bookings: Math.floor(Math.random() * 10) + 1,
                earnings: Math.floor(Math.random() * 10000) + 5000,
                reviews: Math.floor(Math.random() * 50) + 100,
            };

            document
                .querySelectorAll(".stat-card .value")
                .forEach((element, index) => {
                    const keys = Object.keys(stats);
                    const value = stats[keys[index]];
                    element.textContent = index === 2 ? `$${value}` : value;
                });
        }

        setInterval(updateStats, 30000);

        // Add form handling
        document
            .getElementById("addPartForm")
            .addEventListener("submit", function (e) {
                e.preventDefault();

                const name = document.getElementById("partName").value;
                const price = document.getElementById("partPrice").value;
                const imageFile = document.getElementById("partImage").files[0];

                const reader = new FileReader();
                reader.onload = function (e) {
                    const partCard = `
                    <div class="listing-card">
                        <img src="${e.target.result}" alt="${name}" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
                        <h3>${name}</h3>
                        <p>Price: $${price}</p>
                        <p>Status: In Stock</p>
                    </div>
                  `;

                    document
                        .getElementById("partsGrid")
                        .insertAdjacentHTML("afterbegin", partCard);
                };

                if (imageFile) {
                    reader.readAsDataURL(imageFile);
                }

                // Reset form
                e.target.reset();
            });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        <?php
        $chart_data = array();
        if ($payment_analytics && $payment_analytics->num_rows > 0) {
            $payment_analytics->data_seek(0);
            while ($row = $payment_analytics->fetch_assoc()) {
                $month = date('M Y', strtotime($row['month'] . '-01'));
                if (!isset($chart_data[$month])) {
                    $chart_data[$month] = array(
                        'revenue' => 0,
                        'completed' => 0
                    );
                }
                if ($row['status'] == 'completed') {
                    $chart_data[$month]['completed'] += $row['monthly_revenue'];
                }
                $chart_data[$month]['revenue'] += $row['monthly_revenue'];
            }
        }
        ?>
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($chart_data)); ?>,
                datasets: [{
                    label: 'Monthly Revenue',
                    data: <?php echo json_encode(array_column($chart_data, 'revenue')); ?>,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Completed Payments',
                    data: <?php echo json_encode(array_column($chart_data, 'completed')); ?>,
                    borderColor: '#2196F3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Booking Analysis Chart
        const bookingCtx = document.getElementById('bookingChart').getContext('2d');
        <?php
        $booking_data = array();
        if ($booking_trends && $booking_trends->num_rows > 0) {
            while ($row = $booking_trends->fetch_assoc()) {
                $month = date('M Y', strtotime($row['month'] . '-01'));
                $booking_data[$month] = array(
                    'confirmed' => $row['confirmed_bookings'],
                    'cancelled' => $row['cancelled_bookings']
                );
            }
        }
        ?>
        new Chart(bookingCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($booking_data)); ?>,
                datasets: [{
                    label: 'Confirmed',
                    data: <?php echo json_encode(array_column($booking_data, 'confirmed')); ?>,
                    backgroundColor: '#28a745'
                }, {
                    label: 'Cancelled',
                    data: <?php echo json_encode(array_column($booking_data, 'cancelled')); ?>,
                    backgroundColor: '#dc3545'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    </script>
    <script>
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
    <script>
    // Disable form resubmission on refresh
    window.onload = function() {
        if (window.performance && window.performance.navigation.type === window.performance.navigation.TYPE_RELOAD) {
            document.getElementById('carForm').reset();
        }
    };

    // Disable submit button after first click
    document.getElementById('carForm').addEventListener('submit', function(e) {
        var submitButton = this.querySelector('button[type="submit"]');
        if (submitButton.disabled) {
            e.preventDefault();
            return false;
        }
        submitButton.disabled = true;
    });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers to all edit buttons
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const bookingId = this.getAttribute('data-id');
                    const amount = this.getAttribute('data-amount');
                    const currentStatus = this.getAttribute('data-status');
                    openEditModal(bookingId, amount, currentStatus);
                });
            });

            function openEditModal(bookingId, amount, currentStatus) {
                const modalHTML = `
                    <div class="modal-overlay" id="editModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>Update Payment Status</h3>
                                <button class="close-modal" onclick="closeModal()">&times;</button>
                            </div>
                            <div class="modal-body">
                                <p><strong>Booking ID:</strong> #${bookingId}</p>
                                <p><strong>Amount:</strong> ₹${amount}</p>
                                <p><strong>Current Status:</strong> <span class="status-badge status-${currentStatus.toLowerCase()}">${currentStatus}</span></p>
                                <div class="form-group" style="margin-top: 20px;">
                                    <label for="newStatus">Update Status:</label>
                                    <select id="newStatus" class="form-control" style="width: 100%; padding: 8px; margin-top: 5px;">
                                        <option value="pending" ${currentStatus === 'pending' ? 'selected' : ''}>Pending</option>
                                        <option value="captured" ${currentStatus === 'captured' ? 'selected' : ''}>Captured</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn-secondary" onclick="closeModal()">Cancel</button>
                                <button class="btn-primary" onclick="updateStatus(${bookingId}, ${amount})">Update Status</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHTML);
            }

            window.closeModal = function() {
                const modal = document.getElementById('editModal');
                if (modal) {
                    modal.remove();
                }
            };

            window.updateStatus = function(bookingId, amount) {
                const newStatus = document.getElementById('newStatus').value;
                
                fetch('update_payment_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `booking_id=${bookingId}&status=${newStatus}&amount=${amount}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Payment status updated successfully!');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the status.');
                });
            };
        });
    </script>
    <script>
        // Booking filters
        document.querySelectorAll('.filter-button').forEach(button => {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                window.location.href = `owner.php?filter=${filter}#bookings-section`;
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
