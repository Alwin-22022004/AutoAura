<?php
session_start();
require_once 'db_connect.php';

// Check for admin authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['car_id'])) {
    $car_id = intval($_POST['car_id']);
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'reject') {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        $update_sql = "UPDATE cars SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_status, $car_id);

        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode([
                "success" => true,
                "message" => "Car listing #$car_id has been $new_status successfully!",
                "new_status" => ucfirst($new_status)
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                "success" => false,
                "message" => "Error updating status: " . $conn->error
            ]);
        }
        $stmt->close();
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            "success" => false,
            "message" => "Invalid action specified"
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false,
        "message" => "Invalid request parameters"
    ]);
}
?>
