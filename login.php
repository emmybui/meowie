<?php
session_start();

// If user is already authenticated, redirect to dashboard
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MEOWIÉ</title>
    <link rel="stylesheet" href="style_login.css">
</head>
<body>
    <div class="wrapper">
        <P class="meowie">MEOWIÉ</P>
        <form action="login_process.php" method="post" id="loginForm">
            <h2>Login</h2>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message visible" style="margin-bottom: 15px;">
                    <i class="fas fa-exclamation-circle error-icon"></i>
                    <?php
                    echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="input-field">
                <input type="email" id="email" name="email" required placeholder=" ">
                <label>Your email</label>
            </div>
            <div class="input-field">
                <div class="password-container">
                    <input type="password" id="password" name="password" required placeholder=" ">
                    <label>Password</label>
                    <span class="toggle-password"></span>
                </div>
            </div>
            <div class="forget">
                <label for="remember">
                    <input type="checkbox" id="remember" name="remember">
                    <p>Remember me</p>
                </label>
                <a href="forget.php">Forget password?</a>
            </div>
            <button type="submit">Login</button>
            <div class="register">
                <p>Don't have an account? <a href="register.html">Sign up?</a></p>
            </div>
        </form>
    </div>

    <script>
        document.querySelector('.toggle-password').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            this.classList.toggle('active');
            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
        });

        document.getElementById('loginForm').addEventListener('submit', function (event) {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            let isValid = true;

            // Xóa thông báo lỗi cũ
            document.querySelectorAll('.error-message').forEach(el => el.remove());

            // Email validation
            if (!emailInput.value.trim()) {
                showError(emailInput, 'Please enter your email');
                isValid = false;
            } else if (!isValidEmail(emailInput.value.trim())) {
                showError(emailInput, 'Invalid email format');
                isValid = false;
            }

            // Password validation
            if (!passwordInput.value.trim()) {
                showError(passwordInput, 'Please enter your password');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
            }
        });

        function showError(input, message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.style.color = '#ff4444';
            errorDiv.style.fontSize = '0.8em';
            errorDiv.style.marginTop = '5px';
            errorDiv.textContent = message;
            input.parentElement.appendChild(errorDiv);
            input.focus();
        }

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
    </script>
</body>

</html>