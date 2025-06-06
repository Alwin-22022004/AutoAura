<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "car_rental";

// Create connection with retry mechanism
function createConnection($maxRetries = 3) {
    global $servername, $username, $password, $dbname;
    
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            $conn = new mysqli($servername, $username, $password, $dbname);
            
            if (!$conn->connect_error) {
                $conn->set_charset("utf8mb4");
                return $conn;
            }
            
            // Wait for 1 second before retrying
            sleep(1);
        } catch (mysqli_sql_exception $e) {
            if ($i === $maxRetries - 1) {
                die("Connection failed after {$maxRetries} attempts: " . $e->getMessage());
            }
            // Wait before retry
            sleep(1);
        }
    }
    
    die("Failed to establish database connection after {$maxRetries} attempts");
}

// Create connection with retry mechanism
$conn = createConnection();

// Check if we need to create the database
$result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
if ($result->num_rows === 0) {
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname";
    if (!$conn->query($sql)) {
        die("Error creating database: " . $conn->error);
    }
    
    // Select the database
    $conn->select_db($dbname);
}

// Create users table with proper storage engine
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    verification_doc LONGBLOB,
    address VARCHAR(255) DEFAULT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    auth_type VARCHAR(20) DEFAULT NULL,
    active ENUM('active', 'blocked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email),
    UNIQUE KEY unique_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($sql)) {
    die("Error creating users table: " . $conn->error);
}

// Add is_active column if it doesn't exist
$check_column = "SHOW COLUMNS FROM users LIKE 'is_active'";
$result = $conn->query($check_column);
if ($result->num_rows === 0) {
    $add_column = "ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1";
    if (!$conn->query($add_column)) {
        die("Error adding is_active column: " . $conn->error);
    }
}

// Create reviews table
$sql = "CREATE TABLE IF NOT EXISTS reviews (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    booking_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    car_id INT(11) NOT NULL,
    rating INT(1) NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($sql)) {
    die("Error creating reviews table: " . $conn->error);
}

// Create cars table
$sql = "CREATE TABLE IF NOT EXISTS cars (
    id INT(11) NOT NULL,
    car_name VARCHAR(255) NOT NULL,
    car_description TEXT NOT NULL,
    car_features TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    images LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(images)),
    rc_document VARCHAR(255) DEFAULT NULL,
    owner_id INT(11) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (!$conn->query($sql)) {
    die("Error creating cars table: " . $conn->error);
}

// Create bookings table if it doesn't exist
$sql_create_bookings = "CREATE TABLE IF NOT EXISTS bookings (
    booking_id INT PRIMARY KEY AUTO_INCREMENT,
    car_id INT NOT NULL,
    user_id INT NOT NULL,
    booking_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    pickup_time TIME NOT NULL,
    pickup_location VARCHAR(255) NOT NULL,
    center VARCHAR(50) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    driver_charges DECIMAL(10,2) NOT NULL,
    insurance DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    payment_status ENUM('pending','captured', 'completed', 'refunded') DEFAULT 'pending',
    feedback_status ENUM('pending', 'submitted', 'skipped') DEFAULT 'pending',
    FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql_create_bookings) === FALSE) {
    die("Error creating bookings table: " . $conn->error);
}

// Add missing columns if they don't exist
$alter_queries = [
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS pickup_time TIME NOT NULL AFTER end_date",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS pickup_location VARCHAR(255) NOT NULL AFTER pickup_time",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS center VARCHAR(50) NOT NULL AFTER pickup_location",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) NOT NULL AFTER center",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS driver_charges DECIMAL(10,2) NOT NULL AFTER total_price",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS insurance DECIMAL(10,2) NOT NULL AFTER driver_charges",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS feedback_status ENUM('pending', 'submitted', 'skipped') DEFAULT 'pending' AFTER payment_status"
];

foreach ($alter_queries as $query) {
    if ($conn->query($query) === FALSE) {
        echo "Error altering table: " . $conn->error . "\n";
    }
}

// Create payments table
$sql = "CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    payment_id VARCHAR(255) NOT NULL,
    order_id VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    status ENUM('pending', 'captured', 'completed', 'refunded') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (!$conn->query($sql)) {
    echo "Error creating payments table: " . $conn->error;
}

// Alter existing payments table to update status field if it exists
$alter_payment_status = "ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'captured', 'completed', 'refunded') NOT NULL DEFAULT 'pending'";
if (!$conn->query($alter_payment_status)) {
    echo "Error updating payments status field: " . $conn->error;
}

// Create workshops table
$sql = "CREATE TABLE IF NOT EXISTS workshops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    services TEXT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (!$conn->query($sql)) {
    echo "Error creating workshops table: " . $conn->error;
}

// Create complaints table
$sql = "CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'resolved', 'in_progress') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (!$conn->query($sql)) {
    echo "Error creating complaints table: " . $conn->error;
}

// Create enquiries table
$sql = "CREATE TABLE IF NOT EXISTS enquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    owner_id INT,
    message TEXT NOT NULL,
    is_owner_reply BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!$conn->query($sql)) {
    echo "Error creating enquiries table: " . $conn->error;
}

// Set strict mode for better data integrity
$conn->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
?>