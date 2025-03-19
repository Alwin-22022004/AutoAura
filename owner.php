<?php

session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

    // Convert image paths to JSON
    $images = [
        'main_image' => $main_image,
        'thumbnails' => $thumbnails
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
        echo "<div class='alert success'>Car listing added successfully!</div>";
    } else {
        echo "<div class='alert error'>Error: " . $stmt->error . "</div>";
    }
    
    $stmt->close();
    $conn->close();
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data">
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
                        
                        <button type="submit" class="submit-btn">Submit Car Details</button>
                    </form>
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
                        <div class="value">12</div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Bookings</h3>
                        <div class="value">5</div>
                    </div>
                    <div class="stat-card">
                        <h3>Monthly Earnings</h3>
                        <div class="value">$8,450</div>
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
        </div>
    </div>

    <script>
        document.querySelectorAll(".nav-item").forEach((item) => {
            item.addEventListener("click", () => {
                document.querySelectorAll(".nav-item").forEach((navItem) => {
                    navItem.classList.remove("active");
                });
                item.classList.add("active");
                const section = item.dataset.section;

                // Hide all sections
                document.querySelectorAll("#dashboard-section, #parts-section, #cars-section").forEach((section) => {
                    section.classList.add("hidden");
                });

                // Show selected section
                if (section === "parts") {
                    document.querySelector("#parts-section").classList.remove("hidden");
                } else if (section === "dashboard") {
                    document.querySelector("#dashboard-section").classList.remove("hidden");
                } else if (section === "cars") {
                    document.querySelector("#cars-section").classList.remove("hidden");
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
</body>
</html>
