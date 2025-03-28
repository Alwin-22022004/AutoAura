<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['car_id'], $_POST['car_name'], $_POST['price'])) {
    $response = ['success' => false, 'message' => ''];
    
    $car_id = intval($_POST['car_id']);
    $car_name = trim($_POST['car_name']);
    $price = floatval($_POST['price']);

    // Validate inputs
    if (empty($car_name)) {
        $response['message'] = 'Car name cannot be empty';
    } elseif ($price <= 0) {
        $response['message'] = 'Price must be greater than 0';
    } else {
        // Update car details
        $update_sql = "UPDATE cars SET car_name = ?, price = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sdi", $car_name, $price, $car_id);
        
        if ($stmt->execute()) {
            $response = [
                'success' => true,
                'message' => "Car details updated successfully!"
            ];
        } else {
            $response['message'] = "Error updating car details: " . $conn->error;
        }
        $stmt->close();
    }
} else {
    $response['message'] = "Invalid request";
}

header('Content-Type: application/json');
echo json_encode($response);
