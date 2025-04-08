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
    SELECT b.status, b.booking_id, b.payment_status as booking_payment_status,
           p.payment_id, p.status as payment_status 
    FROM bookings b
    LEFT JOIN payments p ON b.booking_id = p.booking_id
    WHERE b.booking_id = ? AND b.user_id = ?
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

// Start transaction
$conn->begin_transaction();

try {
    // Update booking status to cancelled and payment_status to refunded if payment was captured
    $update_booking = $conn->prepare("
        UPDATE bookings 
        SET status = 'cancelled',
            payment_status = CASE 
                WHEN payment_status = 'captured' THEN 'refunded'
                ELSE payment_status 
            END
        WHERE booking_id = ? AND user_id = ?
    ");
    $update_booking->bind_param("ii", $booking_id, $user_id);
    $update_booking->execute();

    // If payment exists and is captured, update payment status to refunded
    if ($booking['payment_id'] && $booking['payment_status'] === 'captured') {
        $update_payment = $conn->prepare("
            UPDATE payments 
            SET status = 'refunded'
            WHERE payment_id = ?
        ");
        $update_payment->bind_param("s", $booking['payment_id']);
        $update_payment->execute();
    }

    // Commit transaction
    $conn->commit();
    $_SESSION['success'] = "Booking cancelled successfully" . 
        ($booking['booking_payment_status'] === 'captured' ? " and refund initiated" : "");
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error'] = "Failed to cancel booking: " . $e->getMessage();
}

header("Location: user-profile.php");
exit();