<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['rating']) || !isset($data['review'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$user_id = $_SESSION['user_id'];
$rating = (int)$data['rating'];
$review = trim($data['review']);

// Validate data
if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid rating']);
    exit();
}

if (empty($review)) {
    http_response_code(400);
    echo json_encode(['error' => 'Review text cannot be empty']);
    exit();
}

// Insert review into database
$stmt = $conn->prepare("INSERT INTO reviews (user_id, rating, review_text) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $user_id, $rating, $review);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to submit review',
        'details' => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
