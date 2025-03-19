<?php
include 'db_connect.php';

// Fetch approved cars from database
$sql = "SELECT * FROM cars WHERE status = 'approved' AND is_active = 1 ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Rental Cars - LUXE DRIVE</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f5f5;
        }

        header {
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }

        .search-bar {
            flex: 0 1 400px;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 0.5rem 1rem;
            padding-left: 2.5rem;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            border-color: #f5b754;
            box-shadow: 0 0 0 3px rgba(245, 183, 84, 0.2);
            outline: none;
        }

        .search-bar i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }

        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: fit-content;
            opacity: 0;
            transform: translateX(-20px);
            animation: slideInLeft 0.5s ease forwards;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .filter-section {
            margin-bottom: 1.5rem;
        }

        .filter-section h3 {
            margin-bottom: 1rem;
            color: #333;
            font-size: 1.1rem;
        }

        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cars-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .car-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.5s ease forwards;
            animation-delay: calc(var(--delay) * 0.1s);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .car-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .car-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .car-card:hover .car-image {
            transform: scale(1.1);
        }

        .car-details {
            padding: 1rem;
        }

        .car-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .car-specs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin: 1rem 0;
            font-size: 0.9rem;
            color: #666;
        }

        .spec {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .price-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .price {
            font-size: 1.3rem;
            font-weight: 600;
            color: #f5b754;
        }

        .rent-button {
            background: #f5b754;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .rent-button::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .rent-button:hover::after {
            width: 200px;
            height: 200px;
        }

        .rent-button:hover {
            background: #e6a53d;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
            }

            .filters {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 100;
                border-radius: 20px 20px 0 0;
                padding: 1rem;
                transform: translateY(90%);
                transition: transform 0.3s ease;
            }

            .filters.active {
                transform: translateY(0);
            }

            .filter-toggle {
                display: block;
                position: fixed;
                bottom: 1rem;
                right: 1rem;
                background: #f5b754;
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 25px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                z-index: 101;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">
                <img src="assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png" alt="LUXE DRIVE" style="height: 50px;">
            </div>
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search for cars..." id="searchInput">
            </div>
        </div>
    </header>

    <div class="main-container">
        <div class="filters">
            <div class="filter-section">
                <h3>Car Type</h3>
                <div class="filter-options" id="carTypeFilters">
                    <!-- Car types will be dynamically added here -->
                </div>
            </div>

            <div class="filter-section">
                <h3>Price Range</h3>
                <div class="range-filter">
                    <input type="range" min="10000" max="20000" value="500" id="priceRange">
                    <div class="price-inputs">
                        <input type="number" placeholder="Min" id="minPrice">
                        <input type="number" placeholder="Max" id="maxPrice">
                    </div>
                </div>
            </div>

            <div class="filter-section">
                <h3>Features</h3>
                <div class="filter-options" id="featureFilters">
                    <!-- Features will be dynamically added here -->
                </div>
            </div>
        </div>

        <div class="cars-grid" id="carsGrid">
            <?php
            if ($result && $result->num_rows > 0) {
                while ($car = $result->fetch_assoc()) {
                    $features = json_decode($car['car_features'], true);
                    $images = json_decode($car['images'], true);
                    $mainImage = !empty($images['main_image']) ? $images['main_image'] : 'assets/default-car.jpg';
                    ?>
                    <div class="car-card" data-car-type="<?php echo htmlspecialchars($features['car_type']); ?>">
                        <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($car['car_name']); ?>" class="car-image">
                        <div class="car-details">
                            <h3 class="car-title"><?php echo htmlspecialchars($car['car_name']); ?></h3>
                            <div class="car-specs">
                                <div class="spec">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo htmlspecialchars($features['seats']); ?></span>
                                </div>
                                <div class="spec">
                                    <i class="fas fa-cog"></i>
                                    <span><?php echo htmlspecialchars($features['transmission']); ?></span>
                                </div>
                                <div class="spec">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span><?php echo htmlspecialchars($features['mileage']); ?></span>
                                </div>
                                <div class="spec">
                                    <i class="fas fa-snowflake"></i>
                                    <span><?php echo htmlspecialchars($features['ac_type']); ?></span>
                                </div>
                            </div>
                            <div class="price-section">
                                <div class="price">₹<?php echo number_format($car['price']); ?> <span>/ day</span></div>
                                <a href="car-details.php?id=<?php echo $car['id']; ?>" class="rent-button">View Details</a>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<p>No cars available at the moment.</p>';
            }
            ?>
        </div>
    </div>

   

    <script>
    function rentCar(carId) {
        // Add your rental logic here
        alert('Rental functionality will be implemented soon!');
    }

    // Initialize animations when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        // Add animation delays to car cards
        const cards = document.querySelectorAll('.car-card');
        cards.forEach((card, index) => {
            card.style.setProperty('--delay', index + 1);
        });

        // Animate cards on scroll
        const animateOnScroll = () => {
            const cards = document.querySelectorAll('.car-card');
            cards.forEach(card => {
                const cardTop = card.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                if (cardTop < windowHeight * 0.9) {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }
            });
        };

        window.addEventListener('scroll', animateOnScroll);
        animateOnScroll(); // Initial check
    });

    // Search functionality with debounce
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    const searchCars = debounce(function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const cars = document.querySelectorAll('.car-card');
        
        cars.forEach(car => {
            const title = car.querySelector('.car-title').textContent.toLowerCase();
            const specs = car.querySelector('.car-specs').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || specs.includes(searchTerm)) {
                car.style.display = '';
                car.style.animation = 'fadeInUp 0.5s ease forwards';
            } else {
                car.style.display = 'none';
            }
        });
    }, 300);

    document.getElementById('searchInput').addEventListener('input', searchCars);

    // Enhanced price filter with smooth transitions
    function applyPriceFilter() {
        const minPrice = parseFloat(document.getElementById('minPrice').value) || 0;
        const maxPrice = parseFloat(document.getElementById('maxPrice').value) || Infinity;
        const cars = document.querySelectorAll('.car-card');
        
        cars.forEach(car => {
            const price = parseFloat(car.querySelector('.price').textContent.replace(/[^\d.]/g, ''));
            if (price >= minPrice && price <= maxPrice) {
                car.style.display = '';
                car.style.animation = 'fadeInUp 0.5s ease forwards';
            } else {
                car.style.opacity = '0';
                car.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    car.style.display = 'none';
                }, 500);
            }
        });
    }

    const debouncedPriceFilter = debounce(applyPriceFilter, 300);
    document.getElementById('minPrice').addEventListener('input', debouncedPriceFilter);
    document.getElementById('maxPrice').addEventListener('input', debouncedPriceFilter);

    // Smooth mobile filters toggle
    function toggleFilters() {
        const filters = document.querySelector('.filters');
        filters.style.transition = 'transform 0.3s ease';
        filters.classList.toggle('active');
    }
    </script>
</body>
</html>
<?php $conn->close(); ?>
