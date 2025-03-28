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

        .main-content {
            display: flex;
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .filters-container {
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            padding-right: 15px;
            scrollbar-width: thin;
            width: 250px;
            flex-shrink: 0;
        }

        .filters-container::-webkit-scrollbar {
            width: 6px;
        }

        .filters-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .filters-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .filters-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .filter-section {
            margin-bottom: 20px;
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 250px;
        }

        .filter-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #333;
        }

        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-checkbox input[type="checkbox"] {
            margin: 0;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .filter-checkbox label {
            font-size: 14px;
            color: #555;
            cursor: pointer;
            user-select: none;
        }

        .filter-checkbox:hover label {
            color: #000;
        }

        .cars-grid {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            align-content: start;
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
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .car-image-container {
            overflow: hidden;
            position: relative;
        }

        .car-image-container:hover .car-image {
            transform: scale(1.05);
        }

        .car-image-container:hover::after {
            content: 'View Details';
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9rem;
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

        .price-range {
            margin-top: 10px;
        }

        .price-input {
            position: relative;
            margin-bottom: 12px;
        }

        .price-input label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .price-input input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            color: #333;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .price-input input:focus {
            outline: none;
            border-color: #f5b754;
            box-shadow: 0 0 0 2px rgba(245, 183, 84, 0.1);
            background: #fff;
        }

        .price-input::before {
            content: "₹";
            position: absolute;
            left: 8px;
            bottom: 8px;
            color: #666;
            font-size: 14px;
        }

        .price-input input {
            padding-left: 24px;
        }

        .clear-filters {
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            color: #666;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .clear-filters:hover {
            background: #f5b754;
            border-color: #f5b754;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(245, 183, 84, 0.2);
        }

        .clear-filters:active {
            transform: translateY(0);
        }

        .clear-filters i {
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .main-content {
                flex-direction: column;
            }

            .filters-container {
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

            .filters-container.active {
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
            <div class="logo" style="margin-left: -100px;">
                <a href="dashboard.php">
                    <img src="assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png" alt="LUXE DRIVE" style="height: 50px;">
                </a>
            </div>
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search for cars..." id="searchInput">
            </div>
        </div>
    </header>

    <div class="main-content">
        <div class="filters-container">
            <div class="filter-section">
                <h3 class="filter-title">Transmission</h3>
                <div class="filter-options">
                    <div class="filter-checkbox">
                        <input type="checkbox" id="automatic" value="automatic">
                        <label for="automatic">Automatic</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="manual" value="manual">
                        <label for="manual">Manual</label>
                    </div>
                </div>
            </div>

            <div class="filter-section">
                <h3 class="filter-title">Car Type</h3>
                <div class="filter-options">
                    <div class="filter-checkbox">
                        <input type="checkbox" id="sedan" value="sedan">
                        <label for="sedan">Sedan</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="suv" value="suv">
                        <label for="suv">SUV</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="hatchback" value="hatchback">
                        <label for="hatchback">Hatchback</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="luxury" value="luxury">
                        <label for="luxury">Luxury</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="muv" value="muv">
                        <label for="muv">MUV</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="sports" value="sports">
                        <label for="sports">Sports</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="electric" value="electric">
                        <label for="electric">Electric</label>
                    </div>
                </div>
            </div>

            <div class="filter-section">
                <h3 class="filter-title">Seating Capacity</h3>
                <div class="filter-options">
                    <div class="filter-checkbox">
                        <input type="checkbox" id="seats-4" value="4">
                        <label for="seats-4">4 Seater</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="seats-5" value="5">
                        <label for="seats-5">5 Seater</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="seats-7" value="7">
                        <label for="seats-7">7 Seater</label>
                    </div>
                </div>
            </div>

            <div class="filter-section">
                <h3 class="filter-title">Price Range</h3>
                <div class="price-range">
                    <div class="price-input">
                        <label for="minPrice">Minimum Price</label>
                        <input type="number" id="minPrice" placeholder="0" min="0">
                    </div>
                    <div class="price-input">
                        <label for="maxPrice">Maximum Price</label>
                        <input type="number" id="maxPrice" placeholder="Any" min="0">
                    </div>
                </div>
            </div>

            <button class="clear-filters" onclick="clearFilters()">
                <i class="fas fa-undo-alt"></i>
                Clear All Filters
            </button>
        </div>

        <div class="cars-grid" id="carsGrid">
            <?php
            if ($result && $result->num_rows > 0) {
                $delay = 0;
                while ($car = $result->fetch_assoc()) {
                    $features = json_decode($car['car_features'], true);
                    $images = json_decode($car['images'], true);
                    $mainImage = !empty($images['main_image']) ? $images['main_image'] : 'assets/default-car.jpg';
                    ?>
                    <div class="car-card" style="--delay: <?php echo $delay; ?>" 
                         data-car-type="<?php echo htmlspecialchars($features['car_type'] ?? ''); ?>">
                        <div class="car-image-container" onclick="window.location.href='car-details.php?id=<?php echo $car['id']; ?>'">
                            <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($car['car_name']); ?>" class="car-image">
                        </div>
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
                    <?php $delay++; ?>
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

    // Filter functionality
    function applyFilters() {
        const cars = document.querySelectorAll('.car-card');
        const minPrice = parseFloat(document.getElementById('minPrice').value) || 0;
        const maxPrice = parseFloat(document.getElementById('maxPrice').value) || Infinity;
        
        // Get selected transmission types
        const selectedTransmission = Array.from(document.querySelectorAll('input[type="checkbox"][id^="automatic"], input[type="checkbox"][id^="manual"]'))
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        // Get selected car types
        const selectedCarTypes = Array.from(document.querySelectorAll('input[type="checkbox"][id^="sedan"], input[type="checkbox"][id^="suv"], input[type="checkbox"][id^="hatchback"], input[type="checkbox"][id^="luxury"], input[type="checkbox"][id^="muv"], input[type="checkbox"][id^="sports"], input[type="checkbox"][id^="electric"]'))
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        // Get selected seating capacity
        const selectedSeats = Array.from(document.querySelectorAll('input[type="checkbox"][id^="seats-"]'))
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        cars.forEach(car => {
            const price = parseFloat(car.querySelector('.price').textContent.replace(/[^\d.]/g, ''));
            const transmission = car.querySelector('.spec:nth-child(2)').textContent.toLowerCase();
            const seats = car.querySelector('.spec:nth-child(1)').textContent.trim();
            const carType = car.dataset.carType ? car.dataset.carType.toLowerCase() : '';
            
            const matchesPrice = price >= minPrice && price <= maxPrice;
            const matchesTransmission = selectedTransmission.length === 0 || selectedTransmission.some(t => transmission.includes(t));
            const matchesSeats = selectedSeats.length === 0 || selectedSeats.some(s => seats.includes(s));
            const matchesCarType = selectedCarTypes.length === 0 || (carType && selectedCarTypes.includes(carType));
            
            if (matchesPrice && matchesTransmission && matchesSeats && matchesCarType) {
                car.style.display = '';
                car.style.animation = 'fadeInUp 0.5s ease forwards';
            } else {
                car.style.display = 'none';
            }
        });

        // Log selected filters for debugging
        console.log('Selected Car Types:', selectedCarTypes);
        console.log('Cars with their types:');
        cars.forEach(car => {
            console.log(car.dataset.carType, car.style.display);
        });
    }

    // Clear all filters
    function clearFilters() {
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        document.getElementById('minPrice').value = '';
        document.getElementById('maxPrice').value = '';
        document.querySelectorAll('.car-card').forEach(car => {
            car.style.display = '';
            car.style.animation = 'fadeInUp 0.5s ease forwards';
        });
    }

    // Add event listeners to all filter inputs
    document.querySelectorAll('input[type="checkbox"], #minPrice, #maxPrice').forEach(input => {
        input.addEventListener('change', applyFilters);
    });

    document.getElementById('minPrice').addEventListener('input', applyFilters);
    document.getElementById('maxPrice').addEventListener('input', applyFilters);
    </script>
</body>
</html>
<?php $conn->close(); ?>
