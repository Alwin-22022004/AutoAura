<?php
session_start();
include 'db_connect.php';
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=rent-car.php" . (isset($_GET['id']) ? "?id=" . $_GET['id'] : ""));
    exit();
}

// Get car details
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid car selection.");
}

$car_id = intval($_GET['id']);
$sql = "SELECT * FROM cars WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Car not found.");
}

$car = $result->fetch_assoc();
$features = json_decode($car['car_features'], true);
$images = json_decode($car['images'], true);

// Get booked dates for this car
$booked_dates_query = "SELECT start_date, end_date FROM bookings 
                      WHERE car_id = ? AND status != 'cancelled'";
$booked_stmt = $conn->prepare($booked_dates_query);
$booked_stmt->bind_param("i", $car_id);
$booked_stmt->execute();
$booked_result = $booked_stmt->get_result();
        
$booked_dates = [];
while($booking = $booked_result->fetch_assoc()) {
    $start = new DateTime($booking['start_date']);
    $end = new DateTime($booking['end_date']);
    $interval = new DateInterval('P1D');
    $date_range = new DatePeriod($start, $interval, $end->modify('+1 day'));
    
    foreach($date_range as $date) {
        $booked_dates[] = $date->format('Y-m-d');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required_fields = ['start_date', 'end_date', 'pickup_time', 'pickup_location', 'center', 'payment_method'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $error = "Please fill in all required fields: " . implode(', ', $missing_fields);
    } else {
        try {
            // Begin transaction
            $conn->begin_transaction();

            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $pickup_time = $_POST['pickup_time'];
            $pickup_location = $_POST['pickup_location'];
            $center = $_POST['center'];
            $payment_method = $_POST['payment_method'];
            $user_id = $_SESSION['user_id'];
            
            // Validate dates
            $date1 = new DateTime($start_date);
            $date2 = new DateTime($end_date);
            
            if ($date1 > $date2) {
                throw new Exception("Return date must be after pickup date");
            }
            
            // Calculate rental duration and costs
            $interval = $date1->diff($date2);
            $days = $interval->days + 1;
            
            $base_price = $car['price'] * $days;
            $driver_charges = 800 * $days; // ₹800 per day
            $insurance = 500; // Fixed insurance cost
            $total_price = $base_price + $driver_charges + $insurance;
            
            // Check if car is available for the selected dates
            $availability_check = "SELECT booking_id FROM bookings 
                                 WHERE car_id = ? AND 
                                 ((start_date BETWEEN ? AND ?) OR 
                                  (end_date BETWEEN ? AND ?) OR
                                  (start_date <= ? AND end_date >= ?)) AND
                                 status != 'cancelled'";
            
            $check_stmt = $conn->prepare($availability_check);
            $check_stmt->bind_param("issssss", 
                $car_id, $start_date, $end_date, 
                $start_date, $end_date, 
                $start_date, $end_date
            );
            $check_stmt->execute();
            $availability_result = $check_stmt->get_result();
            
            if ($availability_result->num_rows > 0) {
                throw new Exception("Car is not available for the selected dates");
            }
            
            // Insert booking
            $sql = "INSERT INTO bookings (
                car_id, user_id, start_date, end_date, 
                pickup_time, pickup_location, center, 
                payment_method, total_price, driver_charges, 
                insurance, status, payment_status, 
                booking_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'pending', NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iissssssiii", 
                $car_id, $user_id, $start_date, $end_date,
                $pickup_time, $pickup_location, $center,
                $payment_method, $total_price, $driver_charges,
                $insurance
            );
            
            if ($stmt->execute()) {
                $booking_id = $stmt->insert_id;
                $_SESSION['booking_id'] = $booking_id;
                
                // Commit transaction
                $conn->commit();
                
                // If payment method is Razorpay, show payment button
                if ($payment_method === 'razorpay') {
                    // The form will be submitted via AJAX, so we don't redirect
                    $show_payment = true;
                } else {
                    // For other payment methods, redirect to confirmation
                    header("Location: booking-confirmation.php?id=" . $booking_id);
                    exit();
                }
            } else {
                throw new Exception("Error creating booking");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?php echo htmlspecialchars($car['car_name']); ?> - LUXE DRIVE</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_orange.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background: #ffffff;
            position: relative;
            min-height: 100vh;
            color: #333;
            padding: 20px;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at top left, rgba(245, 183, 84, 0.15) 0%, transparent 50%),
                radial-gradient(circle at bottom right, rgba(245, 183, 84, 0.15) 0%, transparent 50%);
            pointer-events: none;
            z-index: 1;
        }

        .header, .container {
            position: relative;
            z-index: 2;
        }

        .header {
            background: #fff;
            padding: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border-radius: 10px;
        }

        .logo img {
            height: 50px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
            background: #fff;
            border-radius: 15px;
            box-shadow: 
                0 10px 30px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .car-info {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .car-image {
            width: 300px;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .car-details h1 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 10px;
        }

        .price {
            font-size: 1.5rem;
            color: #f5b754;
            margin-bottom: 20px;
        }

        .booking-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #666;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus, select:focus {
            border-color: #f5b754;
            outline: none;
            box-shadow: 0 0 10px rgba(245, 183, 84, 0.3);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .payment-method {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            border-color: #f5b754;
            transform: translateY(-2px);
        }

        .payment-method.selected {
            border-color: #f5b754;
            background: rgba(245, 183, 84, 0.1);
        }

        .payment-method i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #f5b754;
        }

        .summary {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
        }

        .summary h2 {
            color: #f5b754;
            margin-bottom: 1rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #ddd;
        }

        .submit-btn {
            background: #f5b754;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 2rem;
            transition: all 0.3s ease;
            grid-column: 1 / -1;
        }

        .submit-btn:hover {
            background: #e4a643;
            transform: translateY(-2px);
        }

        .error {
            color: #ff4444;
            margin-bottom: 20px;
            grid-column: 1 / -1;
        }

        #razorpay-button {
            background-color: #3399cc;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            display: none;
        }
        #razorpay-button:hover {
            background-color: #2980b9;
        }

        @media (max-width: 768px) {
            .booking-form {
                grid-template-columns: 1fr;
            }

            .car-info {
                flex-direction: column;
            }

            .car-image {
                width: 100%;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }
        }

        .location-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 550px;
            background: #fff;
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        .location-popup h2 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
            position: relative;
        }

        .location-popup h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: #f5b754;
            border-radius: 2px;
        }

        .cities-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .city-option {
            padding: 10px;
            border: 2px solid #eee;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .city-option:hover {
            border-color: #f5b754;
            transform: translateY(-2px);
        }

        .city-option.selected {
            background: rgba(245, 183, 84, 0.1);
            border-color: #f5b754;
        }

        .city-option img {
            width: 30px;
            height: 30px;
            margin-bottom: 5px;
            transition: transform 0.3s ease;
        }

        .city-option:hover img {
            transform: scale(1.1);
        }

        .city-option div {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
            font-weight: 500;
        }

        .locations-list {
            background: #f9f9f9;
            border-radius: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
        }

        .location-option {
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            margin-bottom: 5px;
        }

        .location-option:hover {
            background: rgba(245, 183, 84, 0.1);
            transform: translateX(5px);
        }

        .location-option.selected {
            background: rgba(245, 183, 84, 0.2);
            transform: translateX(5px);
        }

        .location-option i {
            color: #f5b754;
            width: 20px;
            transition: transform 0.2s ease;
        }

        .location-option:hover i {
            transform: scale(1.2);
        }

        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .close-popup {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.5rem;
            color: #666;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-popup:hover {
            color: #333;
            background: rgba(0, 0, 0, 0.05);
            transform: rotate(90deg);
        }

        .next-btn {
            background: #f5b754;
            color: white;
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .next-btn:hover {
            background: #e4a643;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 183, 84, 0.2);
        }

        .next-btn:active {
            transform: translateY(0);
        }

        #pickup_location {
            cursor: pointer;
        }

        .main-location-content,
        .home-delivery-content {
            background-color: #f5f5e6;
            border-radius: 12px;
            width: 100%;
        }

        .modal-header {
            padding: 10px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .back-button {
            background: none;
            border: none;
            cursor: pointer;
            margin-right: 10px;
            color: #8b8b8b;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .modal-body {
            padding: 10px 15px;
        }

        .search-location-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1a1a1a;
        }

        .search-input-container {
            position: relative;
            margin-bottom: 15px;
        }

        .search-input {
            width: 100%;
            padding: 10px 20px 10px 40px;
            border-radius: 999px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            outline: none;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #f2ca00;
        }

        .location-options .location-option {
            display: flex;
            align-items: center;
            padding: 10px 0;
            cursor: pointer;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .location-icon {
            margin-right: 12px;
            color: #666;
            display: flex;
        }

        .location-text {
            font-size: 16px;
            color: #555;
        }

        .map-container {
            height: 300px;
            width: 100%;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .location-button {
            background-color: #f5b754;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .location-button:hover {
            background-color: #e4a643;
        }

        .controls {
            margin: 10px 0;
            display: flex;
            align-items: center;
        }

        .radius-select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .error {
            color: #ff4444;
            margin: 10px 0;
            display: none;
        }

        #map-interface {
            padding: 15px;
        }

        #map-picker {
            padding: 15px;
        }

        #map {
            height: 300px;
            width: 100%;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .confirm-btn {
            background: #f5b754;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }

        .confirm-btn:hover {
            background: #e4a643;
        }

        #info, #address {
            margin: 10px 0;
            font-size: 14px;
            color: #666;
        }

        .booking-summary {
            background: #ffffff;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .booking-summary h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.2em;
        }

        .summary-details {
            display: grid;
            gap: 10px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-item .label {
            font-weight: 600;
            color: #555;
        }

        .detail-item .value {
            color: #333;
        }

        .date-picker-container {
            position: relative;
            margin-bottom: 15px;
        }

        .date-picker-container input {
            padding-right: 40px;
            cursor: pointer;
            background-color: white;
        }

        .date-picker-container i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #f5b754;
            pointer-events: none;
        }

        .flatpickr-calendar {
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            border-radius: 10px;
        }

        .flatpickr-day.flatpickr-disabled {
            background-color: #ffebee !important;
            color: #ff5252 !important;
            text-decoration: line-through;
        }
        .flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay) {
            background-color: #e8f5e9 !important;
            color: #4caf50 !important;
        }
        .flatpickr-day.selected {
            background-color: #f5b754 !important;
            color: white !important;
        }
        .flatpickr-months .flatpickr-month {
         border-radius: 5px 5px 0 0;
         background:  #f5b754;
         color: #fff;
         fill: #fff;
         height: 34px;
         line-height: 1;
         text-align: center;
         position: relative;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months {
        appearance: menulist;
        background:  #f5b754;
        border: none;
        border-radius: 0;
        box-sizing: border-box;
        color: inherit;
        cursor: pointer;
        font-size: inherit;
        font-family: inherit;
        font-weight: 300;
        height: auto;
        line-height: inherit;
        margin: -1px 0 0 0;
        outline: none;
        padding: 0 0 0 0.5ch;
        position: relative;
        vertical-align: initial;
        webkit-box-sizing: border-box;
        webkit-appearance: menulist;
        moz-appearance: menulist;
        width: auto;
        }
        .flatpickr-weekdays {
        background:  #f5b754;
        text-align: center;
        overflow: hidden;
        width: 100%;
        display: -webkit-box;
        display: -webkit-flex;
        display: -ms-flexbox;
        display: flex
webkit-box-align: center;
        webkit-align-items: center;
        ms-flex-align: center;
        align-items: center;
        height: 28px;
        }
        span.flatpickr-weekday {
        cursor: default;
        font-size: 90%;
        background:  #f5b754;
        color: rgba(0, 0, 0, 0.54);
        line-height: 1;
        margin: 0;
        text-align: center;
        display: block;
        webkit-box-flex: 1;
        webkit-flex: 1;
        ms-flex: 1;
        flex: 1;
        font-weight: bolder;
    }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png" alt="LUXE DRIVE">
        </div>
    </div>

    <div class="container">
        <div class="car-info">
            <img src="<?php echo htmlspecialchars($images['main_image']); ?>" alt="<?php echo htmlspecialchars($car['car_name']); ?>" class="car-image">
            <div class="car-details">
                <h1><?php echo htmlspecialchars($car['car_name']); ?></h1>
                <div class="price">₹<?php echo number_format($car['price']); ?> / day</div>
                <div class="features">
                    <?php foreach ($features as $key => $value): ?>
                        <?php if (!is_array($value)): ?>
                            <div class="feature">
                                <i class="fas <?php 
                                    echo match($key) {
                                        'seats' => 'fa-users',
                                        'transmission' => 'fa-cog',
                                        'mileage' => 'fa-tachometer-alt',
                                        'ac_type' => 'fa-snowflake',
                                        'car_type' => 'fa-car',
                                        'fuel_type' => 'fa-gas-pump',
                                        default => 'fa-info-circle'
                                    };
                                ?>"></i>
                                <span><?php echo ucwords(str_replace('_', ' ', $key)); ?>: <?php echo htmlspecialchars($value); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form class="booking-form" method="POST" id="booking-form">
            <div class="left-section">
                <div class="form-group">
                    <label for="start_date">Pickup Date</label>
                    <div class="date-picker-container">
                        <input type="text" id="start_date" name="start_date" placeholder="Select pickup date" required>
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="pickup_time">Pickup Time</label>
                    <input type="time" id="pickup_time" name="pickup_time" required>
                </div>

                <div class="form-group">
                    <label for="end_date">Return Date</label>
                    <div class="date-picker-container">
                        <input type="text" id="end_date" name="end_date" placeholder="Select return date" required>
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="pickup_location">Choose your pick location</label>
                    <input type="text" id="pickup_location" name="pickup_location" placeholder="Choose pickup location" required readonly>
                </div>
            </div>

            <div class="right-section">
                <div class="form-group">
                    <label for="center">Additional services</label>
                    <select id="center" name="center" required>
                        <option value="">Select a service</option>
                        <option value="Nothing">Nothing</option>
                        <option value="Flower Decorations"> Flower Decorations</option>
                        <option value="Power Bank & Charging Kit">Power Bank & Charging Kit</option>
                        <option value="Luxury Refreshments">Luxury Refreshments</option>
                    </select>
                </div>

                <div class="form-group payment-method">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select Payment Method</option>
                        <option value="razorpay">Pay Online (Razorpay)</option>
                        <option value="cash">Pay at Pickup</option>
                    </select>
                </div>

                <div class="summary">
                    <h2>Booking Summary</h2>
                    <div class="summary-item">
                        <span>Base Rate (<?php echo htmlspecialchars($car['car_name']); ?>)</span>
                        <span>₹<?php echo number_format($car['price']); ?> / day</span>
                    </div>
                    <div class="summary-item">
                        <span>Driver Charges</span>
                        <span>₹800/day</span>
                    </div>
                    <div class="summary-item">
                        <span>Insurance</span>
                        <span>₹500</span>
                    </div>
                    <div class="summary-item">
                        <strong>Total Amount</strong>
                        <strong id="total_cost">₹0</strong>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn">Proceed to Payment</button>
        </form>
        
        <?php if (isset($show_payment) && $show_payment): ?>
        <div class="booking-summary">
            <h3>Booking Details</h3>
            <div class="summary-details">
                <div class="detail-item">
                    <span class="label">Pickup Date:</span>
                    <span class="value"><?php echo htmlspecialchars($_POST['start_date']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Pickup Time:</span>
                    <span class="value"><?php echo htmlspecialchars($_POST['pickup_time']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Return Date:</span>
                    <span class="value"><?php echo htmlspecialchars($_POST['end_date']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Pickup Location:</span>
                    <span class="value"><?php echo htmlspecialchars($_POST['pickup_location']); ?></span>
                </div>
                <?php if (!empty($_POST['additional_services'])): ?>
                <div class="detail-item">
                    <span class="label">Additional Services:</span>
                    <span class="value">
                        <?php 
                        $services = is_array($_POST['additional_services']) ? 
                            implode(', ', array_map('htmlspecialchars', $_POST['additional_services'])) : 
                            htmlspecialchars($_POST['additional_services']);
                        echo $services;
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <span class="label">Payment Method:</span>
                    <span class="value"><?php echo htmlspecialchars($_POST['payment_method']); ?></span>
                </div>
            </div>
        </div>
        <button id="razorpay-button" style="display: block;">Pay Now</button>
        <?php endif; ?>
    </div>

    <div class="popup-overlay" role="presentation"></div>
    <div class="location-popup" role="dialog" aria-labelledby="location-title">
        <!-- Main location selection content -->
        <div class="main-location-content">
            <button class="close-popup" aria-label="Close location picker"><i class="fas fa-times"></i></button>
            <h2 id="location-title">Select Pickup Location</h2>
            <div class="cities-grid" role="listbox" aria-label="Available cities">
                <div class="city-option" data-city="bengaluru" role="option" tabindex="0" aria-selected="false">
                    <img src="assets/images/cities/bengaluru.svg" alt="Bengaluru city icon">
                    <div>Bengaluru</div>
                </div>
                <div class="city-option" data-city="calicut" role="option" tabindex="0" aria-selected="false">
                    <img src="assets/images/cities/calicut.svg" alt="Calicut city icon">
                    <div>Calicut</div>
                </div>
                <div class="city-option" data-city="chennai" role="option" tabindex="0" aria-selected="false">
                    <img src="assets/images/cities/chennai.svg" alt="Chennai city icon">
                    <div>Chennai</div>
                </div>
                <div class="city-option" data-city="cochin" role="option" tabindex="0" aria-selected="false">
                    <img src="assets/images/cities/cochin.svg" alt="Cochin city icon">
                    <div>Cochin</div>
                </div>
                <div class="city-option" data-city="hyderabad" role="option" tabindex="0" aria-selected="false">
                    <img src="assets/images/cities/hyderabad.svg" alt="Hyderabad city icon">
                    <div>Hyderabad</div>
                </div>
                <div class="city-option" data-city="trivandrum" role="option" tabindex="0" aria-selected="false">
                    <img src="assets/images/cities/trivandrum.svg" alt="Trivandrum city icon">
                    <div>Trivandrum</div>
                </div>
            </div>
            <div class="locations-list" role="listbox" aria-label="Available pickup locations"></div>
            <button class="next-btn">Next</button>
        </div>

        <!-- Home delivery content -->
        <div class="home-delivery-content" style="display: none;">
            <div class="modal-header">
                <button class="back-button">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                </button>
                <h1 class="modal-title">Home Delivery</h1>
            </div>
            
            <div class="modal-body">
                <div class="location-options">
                    <div class="location-option" id="current-location">
                        <span class="location-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <circle cx="12" cy="12" r="1"></circle>
                                <line x1="12" y1="2" x2="12" y2="4"></line>
                                <line x1="12" y1="20" x2="12" y2="22"></line>
                                <line x1="2" y1="12" x2="4" y2="12"></line>
                                <line x1="20" y1="12" x2="22" y2="12"></line>
                            </svg>
                        </span>
                        <span class="location-text">Use my current location</span>
                    </div>
                    
                    <div id="map-interface" style="display: none;">
                        <div class="controls">
                            <select id="searchRadius" class="radius-select">
                                <option value="5">5 km</option>
                                <option value="10" selected>10 km</option>
                                <option value="20">20 km</option>
                                <option value="50">50 km</option>
                            </select>
                            <button id="locationButton" class="location-button">
                                <i class="fas fa-location-arrow"></i> Get My Location
                            </button>
                        </div>

                        <div id="error" class="error"></div>
                        <div id="map" class="map-container"></div>
                    </div>
                    
                    <div class="location-option" id="map-location">
                        <span class="location-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 1 6"></polygon>
                                <line x1="8" y1="2" x2="8" y2="18"></line>
                                <line x1="16" y1="6" x2="16" y2="22"></line>
                            </svg>
                        </span>
                        <span class="location-text">Set location on the map</span>
                    </div>
                </div>

                <!-- Add map picker interface (initially hidden) -->
                <div id="map-picker" style="display: none;">
                    <h2>Select a Location</h2>
                    <p>Click on the map to pick a location.</p>
                    <div id="location-map" class="map-container" style="height: 300px; margin: 10px 0;"></div>
                    <div class="info" style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <p><strong>Latitude:</strong> <span id="lat">Not selected</span></p>
                        <p><strong>Longitude:</strong> <span id="lng">Not selected</span></p>
                        <p><strong>Address:</strong> <span id="address">Not selected</span></p>
                        <div class="button-container" style="margin-top: 10px;">
                            <button type="button" id="confirm-location" class="confirm-btn" disabled>Confirm Location</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Location data
        const locationData = {
            bengaluru: [
                { icon: 'fa-home', name: 'Home Delivery' },
                { icon: 'fa-building', name: 'IndusGo, Whitefield' },
                { icon: 'fa-plane', name: 'Bengaluru International Airport' },
                { icon: 'fa-train', name: 'Bengaluru City Railway Station' }
            ],
            calicut: [
                { icon: 'fa-home', name: 'Home Delivery' },
                { icon: 'fa-building', name: 'IndusGo, Calicut' },
                { icon: 'fa-plane', name: 'Calicut International Airport' },
                { icon: 'fa-train', name: 'Calicut Railway Station' }
            ],
            chennai: [
                { icon: 'fa-home', name: 'Home Delivery' },
                { icon: 'fa-building', name: 'IndusGo, T Nagar' },
                { icon: 'fa-plane', name: 'Chennai International Airport' },
                { icon: 'fa-train', name: 'Chennai Central' },
                { icon: 'fa-train', name: 'Chennai Egmore' }
            ],
            cochin: [
                { icon: 'fa-home', name: 'Home Delivery' },
                { icon: 'fa-building', name: 'IndusGo, Edapally' },
                { icon: 'fa-plane', name: 'Cochin International Airport' },
                { icon: 'fa-train', name: 'North Railway Station' },
                { icon: 'fa-train', name: 'South Railway Station' }
            ],
            hyderabad: [
                { icon: 'fa-home', name: 'Home Delivery' },
                { icon: 'fa-building', name: 'IndusGo, Hitech City' },
                { icon: 'fa-plane', name: 'Rajiv Gandhi International Airport' },
                { icon: 'fa-train', name: 'Secunderabad Railway Station' },
                { icon: 'fa-train', name: 'Hyderabad Deccan Station' }
            ],
            trivandrum: [
                { icon: 'fa-home', name: 'Home Delivery' },
                { icon: 'fa-building', name: 'IndusGo, Technopark' },
                { icon: 'fa-plane', name: 'Trivandrum International Airport' },
                { icon: 'fa-train', name: 'Trivandrum Central' }
            ]
        };

        document.addEventListener('DOMContentLoaded', function() {
            const pickupLocationInput = document.getElementById('pickup_location');
            const locationPopup = document.querySelector('.location-popup');
            const popupOverlay = document.querySelector('.popup-overlay');
            const closePopup = document.querySelector('.close-popup');
            const cityOptions = document.querySelectorAll('.city-option');
            const locationsList = document.querySelector('.locations-list');
            const nextBtn = document.querySelector('.next-btn');

            // Show popup when clicking the pickup location input
            pickupLocationInput.addEventListener('click', function() {
                locationPopup.style.display = 'block';
                popupOverlay.style.display = 'block';
                // Focus the first city option
                cityOptions[0].focus();
            });

            // Prevent the input from being editable directly
            pickupLocationInput.readOnly = true;

            function closeLocationPopup() {
                locationPopup.style.display = 'none';
                popupOverlay.style.display = 'none';
                pickupLocationInput.focus();
            }

            // Close popup when clicking the close button or overlay
            closePopup.addEventListener('click', closeLocationPopup);
            popupOverlay.addEventListener('click', closeLocationPopup);

            // Close popup when pressing Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && locationPopup.style.display === 'block') {
                    closeLocationPopup();
                }
            });

            // Handle keyboard navigation for city options
            cityOptions.forEach((city, index) => {
                city.addEventListener('keydown', function(e) {
                    let targetCity;
                    switch(e.key) {
                        case 'ArrowRight':
                            targetCity = cityOptions[index + 1] || cityOptions[0];
                            break;
                        case 'ArrowLeft':
                            targetCity = cityOptions[index - 1] || cityOptions[cityOptions.length - 1];
                            break;
                        case 'ArrowDown':
                            targetCity = cityOptions[index + 3] || cityOptions[index];
                            break;
                        case 'ArrowUp':
                            targetCity = cityOptions[index - 3] || cityOptions[index];
                            break;
                        case 'Enter':
                        case ' ':
                            e.preventDefault();
                            city.click();
                            return;
                    }
                    if (targetCity) {
                        targetCity.focus();
                    }
                });

                city.addEventListener('click', function() {
                    // Update ARIA selected states
                    cityOptions.forEach(c => {
                        c.setAttribute('aria-selected', 'false');
                        c.classList.remove('selected');
                    });
                    this.setAttribute('aria-selected', 'true');
                    this.classList.add('selected');
                    
                    // Get city locations
                    const cityLocations = locationData[this.dataset.city] || [];
                    
                    // Populate locations list
                    locationsList.innerHTML = cityLocations.map((location, idx) => `
                        <div class="location-option" role="option" tabindex="0" aria-selected="false">
                            <i class="fas ${location.icon}" aria-hidden="true"></i>
                            <span>${location.name}</span>
                        </div>
                    `).join('');

                    // Handle location selection and keyboard navigation
                    const locationOptions = locationsList.querySelectorAll('.location-option');
                    locationOptions.forEach((option, idx) => {
                        option.addEventListener('keydown', function(e) {
                            let targetOption;
                            switch(e.key) {
                                case 'ArrowDown':
                                    targetOption = locationOptions[idx + 1] || locationOptions[0];
                                    break;
                                case 'ArrowUp':
                                    targetOption = locationOptions[idx - 1] || locationOptions[locationOptions.length - 1];
                                    break;
                                case 'Enter':
                                case ' ':
                                    e.preventDefault();
                                    option.click();
                                    return;
                            }
                            if (targetOption) {
                                targetOption.focus();
                            }
                        });

                        option.addEventListener('click', function() {
                            locationOptions.forEach(o => {
                                o.setAttribute('aria-selected', 'false');
                                o.classList.remove('selected');
                            });
                            this.setAttribute('aria-selected', 'true');
                            this.classList.add('selected');
                            nextBtn.focus();
                        });
                    });

                    // Focus the first location option
                    if (locationOptions.length > 0) {
                        locationOptions[0].focus();
                    }
                });
            });

            // Close popup when clicking the next button
            nextBtn.addEventListener('click', function() {
                const selectedCity = document.querySelector('.city-option[aria-selected="true"]');
                const selectedLocation = document.querySelector('.location-option[aria-selected="true"]');
                
                if (selectedCity && selectedLocation) {
                    const cityName = selectedCity.querySelector('div').textContent.trim();
                    const locationName = selectedLocation.querySelector('span').textContent;
                    pickupLocationInput.value = `${locationName}, ${cityName}`;
                    closeLocationPopup();
                }
            });

            // Select Cochin by default
            const cochinOption = document.querySelector('.city-option[data-city="cochin"]');
            if (cochinOption) {
                cochinOption.click();
            }

            let map;
            let userMarker;
            let radiusCircle;
            const markers = [];

            // Initialize map when current location is clicked
            document.getElementById('current-location').addEventListener('click', function() {
                const mapInterface = document.getElementById('map-interface');
                mapInterface.style.display = 'block';
                
                if (!map) {
                    initMap();
                }
            });

            function initMap() {
                map = L.map('map').setView([10.8505, 76.2711], 7);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: ' OpenStreetMap contributors'
                }).addTo(map);

                // Add click handler for location button
                document.getElementById('locationButton').addEventListener('click', getUserLocation);
            }

            function getUserLocation() {
                if (navigator.geolocation) {
                    document.getElementById('error').textContent = "Detecting your location...";
                    document.getElementById('error').style.display = 'block';
                    
                    navigator.geolocation.getCurrentPosition(
                        // Success callback
                        (position) => {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            const radius = document.getElementById('searchRadius').value;
                            
                            document.getElementById('error').style.display = 'none';
                            updateMapView(lat, lng, radius);
                            
                            // Update the pickup location input and close popup
                            reverseGeocode(lat, lng);
                        },
                        // Error callback
                        (error) => {
                            let errorMessage = "Unable to get your location.";
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    errorMessage = "Location access was denied. Please enable location services.";
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    errorMessage = "Location information is unavailable.";
                                    break;
                                case error.TIMEOUT:
                                    errorMessage = "The request to get location timed out.";
                                    break;
                            }
                            showError(errorMessage);
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        }
                    );
                } else {
                    showError("Geolocation is not supported by this browser.");
                }
            }

            function updateMapView(lat, lng, radius) {
                if (userMarker) map.removeLayer(userMarker);
                if (radiusCircle) map.removeLayer(radiusCircle);

                userMarker = L.marker([lat, lng]).addTo(map);
                
                radiusCircle = L.circle([lat, lng], {
                    radius: radius * 1000,
                    color: '#f5b754',
                    fillColor: '#f5b754',
                    fillOpacity: 0.1,
                    weight: 1
                }).addTo(map);

                map.fitBounds(radiusCircle.getBounds());
            }

            function reverseGeocode(lat, lng) {
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then(response => response.json())
                    .then(data => {
                        const address = data.display_name;
                        pickupLocationInput.value = `${address} (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                        
                        // Close the entire location popup
                        const locationPopup = document.querySelector('.location-popup');
                        const popupOverlay = document.querySelector('.popup-overlay');
                        
                        if (locationPopup) locationPopup.style.display = 'none';
                        if (popupOverlay) popupOverlay.style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Error getting address:', error);
                        pickupLocationInput.value = `Location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                        
                        // Close popup even if there's an error getting the address
                        const locationPopup = document.querySelector('.location-popup');
                        const popupOverlay = document.querySelector('.popup-overlay');
                        
                        if (locationPopup) locationPopup.style.display = 'none';
                        if (popupOverlay) popupOverlay.style.display = 'none';
                    });
            }

            function showError(message) {
                const error = document.getElementById('error');
                error.textContent = message;
                error.style.display = 'block';
            }
        });

        function updateReturnDateOptions() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const maxRentalDays = 2; // Maximum rental period in days
            
            if (!startDateInput.value) {
                endDateInput.value = '';
                endDateInput.disabled = true;
                return;
            }

            const startDate = new Date(startDateInput.value);
            const maxEndDate = new Date(startDate);
            maxEndDate.setDate(startDate.getDate() + maxRentalDays);

            // Get the flatpickr instance for end date
            const endDatePicker = document.querySelector("#end_date")._flatpickr;
            
            // Update the flatpickr instance with new date constraints
            endDatePicker.set('minDate', startDate);
            endDatePicker.set('maxDate', maxEndDate);

            // Enable the input
            endDateInput.disabled = false;

            // Clear the end date if it's outside the valid range
            if (endDateInput.value) {
                const endDate = new Date(endDateInput.value);
                if (endDate < startDate || endDate > maxEndDate) {
                    endDateInput.value = '';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date pickers with booked dates disabled
            const bookedDates = <?php echo json_encode($booked_dates); ?>;
            const today = new Date();
            const maxRentalDays = 2; // Maximum rental period in days
            
            // Initialize start date picker
            flatpickr("#start_date", {
                dateFormat: "Y-m-d",
                minDate: today,
                maxDate: new Date(today.getFullYear(), today.getMonth() + 1, today.getDate()),
                disable: bookedDates,
                onChange: function(selectedDates) {
                    if (selectedDates[0]) {
                        updateReturnDateOptions();
                        calculateTotal();
                        
                        // Update pickup time restrictions based on selected date
                        const pickupTime = document.getElementById('pickup_time');
                        const now = new Date();
                        const selectedDate = selectedDates[0];
                        
                        // Reset the pickup time input
                        if (pickupTime._flatpickr) {
                            pickupTime._flatpickr.destroy();
                        }
                        
                        // Configure time picker based on selected date
                        const isToday = selectedDate.toDateString() === now.toDateString();
                        const minTime = isToday ? new Date(now.getTime() + 60 * 60 * 1000) : "00:00";
                        
                        flatpickr("#pickup_time", {
                            enableTime: true,
                            noCalendar: true,
                            dateFormat: "H:i",
                            minTime: isToday ? `${minTime.getHours()}:${minTime.getMinutes()}` : "00:00",
                            maxTime: "23:00",
                            minuteIncrement: 30,
                            defaultHour: isToday ? minTime.getHours() : 9,
                            defaultMinute: isToday ? Math.ceil(minTime.getMinutes() / 30) * 30 : 0,
                            onChange: function(selectedTimes) {
                                calculateTotal();
                            }
                        });
                    }
                }
            });
            
            // Initialize end date picker
            const endDatePicker = flatpickr("#end_date", {
                dateFormat: "Y-m-d",
                minDate: today,
                disable: bookedDates,
                onChange: function(selectedDates) {
                    if (selectedDates[0]) {
                        calculateTotal();
                    }
                }
            });

            // Disable end date initially
            document.getElementById('end_date').disabled = true;
        });

        document.getElementById('start_date').addEventListener('change', function() {
            updateReturnDateOptions();
            calculateTotal();
        });

        document.getElementById('end_date').addEventListener('change', function() {
            calculateTotal();
        });
        
        // Razorpay integration
        <?php if (isset($show_payment) && $show_payment): ?>
        document.getElementById('booking-form').style.display = 'none';
        document.getElementById('razorpay-button').style.display = 'block';
        
        document.getElementById('razorpay-button').onclick = function(e) {
            e.preventDefault();
            
            // Create Razorpay order
            fetch('order.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'error') {
                        throw new Error(data.message);
                    }
                    
                    var options = {
                        key: '<?php echo RAZORPAY_KEY_ID; ?>',
                        amount: data.amount,
                        currency: 'INR',
                        name: 'AUTOAURA',
                        image: 'assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png',
                        description: 'Car Rental Payment',
                        order_id: data.order_id,
                        handler: function(response) {
                            // Send payment verification details to server
                            fetch('verify.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: 'razorpay_payment_id=' + response.razorpay_payment_id +
                                      '&razorpay_order_id=' + response.razorpay_order_id +
                                      '&razorpay_signature=' + response.razorpay_signature
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (data.status === 'success') {
                                    window.location.href = data.redirect;
                                } else {
                                    throw new Error(data.message || 'Payment verification failed');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Payment verification failed: ' + error.message);
                            });
                        },
                        prefill: {
                            name: '<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''; ?>',
                            email: '<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>',
                            contact: '<?php echo isset($_SESSION['user_mobile']) ? htmlspecialchars($_SESSION['user_mobile']) : ''; ?>'
                        },
                        theme: {
                            color: '#3399cc'
                        }
                    };
                    
                    var rzp1 = new Razorpay(options);
                    rzp1.open();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                });
            return false;
        };
        <?php endif; ?>

        function calculateTotal() {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            const baseRate = <?php echo $car['price']; ?>;
            const driverRate = 800;
            const insurance = 500;

            if (startDate && endDate && endDate >= startDate) {
                const diffTime = Math.abs(endDate - startDate);
                const days = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                const total = (baseRate + driverRate) * days + insurance;
                
                // Show the breakdown
                document.querySelectorAll('.summary-item').forEach(item => {
                    const label = item.querySelector('span:first-child');
                    if (label) {
                        if (label.textContent.includes('Base Rate')) {
                            item.querySelector('span:last-child').textContent = '₹' + (baseRate * days).toLocaleString() + ' (' + days + ' days)';
                        } else if (label.textContent.includes('Driver Charges')) {
                            item.querySelector('span:last-child').textContent = '₹' + (driverRate * days).toLocaleString() + ' (' + days + ' days)';
                        }
                    }
                });

                document.getElementById('total_cost').textContent = '₹' + total.toLocaleString();
            } else {
                // Reset the display if dates are invalid
                document.getElementById('total_cost').textContent = '₹0';
                document.querySelectorAll('.summary-item').forEach(item => {
                    const label = item.querySelector('span:first-child');
                    if (label) {
                        if (label.textContent.includes('Base Rate')) {
                            item.querySelector('span:last-child').textContent = '₹<?php echo number_format($car['price']); ?>/day';
                        } else if (label.textContent.includes('Driver Charges')) {
                            item.querySelector('span:last-child').textContent = '₹800/day';
                        }
                    }
                });
            }
        }

        let map;
        let marker;
        const mapLocation = document.getElementById('map-location');
        const mapPicker = document.getElementById('map-picker');
        const confirmBtn = document.getElementById('confirm-location');
        const pickupLocationInput = document.getElementById('pickup_location');

        mapLocation.addEventListener('click', function() {
            // Hide location options and show map picker
            document.querySelector('.location-options').style.display = 'none';
            mapPicker.style.display = 'block';
            
            // Initialize map if not already done
            if (!map) {
                initializeMap();
            }
        });

        function initializeMap() {
            map = L.map('location-map').setView([20.5937, 78.9629], 5); // Default: India
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            map.on('click', function(e) {
                const lat = e.latlng.lat.toFixed(6);
                const lng = e.latlng.lng.toFixed(6);

                if (marker) {
                    map.removeLayer(marker);
                }

                marker = L.marker([lat, lng]).addTo(map)
                    .bindPopup(`Selected Location<br>Lat: ${lat}, Lng: ${lng}`)
                    .openPopup();

                document.getElementById("lat").textContent = lat;
                document.getElementById("lng").textContent = lng;
                document.getElementById("confirm-location").disabled = false;

                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then(response => response.json())
                    .then(data => {
                        let address = data.display_name || "Address not found";
                        document.getElementById("address").textContent = address;
                    })
                    .catch(() => document.getElementById("address").textContent = "Unable to fetch address");
            });
        }

        confirmBtn.addEventListener('click', function() {
            if (marker) {
                const lat = marker.getLatLng().lat.toFixed(6);
                const lng = marker.getLatLng().lng.toFixed(6);
                const address = document.getElementById("address").textContent;
                
                // Update pickup location input
                pickupLocationInput.value = `${address} (${lat}, ${lng})`;
                
                // Close the popup
                const locationPopup = document.querySelector('.location-popup');
                const popupOverlay = document.querySelector('.popup-overlay');
                
                if (locationPopup) locationPopup.style.display = 'none';
                if (popupOverlay) popupOverlay.style.display = 'none';
            } else {
                alert('Please select a location on the map first.');
            }
        });
        
        // Handle map location button
        document.getElementById('map-location').addEventListener('click', function() {
            // Hide location options and show map picker
            document.querySelector('.location-options').style.display = 'none';
            document.getElementById('map-picker').style.display = 'block';
            
            // Initialize map if not already done
            if (!map) {
                initializeMap();
            }
        });

        // Back button handler for map picker
        document.querySelector('.back-button').addEventListener('click', function() {
            // Hide map picker and show location options
            document.getElementById('map-picker').style.display = 'none';
            document.querySelector('.location-options').style.display = 'block';
        });

        // Function to receive location from map window
        window.setPickupLocation = function(address, lat, lng) {
            document.getElementById('pickup_location').value = `${address} (${lat}, ${lng})`;
        };

        document.addEventListener('DOMContentLoaded', function() {
            const mainContent = document.querySelector('.main-location-content');
            const homeDeliveryContent = document.querySelector('.home-delivery-content');
            const backButton = document.querySelector('.back-button');
            const mapInterface = document.getElementById('map-interface');
            const currentLocationBtn = document.getElementById('current-location');
            const locationButton = document.getElementById('locationButton');
            
            // Show home delivery interface when clicking Home Delivery option
            document.querySelector('.locations-list').addEventListener('click', function(e) {
                const locationOption = e.target.closest('.location-option');
                if (locationOption && locationOption.textContent.includes('Home Delivery')) {
                    mainContent.style.display = 'none';
                    homeDeliveryContent.style.display = 'block';
                }
            });
            
            // Handle current location button click
            currentLocationBtn.addEventListener('click', function() {
                if (navigator.geolocation) {
                    mapInterface.style.display = 'block';
                    if (!map) {
                        initializeMap();
                    }
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            map.setView([lat, lng], 15);
                            
                            if (marker) {
                                map.removeLayer(marker);
                            }
                            marker = L.marker([lat, lng]).addTo(map)
                                .bindPopup('Your Location')
                                .openPopup();

                            // Enable the confirm button
                            document.getElementById('confirm-location').disabled = false;
                            
                            // Update coordinates display
                            document.getElementById('lat').textContent = lat.toFixed(6);
                            document.getElementById('lng').textContent = lng.toFixed(6);
                            
                            // Get address using reverse geocoding
                            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                                .then(response => response.json())
                                .then(data => {
                                    const address = data.display_name || 'Address not found';
                                    document.getElementById('address').textContent = address;
                                })
                                .catch(() => {
                                    document.getElementById('address').textContent = 'Unable to fetch address';
                                });
                        },
                        function(error) {
                            let errorMessage;
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    errorMessage = "Location access denied. Please enable location services.";
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    errorMessage = "Location information unavailable.";
                                    break;
                                case error.TIMEOUT:
                                    errorMessage = "Location request timed out.";
                                    break;
                                default:
                                    errorMessage = "An unknown error occurred.";
                            }
                            document.getElementById('error').textContent = errorMessage;
                            document.getElementById('error').style.display = 'block';
                        }
                    );
                } else {
                    document.getElementById('error').textContent = "Geolocation is not supported by this browser.";
                    document.getElementById('error').style.display = 'block';
                }
            });
            
            // Handle Get My Location button click
            locationButton.addEventListener('click', function() {
                currentLocationBtn.click(); // Reuse the current location functionality
            });
            
            // Back button returns to main location selection
            backButton.addEventListener('click', function() {
                homeDeliveryContent.style.display = 'none';
                mainContent.style.display = 'block';
                mapInterface.style.display = 'none';
                document.getElementById('error').style.display = 'none';
            });
        });
    </script>
</body>
</html>
