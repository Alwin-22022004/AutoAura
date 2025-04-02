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
        $status = ($action === 'activate') ? 'active' : 'blocked';
        $update_sql = "UPDATE users SET active = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $status, $user_id);
        
        if ($stmt->execute()) {
            $response = [
                'success' => true,
                'message' => "User has been " . $status . " successfully!"
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
