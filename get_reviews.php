<?php
require_once 'db_connect.php';

// Fetch reviews with user information
$sql = "SELECT r.*, u.fullname 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY r.created_at DESC 
        LIMIT 10";

$result = $conn->query($sql);
$reviews = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = [
            'fullname' => htmlspecialchars($row['fullname']),
            'rating' => (int)$row['rating'],
            'review_text' => htmlspecialchars($row['review_text']),
            'created_at' => $row['created_at']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($reviews);

$conn->close();
?>
