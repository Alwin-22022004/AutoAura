<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    $response = ['success' => false, 'message' => ''];
    
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];

    if ($action === 'activate' || $action === 'block') {
        $is_active = ($action === 'activate') ? 1 : 0;
        $update_sql = "UPDATE users SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $is_active, $user_id);
        
        if ($stmt->execute()) {
            $response = [
                'success' => true,
                'message' => "User has been " . ($is_active ? 'activated' : 'blocked') . " successfully!"
            ];
        } else {
            $response['message'] = "Error updating user status: " . $conn->error;
        }
        $stmt->close();
    } else {
        $response['message'] = "Invalid action";
    }
} else {
    $response['message'] = "Invalid request";
}

header('Content-Type: application/json');
echo json_encode($response);
