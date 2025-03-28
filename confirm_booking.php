<?php
session_start();
require_once 'db_connect.php';
require_once 'includes/booking_status_handler.php';

if (!isset($_POST['booking_id']) || !isset($_SESSION['user_id'])) {
    header('Location: user-profile.php');
    exit();
}

$booking_id = $_POST['booking_id'];
$user_id = $_SESSION['user_id'];

// Verify the booking belongs to the user and update status
$update_query = "UPDATE bookings 
                SET status = 'confirmed' 
                WHERE booking_id = ? 
                AND user_id = ? 
                AND status = 'pending'";

if ($stmt = $conn->prepare($update_query)) {
    $stmt->bind_param("ii", $booking_id, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Handle the status change (send email)
        handleBookingStatusChange($booking_id, 'confirmed');
        
        $_SESSION['success_message'] = "Booking confirmed successfully! A confirmation email has been sent.";
    } else {
        $_SESSION['error_message'] = "Unable to confirm booking. Please try again.";
    }
    
    $stmt->close();
} else {
    $_SESSION['error_message'] = "System error. Please try again later.";
}

header('Location: user-profile.php');
exit();
