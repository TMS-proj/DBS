<?php
require '../db.php';

$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Traffic System</title>
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="login-box">
    <div class="logo">
        <h2><i class="fas fa-traffic-light"></i> TrafficSense</h2>
        <p>Distributed Monitoring System</p>
    </div>

    <?php if(!empty($error)): ?>
        <div class="error">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form action="check_login.php" method="POST" id="loginForm" autocomplete="on">
        <div class="input-group">
            <label for="username"><i class="fas fa-user"></i> Username</label>
            <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
        </div>

        <div class="input-group">
            <label for="email"><i class="fas fa-envelope"></i> Email</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required autocomplete="email">
        </div>

        <div class="input-group">
            <label for="password"><i class="fas fa-lock"></i> Password</label>
            <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                <button type="button" class="toggle-password" id="togglePassword">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="login-btn">
            <i class="fas fa-sign-in-alt"></i> Login
        </button>
    </form>

    <div class="links">
        <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot Password?</a>
        <a href="../landing.html"><i class="fas fa-home"></i> Back to Home</a>
    </div>

    <div class="demo-credentials">
        <h4><i class="fas fa-info-circle"></i> Demo Account</h4>
        <div class="credentials">
            <button type="button" class="demo-btn" id="demoBtn">
                <i class="fas fa-user-shield"></i> Fill Demo Credentials
            </button>
        </div>
    </div>

    <div class="footer">
        <p>© <?php echo date('Y'); ?> Traffic Monitoring System</p>
        <p>v1.0 | Admin Access Only</p>
    </div>
</div>

<!-- JS -->
<script src="../js/login.js"></script>
</body>
</html>
