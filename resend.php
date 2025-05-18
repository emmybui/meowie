<?php
session_start();
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Check if email exists in session
$email = $_SESSION['register_email'] ?? '';
if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Registration information not found.']);
    exit();
}

// Check waiting time between OTP sends (60 seconds)
if (isset($_SESSION['last_otp_sent']) && (time() - $_SESSION['last_otp_sent'] < 60)) {
    $waitTime = 60 - (time() - $_SESSION['last_otp_sent']);
    echo json_encode([
        'success' => false, 
        'message' => "Please wait {$waitTime} seconds before requesting a new OTP."
    ]);
    exit();
}

// Generate new OTP
$_SESSION['otp'] = rand(100000, 999999);
$_SESSION['otp_time'] = time();
$_SESSION['last_otp_sent'] = time();

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'himawariteeensociu@gmail.com';
    $mail->Password = 'tqle dnmb xqxv lzoh';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('himawariteeensociu@gmail.com', 'MEOWIÉ Support');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'New OTP Code - MEOWIÉ';
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Your New OTP Code</h2>
            <p>You have requested a new OTP code. Please use the following code:</p>
            <div style='background-color: #f5f5f5; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;'>
                {$_SESSION['otp']}
            </div>
            <p>This OTP code will expire in 5 minutes.</p>
            <p>If you did not make this request, please ignore this email.</p>
            <p style='color: #666; font-size: 12px; margin-top: 20px;'>This is an automated email, please do not reply.</p>
        </div>
    ";

    $mail->send();
    echo json_encode([
        'success' => true, 
        'message' => 'New OTP code has been sent! Please check your email.'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => "Could not send email: {$mail->ErrorInfo}"
    ]);
}
?>
