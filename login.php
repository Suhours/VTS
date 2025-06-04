<?php
session_start();
include 'db.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function log_action($conn, $user_id, $action, $page, $details = '') {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        $ip = 'unknown';
    }

    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, page, details, ip) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $action, $page, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

$maxAttempts = 3;
$lockTime = 60;

if (!isset($_SESSION['failed_attempts'])) $_SESSION['failed_attempts'] = 0;
if (!isset($_SESSION['last_attempt_time'])) $_SESSION['last_attempt_time'] = 0;

$remaining = $lockTime - (time() - $_SESSION['last_attempt_time']);
if ($_SESSION['failed_attempts'] >= $maxAttempts && $remaining > 0) {
    $error = "⛔ Too many login attempts. Try again in $remaining seconds.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['failed_attempts'] = 0;
        $_SESSION['last_attempt_time'] = 0;

        log_action($conn, $user['id'], 'Login', 'login.php', 'User logged in');

        if ($user['role'] === 'Admin') {
            header("Location: dashboard/dashboard");
            exit;
        } elseif ($user['role'] === 'User') {
            header("Location: users/dashboard_user");
            exit;
        }
    } else {
        $_SESSION['failed_attempts']++;
        $_SESSION['last_attempt_time'] = time();
        $attemptsLeft = $maxAttempts - $_SESSION['failed_attempts'];
        if ($attemptsLeft <= 0) {
            $error = "⛔ Too many login attempts. Try again in $lockTime seconds.";
        } else {
            $error = "❌ Login failed. You have $attemptsLeft attempt(s) left.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
      overflow: hidden;
      animation: fadeIn 1.5s ease-in-out;
    }
    body::before {
      content: "";
      position: absolute;
      top: 0; left: 0;
      height: 100%; width: 100%;
      background: url('img/blurr.png') no-repeat center center fixed;
      background-size: cover;
      filter: blur(8px);
      z-index: -2;
    }
    body::after {
      content: "";
      position: absolute;
      top: 0; left: 0;
      height: 100%; width: 100%;
      background-color: rgba(0, 0, 0, 0.4);
      z-index: -1;
    }
    .login-box {
      background: white;
      padding: 40px;
      border-radius: 25px;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
      text-align: center;
      max-width: 400px;
      width: 100%;
      position: relative;
      z-index: 1;
      animation: fadeIn 1s ease;
    }
    .login-box img {
      width: 80px;
      margin-bottom: 20px;
    }
    .login-box h2 {
      color: #007bff;
      margin-bottom: 30px;
    }
    .login-box input[type="text"],
    .login-box input[type="password"] {
      width: 100%;
      padding: 12px 15px;
      margin: 10px 0;
      border: 2px solid #007bff;
      border-radius: 25px;
    }
    .login-box button {
      width: 100%;
      padding: 12px;
      background: #007bff;
      border: none;
      border-radius: 25px;
      color: white;
      font-weight: bold;
      font-size: 16px;
      margin-top: 20px;
      cursor: pointer;
    }
    .login-box button:hover {
      background: #0056b3;
    }
    .error-message {
      color: red;
    }
    .rejected-message {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
    }
    a {
      display: inline-block;
      margin-top: 15px;
      color: #007bff;
      text-decoration: none;
    }
    a:hover {
      text-decoration: underline;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    .password-wrapper {
      position: relative;
    }
    .password-wrapper i {
      position: absolute;
      right: 5px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #007bff;
    }
  </style>
</head>
<body>

  <form method="POST" class="login-box">
    <img src="img/logo.png" alt="Logo">
    <h2>Login</h2>

    <?php if (isset($_GET['rejected'])): ?>
      <div class="rejected-message">❌ Your password reset request was rejected by the admin.</div>
    <?php endif; ?>

    <?php if (isset($error)) echo "<p class='error-message'>$error</p>"; ?>

    <input type="text" name="username" id="username" placeholder="Username" required>

    <div class="password-wrapper">
      <input type="password" name="password" id="password" placeholder="Password" required>
      <i class="fa-solid fa-eye" id="toggleIcon" onclick="togglePassword()"></i>
    </div>

    <button type="submit">Login</button>

    <a href="forgetpassword/forget_password.php">Forgot Password?</a>
  </form>

  <script>
    function togglePassword() {
      const pwdInput = document.getElementById('password');
      const icon = document.getElementById('toggleIcon');
      if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        pwdInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }

    // Save username on form submit
    document.querySelector('form').addEventListener('submit', function () {
      const username = document.getElementById('username').value;
      localStorage.setItem('savedUsername', username);
    });

    // Auto-fill username if saved
    window.onload = function () {
      const saved = localStorage.getItem('savedUsername');
      if (saved) {
        document.getElementById('username').value = saved;
      }
    };
  </script>

</body>
</html>
