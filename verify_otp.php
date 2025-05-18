<?php 
session_start(); 
require 'config.php';

// Check if email exists in session
if (!isset($_SESSION['reset_email']) && !isset($_SESSION['register_email'])) {
    header("Location: login.php");
    exit();
}

// Determine which type of verification this is
$email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : $_SESSION['register_email'];
$isPasswordReset = isset($_SESSION['reset_email']);

// If this is a direct POST to this page (not using verify_otp_process.php)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['otp'])) {
    $otp = $_POST['otp'];
    
    if ($isPasswordReset) {
        // Kiểm tra OTP cho quên mật khẩu
        $query = "SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW()";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // OTP hợp lệ, tạo token mới cho reset password
            $reset_token = bin2hex(random_bytes(32));
            $update_query = "UPDATE users SET reset_token = ? WHERE email = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ss", $reset_token, $email);
            
            if ($update_stmt->execute()) {
                header("Location: reset_password.php?token=" . $reset_token);
                exit();
            } else {
                $_SESSION['error'] = "System error. Please try again.";
            }
        } else {
            $_SESSION['error'] = "Invalid or expired OTP code.";
        }
    } else {
        // Xử lý OTP cho đăng ký
        if ($otp === $_SESSION['otp']) {
            $_SESSION['verified_email'] = $email;
            header("Location: register_complete.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid OTP code.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - MEOWIÉ</title>
    <link rel="stylesheet" href="style_forgot_password.css">
    <style>
        #resendBtn {
            color:rgb(0, 0, 0);
            text-decoration: none;
            cursor: pointer;
        }
        #resendBtn:hover {
            text-decoration: underline;
        }
        #resendBtn:disabled {
            color: #6c757d;
            cursor: not-allowed;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h1 class="meowie">MEOWIÉ</h1>
        <h2>Verify OTP</h2>
        
        <p class="email-sent">
            We've sent a verification code to<br>
            <span class="email-highlight"><?php echo htmlspecialchars($email); ?></span>
        </p>
        
        <form action="verify_otp_process.php" method="post" id="otpForm">
            <div class="otp-inputs">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                <input type="text" 
                       name="digit<?php echo $i; ?>"
                       class="otp-input" 
                       maxlength="1" 
                       pattern="[0-9]"
                       inputmode="numeric"
                       required>
                <?php endfor; ?>
                <input type="hidden" name="otp" id="otpValue">
            </div>

            <div class="timer">
                Time remaining: <span id="countdown">5:00</span>
            </div>

            <button type="submit">Verify OTP</button>
        </form>

        <div class="back-to-login">
            <p>Didn't receive the code? 
                <a href="<?php echo $isPasswordReset ? 'forget_process.php' : 'register_process.php'; ?>" 
                   class="resend-link" id="resendLink">Resend</a>
                <span id="resendCountdown"></span>
            </p>
        </div>
    </div>

    <script>
        // OTP input handling
        const otpInputs = document.querySelectorAll('.otp-input');
        const form = document.getElementById('otpForm');
        const otpValue = document.getElementById('otpValue');
        
        otpInputs.forEach((input, index) => {
            // Chỉ cho phép nhập số
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                if (e.target.value.length === 1) {
                    if (index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });

            // Cho phép paste OTP
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
                for (let i = 0; i < Math.min(pastedData.length, otpInputs.length - index); i++) {
                    otpInputs[index + i].value = pastedData[i];
                }
                if (index + pastedData.length < otpInputs.length) {
                    otpInputs[index + pastedData.length].focus();
                }
            });
        });

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const otp = Array.from(otpInputs).map(input => input.value).join('');
            if (otp.length === 6 && /^\d+$/.test(otp)) {
                otpValue.value = otp;
                form.submit();
            } else {
                alert('Please enter a valid 6-digit OTP code');
            }
        });

        // Countdown timer
        let timeLeft = <?php echo isset($_SESSION['otp_time']) ? max(0, 300 - (time() - $_SESSION['otp_time'])) : 300; ?>;
        const countdownElement = document.getElementById('countdown');
        
        function updateCountdown() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 60) {
                countdownElement.classList.add('expiring');
            }
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                window.location.href = '<?php echo $isPasswordReset ? "forget.php" : "register.php"; ?>';
            }
            timeLeft--;
        }

        const timerInterval = setInterval(updateCountdown, 1000);
        updateCountdown();

        // Show notifications if they exist
        <?php if (isset($_SESSION['error'])): ?>
        const notification = document.createElement('div');
        notification.className = 'notification error';
        notification.textContent = '<?php echo addslashes($_SESSION['error']); ?>';
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.5s ease-out';
            setTimeout(() => notification.remove(), 500);
        }, 5000);
        <?php unset($_SESSION['error']); endif; ?>
    </script>
</body>
</html>
