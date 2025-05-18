<?php
session_start();
require 'config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Lấy mật khẩu hiện tại từ database
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Validate passwords
    if (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } else {
        // Cập nhật mật khẩu mới
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);

        if ($update_stmt->execute()) {
            $success = "Password changed successfully!";
            header("Refresh: 2; URL=dashboard.php");
        } else {
            $error = "Failed to update password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - MEOWIÉ</title>
    <link rel="stylesheet" href="style_change_password.css">
</head>

<body>
    <div class="wrapper">
        <p class="meowie">MEOWIÉ</p>
        <h1>Change Password</h1>

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="input-field">
                <input type="password" id="current_password" name="current_password" required placeholder=" ">
                <label for="current_password">Current Pasword</label>
                <span class="toggle-password" data-target="current_password"></span>
            </div>

            <div class="input-field">
                <input type="password" id="new_password" name="new_password" required placeholder=" ">
                <label for="new_password">New Password</label>
                <span class="toggle-password" data-target="new_password"></span>
            </div>

            <div class="input-field">
                <input type="password" id="confirm_password" name="confirm_password" required placeholder=" ">
                <label for="confirm_password">Confirm Password</label>
                <span class="toggle-password" data-target="confirm_password"></span>
            </div>

            <button type="submit">Change Password</button>
        </form>
    </div>

    <script>
        document.querySelectorAll('input[type="password"]').forEach(input => {
            const toggle = document.createElement('span');
            toggle.className = 'toggle-password';

            toggle.addEventListener('click', () => {
                input.type = input.type === 'password' ? 'text' : 'password';
            });
            input.parentNode.appendChild(toggle);
        });

        document.querySelectorAll('.toggle-password').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                this.classList.toggle('active');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                } else {
                    passwordInput.type = 'password';
                }
            });
        });
    </script>
</body>

</html>