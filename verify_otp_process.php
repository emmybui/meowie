<?php
// session_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

// Lấy OTP từ form
$userOtp = $_POST['otp'];

// Xác định loại xác thực (đăng ký hoặc quên mật khẩu)
$isRegistration = isset($_SESSION['register_email']);
$isPasswordReset = isset($_SESSION['reset_email']);

if ($isRegistration) {
    // Xử lý OTP cho đăng ký
    $sessionOtp = $_SESSION['otp'] ?? '';
    $email = $_SESSION['register_email'];
    $passwordHash = $_SESSION['register_password'];

    if ($userOtp == $sessionOtp) {
        // Kiểm tra email đã tồn tại
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        if (!$stmt) {
            die("Lỗi prepare: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $_SESSION['error'] = "Email already exists.";
            header("Location: verify_otp.php");
            exit();
        }

        // Tạo username từ email
        $username = explode('@', $email)[0];
        
        // Lưu vào DB với đúng cấu trúc bảng
        $stmt = $conn->prepare("INSERT INTO users (email, username, password) VALUES (?, ?, ?)");
        if (!$stmt) {
            die("Lỗi prepare: " . $conn->error);
        }
        $stmt->bind_param("sss", $email, $username, $passwordHash);
        
        if ($stmt->execute()) {
            // Xóa session OTP sau khi verify thành công
            unset($_SESSION['otp']);
            unset($_SESSION['register_email']);
            unset($_SESSION['register_password']);
            
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['error'] = "Error saving data: " . $stmt->error;
            header("Location: verify_otp.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Invalid OTP code. Please try again.";
        header("Location: verify_otp.php");
        exit();
    }
} elseif ($isPasswordReset) {
    // Xử lý OTP cho quên mật khẩu
    $email = $_SESSION['reset_email'];

    // Debug: Log các giá trị
    error_log("Email: " . $email);
    error_log("User OTP: " . $userOtp);

    // Kiểm tra OTP trong database
    $query = "SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $userOtp);
    $stmt->execute();
    $result = $stmt->get_result();

    // Debug: Log kết quả query
    error_log("Query result rows: " . $result->num_rows);

    if ($result->num_rows === 1) {
        // OTP hợp lệ và chưa hết hạn
        // Tạo token mới để sử dụng trong URL reset password
        $reset_token = bin2hex(random_bytes(32));
        
        // Cập nhật token mới vào database
        $update_query = "UPDATE users SET reset_token = ? WHERE email = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ss", $reset_token, $email);
        
        if ($update_stmt->execute()) {
            // Debug: Log thành công
            error_log("Reset token updated successfully");
            // Chuyển hướng đến trang reset password với token mới
            header("Location: reset_password.php?token=" . $reset_token);
            exit();
        } else {
            // Debug: Log lỗi
            error_log("Error updating reset token: " . $update_stmt->error);
            $_SESSION['error'] = "System error. Please try again.";
            header("Location: verify_otp.php");
            exit();
        }
    } else {
        // Debug: Log OTP không hợp lệ
        error_log("Invalid OTP for email: " . $email);
        $_SESSION['error'] = "Invalid or expired OTP code.";
        header("Location: verify_otp.php");
        exit();
    }
} else {
    // Không có session hợp lệ
    header("Location: login.php");
    exit();
}
?>