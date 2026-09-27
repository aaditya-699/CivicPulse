<?php
// reset-password.php
require 'config.php';
require 'functions.php';

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = false;
$token = $_GET['token'] ?? '';
$valid_token = false;

// Validate token
if (!empty($token)) {
    // Check if token exists and is not expired
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expire > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $valid_token = true;
    } else {
        $error = 'Password reset link has expired or is invalid. Please request a new one.';
    }
} else {
    $error = 'Invalid or missing reset token';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token && empty($error)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';
    
    // Validate passwords
    if (empty($password)) {
        $error = 'Password is required';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Hash password with bcrypt
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Update password and clear reset token
        $stmt = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expire = NULL WHERE reset_token = ?");
        $stmt->bind_param("ss", $password_hash, $token);
        
        if ($stmt->execute()) {
            $success = true;
            logActivity(null, 'PASSWORD_RESET_SUCCESS', 'users', 0, 'Password reset successfully via email link');
            
            // Redirect after 2 seconds
            echo '<meta http-equiv="refresh" content="2;url=login.php">';
        } else {
            $error = 'An error occurred while resetting your password. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - CivicPulse</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 20px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .info-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .strength-indicator {
            margin-top: 5px;
            padding: 5px;
            background: #f8f9fa;
            border-radius: 3px;
            font-size: 12px;
            color: #666;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .footer-links a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 Set New Password</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✓ Password reset successfully! Redirecting to login in 2 seconds...
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <p>If not redirected, <a href="login.php">click here to login</a></p>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-error">
                ✗ <?php echo htmlspecialchars($error); ?>
            </div>
            <div class="footer-links">
                <a href="forget-password.php">← Request new reset link</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password">New Password *</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter new password" 
                        required
                        minlength="8"
                        autofocus
                    >
                    <div class="info-text">Minimum 8 characters</div>
                    <div class="strength-indicator">
                        Strength: <span id="strength-text">Weak</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Re-enter your password" 
                        required
                    >
                </div>
                
                <button type="submit">Reset Password</button>
            </form>
            
            <div class="footer-links">
                <a href="login.php">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const pwd = this.value;
                const strengthText = document.getElementById('strength-text');
                
                if (pwd.length < 8) {
                    strengthText.textContent = 'Too Short';
                    strengthText.style.color = 'red';
                } else if (/[A-Z]/.test(pwd) && /[0-9]/.test(pwd) && /[!@#$%^&*]/.test(pwd)) {
                    strengthText.textContent = 'Strong ✓';
                    strengthText.style.color = 'green';
                } else if (/[A-Z]/.test(pwd) || /[0-9]/.test(pwd)) {
                    strengthText.textContent = 'Medium';
                    strengthText.style.color = 'orange';
                } else {
                    strengthText.textContent = 'Weak';
                    strengthText.style.color = 'red';
                }
            });
        }
        
        // Validate passwords match before submit
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const pwd = document.getElementById('password').value;
                const confirm = document.getElementById('confirm_password').value;
                
                if (pwd !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
            });
        }
    </script>
</body>
</html>