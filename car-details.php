<?php
include 'db_connect.php';

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
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($car['car_name']); ?> - LUXE DRIVE</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Car Gallery Section */
        .car-gallery {
            display: flex;
            gap: 20px;
            margin-bottom: 40px;
        }

        .main-image {
            flex: 2;
            position: relative;
            height: 400px;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .thumbnails {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .thumbnail {
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .thumbnail:hover {
            transform: scale(1.05);
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Car Details Section */
        .car-details {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .car-info h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: #333;
        }

        .car-info p {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #666;
            margin-bottom: 15px;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .feature i {
            color: #f5b754;
            font-size: 1.2rem;
        }

        /* Booking Section */
        .booking-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }

        .price {
            font-size: 2rem;
            color: #333;
            margin-bottom: 20px;
        }

        .price span {
            font-size: 1rem;
            color: #666;
        }

        .book-now-btn {
            width: 100%;
            padding: 15px;
            background: #f5b754;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform 0.3s ease, background 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .book-now-btn:hover {
            background: #e4a643;
            transform: translateY(-2px);
        }

        /* Reviews Section */
        .reviews {
            margin-top: 40px;
        }

        .reviews h2 {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: #333;
        }

        .review-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .review-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .reviewer {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .reviewer img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .stars {
            color: #ffc107;
            margin-bottom: 10px;
        }

        .review-text {
            color: #666;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .car-gallery {
                flex-direction: column;
            }

            .car-details {
                grid-template-columns: 1fr;
            }

            .main-image {
                height: 300px;
            }

            .thumbnails {
                grid-template-columns: repeat(4, 1fr);
            }

            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Car Gallery Section -->
        <div class="car-gallery">
            <div class="main-image">
                <img src="<?php echo htmlspecialchars($images['main_image']); ?>" alt="<?php echo htmlspecialchars($car['car_name']); ?>" id="mainImage">
            </div>
            <div class="thumbnails">
                <?php if (!empty($images['main_image'])): ?>
                    <div class="thumbnail" onclick="changeImage('<?php echo htmlspecialchars($images['main_image']); ?>')">
                        <img src="<?php echo htmlspecialchars($images['main_image']); ?>" alt="Main Image">
                    </div>
                <?php endif; ?>
                
                <?php 
                if (!empty($images['thumbnails']) && is_array($images['thumbnails'])):
                    foreach ($images['thumbnails'] as $thumbnail):
                        if (!empty($thumbnail)):
                ?>
                    <div class="thumbnail" onclick="changeImage('<?php echo htmlspecialchars($thumbnail); ?>')">
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="Car Thumbnail">
                    </div>
                <?php 
                        endif;
                    endforeach;
                endif;
                ?>
            </div>
        </div>

        <!-- Car Details Section -->
        <div class="car-details">
            <div class="car-info">
                <h1><?php echo htmlspecialchars($car['car_name']); ?></h1>
                <p><?php echo nl2br(htmlspecialchars($car['car_description'])); ?></p>
                
                <div class="features">
                    <?php if ($features && is_array($features)): ?>
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
                    <?php endif; ?>
                </div>
            </div>

            <div class="booking-card">
                <div class="price">
                    ₹<?php echo number_format($car['price']); ?> <span>/ day</span>
                </div>
                <a href="rent-car.php?id=<?php echo $car['id']; ?>" class="book-now-btn">Book Now</a>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="reviews">
            <h2>Customer Reviews</h2>
            <div class="review-grid">
                <div class="review-card">
                    <div class="reviewer">
                        <i class="fas fa-user-circle" style="font-size: 50px; color: #ccc;"></i>
                        <div>
                            <h4>John Doe</h4>
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="review-text">"Amazing experience! The car was in perfect condition and the service was exceptional."</p>
                </div>

                <div class="review-card">
                    <div class="reviewer">
                        <i class="fas fa-user-circle" style="font-size: 50px; color: #ccc;"></i>
                        <div>
                            <h4>Jane Smith</h4>
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="far fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="review-text">"Luxury at its finest. The car exceeded my expectations in every way."</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function changeImage(src) {
            const mainImage = document.getElementById('mainImage');
            mainImage.style.opacity = '0';
            setTimeout(() => {
                mainImage.src = src;
                mainImage.style.opacity = '1';
            }, 300);
        }

        // Add smooth transitions for images
        document.addEventListener('DOMContentLoaded', () => {
            const mainImage = document.getElementById('mainImage');
            mainImage.style.transition = 'opacity 0.3s ease';
        });
    </script>
</body>
</html>
