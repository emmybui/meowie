<?php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: forget.php");
        exit();
    }
    
    // Check if email exists in the system
    $query = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Database prepare error: " . $conn->error);
        $_SESSION['error'] = "System error, please try again later.";
        header("Location: forget.php");
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        // Generate 6-digit OTP
        $otp = sprintf("%06d", mt_rand(0, 999999));
        $expires = date("Y-m-d H:i:s", strtotime("+5 minutes")); // OTP expires in 5 minutes
        
        // Save OTP to database
        $updateQuery = "UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("sss", $otp, $expires, $email);
        
        if ($updateStmt->execute()) {
            // Send email with OTP
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
                $mail->Subject = 'MEOWIÉ Password Reset OTP';
                
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #000000; margin-bottom: 20px;'>MEOWIÉ Password Reset</h2>
                        <p>You have requested to reset your MEOWIÉ account password.</p>
                        <p>Your OTP code is:</p>
                        <h1 style='color: #000000; font-size: 32px; letter-spacing: 5px; margin: 20px 0;'>$otp</h1>
                        <p>This code will expire in 5 minutes.</p>
                        <p>If you did not request a password reset, please ignore this email.</p>
                        <hr style='margin: 20px 0; border: none; border-top: 1px solid #eee;'>
                        <p style='color: #666; font-size: 12px;'>This is an automated email, please do not reply.</p>
                    </div>
                ";
                
                $mail->send();
                // Lưu email vào session để sử dụng ở trang xác nhận OTP
                $_SESSION['reset_email'] = $email;
                $_SESSION['otp_time'] = time(); // Lưu thời gian gửi OTP
                header("Location: verify_otp.php");
                exit();
            } catch (Exception $e) {
                error_log("Email send failed: " . $mail->ErrorInfo);
                $_SESSION['error'] = "Unable to send email. Please try again later.";
                header("Location: forget.php");
                exit();
            }
        } else {
            error_log("Failed to update OTP: " . $updateStmt->error);
            $_SESSION['error'] = "System error, please try again later.";
            header("Location: forget.php");
            exit();
        }
    } else {
        // Don't disclose whether the email exists for security reasons
        $_SESSION['error'] = "If the email exists in our system, you will receive an OTP code.";
        header("Location: forget.php");
        exit();
    }
} else {
    header("Location: forget.php");
    exit();
}
?>