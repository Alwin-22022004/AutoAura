<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendMail($to, $subject, $htmlBody, $altBody = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 0;                      // Enable verbose debug output (set to 2 for debugging)
        $mail->isSMTP();                          
        $mail->Host       = 'smtp.gmail.com';      
        $mail->SMTPAuth   = true;                  
        $mail->Username   = 'autoauracars@gmail.com';
        $mail->Password   = 'vqqjrpmrjsnlgmjf';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  
        $mail->Port       = 465;                   
        
        // Disable SSL verification (only if needed for testing)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom('autoauracars@gmail.com', 'Car Rental');
        $mail->addAddress($to);     // Add recipient
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags($htmlBody);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        throw new Exception("Failed to send email: " . $e->getMessage());
    }
}

// Helper function to generate OTP email content
function generateOTPEmailContent($otp, $type = 'verification') {
    $title = $type === 'verification' ? 'Email Verification' : 'Password Reset';
    
    $htmlBody = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='background-color: #f6f6f6; padding: 20px;'>
                <h2 style='color: #333;'>{$title}</h2>
                <p>Your OTP for {$type} is:</p>
                <h1 style='color: #007bff; font-size: 32px; letter-spacing: 5px;'>{$otp}</h1>
                <p>This OTP will expire in 10 minutes.</p>
                <p style='color: #666; font-size: 12px;'>If you didn't request this OTP, please ignore this email.</p>
            </div>
        </body>
        </html>";
    
    $altBody = "Your OTP for {$type} is: {$otp}. This OTP will expire in 10 minutes.";
    
    return ['html' => $htmlBody, 'text' => $altBody];
}
?>
