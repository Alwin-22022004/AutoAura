<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// CSRF Protection
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['car_id'])) {
    $car_id = filter_var($_POST['car_id'], FILTER_VALIDATE_INT);
    
    if ($car_id === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid car ID']);
        exit();
    }

    // Get current status
    $stmt = $conn->prepare("SELECT is_active FROM cars WHERE id = ?");
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $car = $result->fetch_assoc();

    if (!$car) {
        echo json_encode(['success' => false, 'message' => 'Car not found']);
        exit();
    }

    // Toggle status
    $new_status = $car['is_active'] ? 0 : 1;
    $stmt = $conn->prepare("UPDATE cars SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $car_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'new_status' => $new_status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating status']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>
