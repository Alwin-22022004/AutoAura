<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Prepare and execute the query
    $query = "SELECT id, fullname, email, mobile, verification_doc, created_at FROM users ORDER BY created_at DESC";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception($conn->error);
    }

    // Fetch all users
    $users = array();
    while ($row = $result->fetch_assoc()) {
        // If verification_doc is a file path, make sure it's properly formatted for web access
        if ($row['verification_doc']) {
            $row['verification_doc'] = 'uploads/' . basename($row['verification_doc']);
        }
        
        // Format the created_at date
        if ($row['created_at']) {
            $date = new DateTime($row['created_at']);
            $row['created_at'] = $date->format('Y-m-d H:i:s');
        }
        
        $users[] = $row;
    }

    // Return the users as JSON
    echo json_encode($users);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
