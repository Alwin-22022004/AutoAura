<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid booking ID.");
}

$booking_id = intval($_GET['id']);
$sql = "SELECT b.*, c.car_name, c.images, c.car_features, u.fullname as user_name, u.email 
        FROM bookings b 
        JOIN cars c ON b.car_id = c.id 
        JOIN users u ON b.user_id = u.id 
        WHERE b.booking_id = ? AND b.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Booking not found.");
}

$booking = $result->fetch_assoc();
$images = json_decode($booking['images'], true);
$features = json_decode($booking['car_features'], true);

// Calculate base rate
$base_rate = $booking['total_price'] - ($booking['driver_charges'] + $booking['insurance']);

// Format status message
$status_messages = [
    'pending' => ['class' => 'pending', 'text' => 'Pending Confirmation'],
    'confirmed' => ['class' => 'confirmed', 'text' => 'Booking Confirmed'],
    'cancelled' => ['class' => 'cancelled', 'text' => 'Booking Cancelled']
];

$current_status = $status_messages[$booking['status']] ?? $status_messages['pending'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking #<?php echo str_pad($booking['booking_id'], 6, '0', STR_PAD_LEFT); ?> - AUTOAURA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background: linear-gradient(rgb(255 255 255 / 70%), rgb(220 205 205 / 70%)), url(assets/bg-pattern.jpg) center / cover;
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border-radius: 10px;
        }

        .logo img {
            height: 50px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
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

        .success-message {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #e8f5e9;
            border-radius: 8px;
            color: #2e7d32;
        }

        .success-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: checkmark 0.5s ease-in-out;
        }

        @keyframes checkmark {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .booking-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .booking-id {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #ddd;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .detail-item {
            margin-bottom: 1rem;
        }

        .detail-item h3 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .detail-item p {
            font-size: 1.1rem;
            color: #333;
        }

        .car-info {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .car-image {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }

        .car-details h2 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .price-breakdown {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #eee;
        }

        .price-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
            font-weight: bold;
            color: #f5b754;
            font-size: 1.2rem;
        }

        .buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
        }

        .btn-primary {
            background: #f5b754;
            color: white;
        }

        .btn-secondary {
            background: #e9ecef;
            color: #333;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }

            .car-info {
                flex-direction: column;
            }

            .car-image {
                width: 100%;
                height: 200px;
            }

            .buttons {
                flex-direction: column;
            }
        }

        /* Add status styles */
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        @media print {
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 15px !important;
                box-shadow: none !important;
                background: white !important;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
            }
            .header { margin-bottom: 1rem !important; }
            .logo img { height: 40px !important; }
            .success-message { padding: 0.5rem !important; margin-bottom: 1rem !important; }
            .booking-details { padding: 1rem !important; margin-bottom: 1rem !important; }
            .car-info { padding: 1rem !important; margin-bottom: 1rem !important; }
            .car-image { width: 150px !important; height: 100px !important; }
            .price-breakdown { padding: 1rem !important; margin-top: 1rem !important; }
            .buttons { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
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
        <div class="success-message">
            <i class="fas <?php echo $booking['status'] === 'confirmed' ? 'fa-check-circle' : 'fa-info-circle'; ?>"></i>
            <h1><?php echo $current_status['text']; ?></h1>
            <p class="status-badge status-<?php echo $current_status['class']; ?>">
                Booking Status: <?php echo ucfirst($booking['status']); ?> | 
                Payment Status: <?php echo ucfirst($booking['payment_status']); ?>
            </p>
        </div>

        <div class="booking-details">
            <div class="booking-id">
                Booking ID: #<?php echo str_pad($booking['booking_id'], 6, '0', STR_PAD_LEFT); ?>
            </div>

            <div class="details-grid">
                <div class="detail-item">
                    <h3>Customer Name</h3>
                    <p><?php echo htmlspecialchars($booking['user_name']); ?></p>
                </div>

                <div class="detail-item">
                    <h3>Pickup Date & Time</h3>
                    <p><?php echo date('d M Y', strtotime($booking['start_date'])); ?> at <?php echo date('h:i A', strtotime($booking['pickup_time'])); ?></p>
                </div>

                <div class="detail-item">
                    <h3>Return Date</h3>
                    <p><?php echo date('d M Y', strtotime($booking['end_date'])); ?></p>
                </div>

                <div class="detail-item">
                    <h3>Pickup Location</h3>
                    <p><?php echo htmlspecialchars($booking['pickup_location']); ?></p>
                </div>

                <div class="detail-item">
                    <h3>Car Center</h3>
                    <p>AUTOAURA <?php echo ucfirst(htmlspecialchars($booking['center'])); ?></p>
                </div>

                <div class="detail-item">
                    <h3>Payment Method</h3>
                    <p><?php echo ucfirst(htmlspecialchars($booking['payment_method'])); ?></p>
                </div>
            </div>
        </div>

        <div class="car-info">
            <img src="<?php echo htmlspecialchars($images['main_image']); ?>" alt="<?php echo htmlspecialchars($booking['car_name']); ?>" class="car-image">
            <div class="car-details">
                <h2><?php echo htmlspecialchars($booking['car_name']); ?></h2>
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

        <div class="price-breakdown">
            <h3>Price Breakdown</h3>
            <div class="price-item">
                <span>Base Rate</span>
                <span>₹<?php echo number_format($base_rate); ?></span>
            </div>
            <div class="price-item">
                <span>Driver Charges</span>
                <span>₹<?php echo number_format($booking['driver_charges']); ?></span>
            </div>
            <div class="price-item">
                <span>Insurance</span>
                <span>₹<?php echo number_format($booking['insurance']); ?></span>
            </div>
            <div class="price-item">
                <span>Total Amount</span>
                <span>₹<?php echo number_format($booking['total_price']); ?></span>
            </div>
        </div>

        <div class="buttons">
            <a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
            <button onclick="window.print()" class="btn btn-primary">Print Booking</button>
        </div>
    </div>
</body>
</html>
