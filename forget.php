<?php
session_start();
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forget password - MEOWIÉ</title>
    <link rel="stylesheet" href="style_forgot_password.css">
</head>
<body>
    <div class="wrapper">
        <P class="meowie">MEOWIÉ</P>
        <form action="forget_process.php" method="post" id="forgetForm">
            <h2>Forget password</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?php 
                echo htmlspecialchars($_SESSION['error']); 
                unset($_SESSION['error']);
                ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <?php 
                echo htmlspecialchars($_SESSION['success']); 
                unset($_SESSION['success']);
                ?>
            </div>
            <?php endif; ?>

            <div class="input-field">
                <input type="email" id="email" name="email" required placeholder=" ">
                <label for="email">Insert your email here</label>
            </div>
            <button type="submit">Send OTP Code</button>
            <div class="back-to-login">
                <p>Remember your password? <a href="login.php">Login</a></p>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('forgetForm').addEventListener('submit', function(event) {
            const emailInput = document.getElementById('email');
            const email = emailInput.value.trim();
            
            if (!email) {
                event.preventDefault();
                alert('Please enter your email');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                event.preventDefault();
                alert('Please enter a valid email address');
                return;
            }
        });
    </script>
</body>
</html>