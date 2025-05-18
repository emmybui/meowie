<?php
session_start();
require 'connect_db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $currentTime = date("Y-m-d H:i:s");

    // Kiểm tra token hợp lệ
    $query = "SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $token, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows != 1) {
        $_SESSION['error'] = "Invalid or expired password reset link.";
        header("Location: login.php");
        exit();
    }

    // Kiểm tra mật khẩu mới
    if ($newPassword !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset_password.php?token=$token");
        exit();
    }

    // Kiểm tra độ mạnh mật khẩu
    if (strlen($newPassword) < 8 || !preg_match("/[A-Z]/", $newPassword) || 
        !preg_match("/[0-9]/", $newPassword) || !preg_match("/[^A-Za-z0-9]/", $newPassword)) {
        $_SESSION['error'] = "Password must be at least 8 characters long and contain at least one uppercase letter, one number, and one special character.";
        header("Location: reset_password.php?token=$token");
        exit();
    }

    // Cập nhật mật khẩu mới
    $user = $result->fetch_assoc();
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $updateQuery = "UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $hashedPassword, $user['id']);
    
    if ($updateStmt->execute()) {
        $_SESSION['message'] = "Your password has been reset successfully. Please login with your new password.";
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to reset password. Please try again.";
        header("Location: reset_password.php?token=$token");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>