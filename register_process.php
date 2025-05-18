<?php
session_start();
include('config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $email = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // Check empty fields
    if (empty($email) || empty($password) || empty($confirm_password)) {
        die("<script>alert('Please fill in all fields!'); history.back();</script>");
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("<script>alert('Invalid email format!'); history.back();</script>");
    }

    // Check password strength
    if (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || 
        !preg_match("/[0-9]/", $password) || !preg_match("/[^A-Za-z0-9]/", $password)) {
        die("<script>alert('Password is not strong enough!'); history.back();</script>");
    }

    // Check password match
    if ($password !== $confirm_password) {
        die("<script>alert('Passwords do not match!'); history.back();</script>");
    }

    // Check existing email
    $check_query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_query);
    if (!$stmt) {
        die("<script>alert('System error!'); history.back();</script>");
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        die("<script>alert('Email already exists!'); history.back();</script>");
    }

    // Generate OTP
    $_SESSION['register_email'] = $email;
    $_SESSION['register_password'] = password_hash($password, PASSWORD_DEFAULT);
    $_SESSION['otp'] = rand(100000, 999999);
    $_SESSION['otp_time'] = time(); // Add OTP creation time

    // Send OTP via email
    require 'vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

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
        $mail->Subject = 'MEOWIÉ Account Registration Verification';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #333;'>MEOWIÉ Account Registration Verification</h2>
                <p>Thank you for registering an account at MEOWIÉ. To complete the registration process, please use the following OTP code:</p>
                <div style='background-color: #f5f5f5; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;'>
                    {$_SESSION['otp']}
                </div>
                <p>This OTP code will expire in 5 minutes.</p>
                <p>If you did not make this request, please ignore this email.</p>
                <p style='color: #666; font-size: 12px; margin-top: 20px;'>This is an automated email, please do not reply.</p>
            </div>
        ";

        $mail->send();
        header("Location: verify_otp.php");
        exit();
    } catch (Exception $e) {
        die("<script>alert('Could not send email: {$mail->ErrorInfo}'); history.back();</script>");
    }
} else {
    header("Location: register.html");
    exit();
}
?>