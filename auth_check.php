<?php
session_start();

function checkAuth() {
    // Kiểm tra session
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        return true;
    }

    // Kiểm tra remember token
    if (isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id'])) {
        include('config.php');
        
        $token = $_COOKIE['remember_token'];
        $user_id = $_COOKIE['user_id'];
        
        $query = "SELECT * FROM users WHERE id = ? AND remember_token = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $user_id, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Tạo session mới
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['email'] = $user['email'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            
            return true;
        }
        
        // Nếu token không hợp lệ, xóa cookies
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');
    }
    
    return false;
}
?> 