<?php
session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $remember = isset($_POST["remember"]) ? true : false;

    // Debug: Log login attempt
    error_log("Login attempt for email: " . $email);

    // Check if email and password are provided
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please enter both email and password!";
        header("Location: login.php");
        exit();
    }

    // Find user by email
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Database prepare error: " . $conn->error);
        $_SESSION['error'] = "System error!";
        header("Location: login.php");
        exit();
    }

    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        $_SESSION['error'] = "System error!";
        header("Location: login.php");
        exit();
    }

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Successful login
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['authenticated'] = true;
            $_SESSION['email'] = $user['email'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            
            // If user selected "Remember Me"
            if ($remember) {
                // Generate random token
                $token = bin2hex(random_bytes(32));
                
                // Save token to database
                $update_query = "UPDATE users SET remember_token = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                if ($update_stmt) {
                    $update_stmt->bind_param("si", $token, $user['id']);
                    $update_stmt->execute();
                    
                    // Create cookie with token, expires in 30 days
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                    setcookie('user_id', $user['id'], time() + (30 * 24 * 60 * 60), '/');
                }
            }
            
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid email or password!";
            error_log("Password verification failed for user: " . $email);
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Invalid email or password!";
        error_log("No user found with email: " . $email);
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>