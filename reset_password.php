<?php
session_start();
require 'config.php';

// Check token
if (!isset($_GET['token'])) {
    header("Location: login.php");
    exit();
}

$token = $_GET['token'];
$current_time = date("Y-m-d H:i:s");

// Check if token is valid and not expired
$query = "SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $token, $current_time);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Link reset password is invalid or expired.";
    header("Location: login.php");
    exit();
}

// If form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password
    if (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: reset_password.php?token=" . $token);
        exit();
    }
    
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Password confirmation does not match.";
        header("Location: reset_password.php?token=" . $token);
        exit();
    }
    
    // Hash password and update
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $update_query = "UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE reset_token = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ss", $hashed_password, $token);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Password has been reset successfully. Please log in.";
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['error'] = "An error occurred. Please try again.";
        header("Location: reset_password.php?token=" . $token);
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MEOWIÉ</title>
    <link rel="stylesheet" href="style_forgot_password.css">
</head>
<body>
    <div class="wrapper">
        <P class="meowie">MEOWIÉ</P>
        <h2>Reset Password</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="error-message">
            <?php 
            echo htmlspecialchars($_SESSION['error']); 
            unset($_SESSION['error']);
            ?>
        </div>
        <?php endif; ?>

        <form method="post" id="resetForm">
            <div class="input-field">
                <input type="password" id="password" name="password" required placeholder=" ">
                <label for="password">New Password</label>
            </div>
            
            <div class="input-field">
                <input type="password" id="confirm_password" name="confirm_password" required placeholder=" ">
                <label for="confirm_password">Confirm Password</label>
            </div>
            
            <button type="submit">Reset Password</button>
        </form>
    </div>

    <script>
        document.getElementById('resetForm').addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password.length < 8) {
                event.preventDefault();
                alert('Password must be at least 8 characters long.');
                return;
            }
            
            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Password confirmation does not match.');
                return;
            }
        });
    </script>
</body>
</html>