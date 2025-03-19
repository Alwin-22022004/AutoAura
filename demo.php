<?php
include 'db_connect.php'; // Include your database connection file

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $car_name = $_POST['car_name'];
    $car_description = $_POST['car_description'];
    $car_features = $_POST['car_features'];
    $price = $_POST['price'];
    
    // Image upload handling
    $target_dir = "uploads/";
    $image_paths = [];
    
    for ($i = 0; $i < 4; $i++) {
        $image_name = basename($_FILES['car_images']['name'][$i]);
        $target_file = $target_dir . time() . "_" . $image_name;
        
        if (move_uploaded_file($_FILES['car_images']['tmp_name'][$i], $target_file)) {
            $image_paths[] = $target_file;
        }
    }
    
    // Convert images array to JSON for storage
    $images_json = json_encode($image_paths);
    
    // Insert into database
    $sql = "INSERT INTO cars (car_name, car_description, car_features, price, images, status) VALUES (?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssis", $car_name, $car_description, $car_features, $price, $images_json);
    
    if ($stmt->execute()) {
        echo "<div class='alert success'>Car details submitted successfully. Waiting for admin approval.</div>";
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Car</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            padding: 2rem;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #333;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 2rem;
            position: relative;
        }

        h2::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: #4CAF50;
            margin: 10px auto;
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

        input[type="file"] {
            display: block;
            margin-top: 0.5rem;
            padding: 0.5rem;
            border: 1px dashed #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #45a049;
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

        @media (max-width: 600px) {
            body {
                padding: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add a Car</h2>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="car_name">Car Name:</label>
                <input type="text" id="car_name" name="car_name" required>
            </div>
            
            <div class="form-group">
                <label for="car_description">Description:</label>
                <textarea id="car_description" name="car_description" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="car_features">Features:</label>
                <textarea id="car_features" name="car_features" 
                    placeholder="Example:&#10;Luxury Sedan&#10;12.5 km/l&#10;Automatic&#10;5 Seats&#10;4 Bags&#10;Climate Control" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Price:</label>
                <input type="number" id="price" name="price" required>
            </div>
            
            <div class="form-group">
                <label>Upload 4 Images:</label>
                <input type="file" name="car_images[]" accept="image/*" multiple required>
            </div>
            
            <button type="submit">Submit Car Details</button>
        </form>
    </div>
</body>
</html>