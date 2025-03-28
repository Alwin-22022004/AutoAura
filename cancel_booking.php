<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-page.php");
    exit();
}

require_once 'db_connect.php';

// Check if booking ID is provided
if (!isset($_POST['booking_id'])) {
    $_SESSION['error'] = "No booking ID provided";
    header("Location: user-profile.php");
    exit();
}

$booking_id = $_POST['booking_id'];
$user_id = $_SESSION['user_id'];

// Verify that the booking belongs to the user and is not already cancelled
$check_query = $conn->prepare("
    SELECT status 
    FROM bookings 
    WHERE booking_id = ? AND user_id = ?
");
$check_query->bind_param("ii", $booking_id, $user_id);
$check_query->execute();
$result = $check_query->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Invalid booking ID";
    header("Location: user-profile.php");
    exit();
}

$booking = $result->fetch_assoc();
if ($booking['status'] === 'cancelled') {
    $_SESSION['error'] = "Booking is already cancelled";
    header("Location: user-profile.php");
    exit();
}

// Update booking status to cancelled
$update_query = $conn->prepare("
    UPDATE bookings 
    SET status = 'cancelled' 
    WHERE booking_id = ? AND user_id = ?
");
$update_query->bind_param("ii", $booking_id, $user_id);

if ($update_query->execute()) {
    $_SESSION['success'] = "Booking cancelled successfully";
} else {
    $_SESSION['error'] = "Failed to cancel booking";
}

header("Location: user-profile.php");
exit();