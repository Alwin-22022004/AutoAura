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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())";
            
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
                    <input type="date" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>" onchange="calculateTotal()">
                </div>

                <div class="form-group">
                    <label for="pickup_time">Pickup Time</label>
                    <input type="time" id="pickup_time" name="pickup_time" required>
                </div>

                <div class="form-group">
                    <label for="end_date">Return Date</label>
                    <input type="date" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>" onchange="calculateTotal()">
                </div>

                <div class="form-group">
                    <label for="pickup_location">Pickup Location</label>
                    <input type="text" id="pickup_location" name="pickup_location" placeholder="Enter pickup location" required>
                </div>
            </div>

            <div class="right-section">
                <div class="form-group">
                    <label for="center">Nearest Car Center</label>
                    <select id="center" name="center" required>
                        <option value="">Select a center</option>
                        <option value="central">LUXE DRIVE Central</option>
                        <option value="north">LUXE DRIVE North</option>
                        <option value="south">LUXE DRIVE South</option>
                        <option value="east">LUXE DRIVE East</option>
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
                        <span>₹<?php echo number_format($car['price']); ?>/day</span>
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
        <button id="razorpay-button" style="display: block;">Pay Now</button>
        <?php endif; ?>
    </div>

    <script>
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

                document.getElementById('total_cost').textContent = '₹' + total.toLocaleString();
            }
        }

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
    </script>
</body>
</html>
