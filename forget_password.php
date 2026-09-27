<?php
// Ensure you are using the correct settings file
require_once 'config.php';
$conn = getDatabaseConnection(); // Ensure connection variable matches what your script uses 
require_once 'functions.php';

// Initialize variables to prevent undefined variable warnings in HTML
$error = null;
$email_sent = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $email = trim($_POST['email']);

    // FIXED: Changed 'name' to 'full_name' to match the users table schema
    $stmt = $conn->prepare("SELECT user_id, email, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Generate a secure password reset token
        $token = bin2hex(random_bytes(50));
        
       // Store token and expiration (1 hour from now)
$store_token = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expire = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
$store_token->bind_param("ss", $token, $email);
        
        if ($store_token->execute()) {
            // FIXED: Changed 'reset-password.php' to 'reset_password.php' to match your directory
          $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/civicpulse/reset_password.php?token=" . $token;
            $message = "Hello " . $user['full_name'] . ", please click the link to reset your password: " . $reset_link;
            
            // Update variable to trigger success UI instead of echoing JS alerts
            $email_sent = true;
        } else {
            $error = "System error: Could not generate reset token.";
        }
    } else {
        // Update variable to trigger error UI instead of echoing JS alerts
        $error = "Email not found in our system.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forget Password - CivicPulse</title>
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
        
        .header p {
            color: #666;
            font-size: 14px;
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
        
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus {
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
        
        .info-box {
            background: #f0f4ff;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #555;
            line-height: 1.6;
            border-left: 4px solid #667eea;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 5px;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Forget Password</h1>
            <p>Reset your password</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ✗ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($email_sent): ?>
            <div class="alert alert-success">
                ✓ If an account exists with this email, a password reset link has been sent!
            </div>
            <div class="info-box">
                📧 Check your email (including spam folder) for the password reset link.<br>
                The link will expire in 1 hour.
            </div>
            <div class="footer-links">
                <a href="login.php">← Back to Login</a>
            </div>
        <?php else: ?>
            <div class="info-box">
                📧 Enter your email address and we'll send you a password reset link.
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="your@email.com" 
                        required
                        autofocus
                    >
                </div>
                
                <button type="submit">Send Reset Link</button>
            </form>
            
            <div class="footer-links">
                <a href="login.php">← Back to Login</a> | 
                <a href="login.php?tab=register">Create Account</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>