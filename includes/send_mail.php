<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

function sendBookingConfirmationEmail($to_email, $user_name, $car_name, $start_date, $end_date, $booking_id) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'autoauracars@gmail.com'; // Replace with your Gmail
        $mail->Password = 'vqqjrpmrjsnlgmjf'; // Replace with your Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('autoauracars@gmail.com', 'CAR RENTAL');
        $mail->addAddress($to_email, $user_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Booking Confirmation - AUTOAURA #' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);

        // Email body
        $body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; margin-bottom: 30px;">
                <img src="cid:logo" alt="AUTOAURA" style="max-width: 200px;">
            </div>
            
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <h2 style="color: #333; margin-bottom: 20px;">Booking Confirmation</h2>
                <p style="color: #666;">Dear ' . htmlspecialchars($user_name) . ',</p>
                <p style="color: #666;">Your car rental booking has been confirmed! Here are your booking details:</p>
            </div>

            <div style="background-color: #fff; padding: 20px; border-radius: 10px; border: 1px solid #ddd;">
                <h3 style="color: #f5b754; margin-bottom: 15px;">Booking Details</h3>
                <p><strong>Booking ID:</strong> #' . str_pad($booking_id, 6, '0', STR_PAD_LEFT) . '</p>
                <p><strong>Car:</strong> ' . htmlspecialchars($car_name) . '</p>
                <p><strong>Start Date:</strong> ' . date('d M Y', strtotime($start_date)) . '</p>
                <p><strong>End Date:</strong> ' . date('d M Y', strtotime($end_date)) . '</p>
            </div>

            <div style="margin-top: 30px; text-align: center; color: #666;">
                <p>Thank you for choosing AUTOAURA !</p>
                <p>If you have any questions, please don\'t hesitate to contact us.</p>
            </div>

            <div style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px; text-align: center; color: #999; font-size: 12px;">
                <p>This is an automated email, please do not reply.</p>
                <p>&copy; ' . date('Y') . ' AUTOAURA. All rights reserved.</p>
            </div>
        </div>';

        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $body));

        // Add logo
        $mail->addEmbeddedImage(__DIR__ . '/../assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png', 'logo');

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}
