<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

try {
    // Select all users but exclude the PDF content for efficiency
    $sql = "SELECT id, fullname, email, mobile, 
            CASE WHEN verification_doc IS NOT NULL AND verification_doc != '' AND verification_doc != 'null' THEN 1 ELSE 0 END as has_document,
            created_at 
            FROM users 
            ORDER BY created_at DESC";
            
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception($conn->error);
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Convert the has_document to a boolean for the frontend
        $row['verification_doc'] = $row['has_document'] == 1;
        unset($row['has_document']);
        
        // Format the created_at date
        if ($row['created_at']) {
            $date = new DateTime($row['created_at']);
            $row['created_at'] = $date->format('Y-m-d H:i:s');
        }
        
        $users[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($users);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
