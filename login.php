<?php
/**
 * CIVICPULSE - Login / Registration
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$activeTab = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $activeTab = 'login';
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = loginUser($email, $password);
        if ($result['success']) {
            // --- RECORD LAST LOGIN FOR FAILOVER ROUTING (MYSQLI) ---
            if (isset($_SESSION['user_id'])) {
                $conn = getDatabaseConnection();
                $updateLogin = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                if ($updateLogin) {
                    $updateLogin->bind_param("i", $_SESSION['user_id']);
                    $updateLogin->execute();
                    $updateLogin->close();
                }
                $actionType = "Login";
                $logDesc = "User account logged into the portal.";
                $logStmt = $conn->prepare("INSERT INTO activity_logs (user_name, action_type, description) VALUES (?, ?, ?)");
                if ($logStmt) {
                    $logStmt->bind_param("sss", $email, $actionType, $logDesc);
                    $logStmt->execute();
                    $logStmt->close();
                }
            }
            // ------------------------------------------------------

            header("Location: dashboard.php");
            exit();
        }
        $error = $result['message'];

    } elseif ($action === 'register') {
        $activeTab = 'register';
        $email = sanitizeInput($_POST['reg_email'] ?? '');
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $password = $_POST['reg_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            $result = registerUser($email, $password, $fullName, $phone);
            if ($result['success']) {
                $success = 'Registration successful! Please log in with your new account.';
                $activeTab = 'login';
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CivicPulse - Municipal Infrastructure Tracking</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-box">
            <div class="header">
                <div class="logo-big">🏛️ CivicPulse</div>
                <div class="tagline">Municipal Infrastructure Tracking &amp; Analytics Portal</div>
            </div>

            <div class="content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><strong>Error:</strong> <?= e($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><strong>Success:</strong> <?= e($success) ?></div>
                <?php endif; ?>

                <div class="tabs">
                    <button type="button" class="tab-button <?= $activeTab === 'login' ? 'active' : '' ?>" onclick="switchTab('login', this)">Login</button>
                    <button type="button" class="tab-button <?= $activeTab === 'register' ? 'active' : '' ?>" onclick="switchTab('register', this)">Register</button>
                </div>

                <div id="login" class="tab-content <?= $activeTab === 'login' ? 'active' : '' ?>">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required placeholder="your@email.com">
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required placeholder="Enter your password">
                           <a href="/civicpulse/forget_password.php" style="font-size: 12px; color: #667eea; margin-top: 8px; display: inline-block;"> Forget Password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width:100%;">Login</button>
                    </form>
                </div>

                <div id="register" class="tab-content <?= $activeTab === 'register' ? 'active' : '' ?>">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="register">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" required placeholder="Your full name" value="<?= e($_POST['full_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="reg_email">Email Address</label>
                            <input type="email" id="reg_email" name="reg_email" required placeholder="your@email.com" value="<?= e($_POST['reg_email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" placeholder="98XXXXXXXX" value="<?= e($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="reg_password">Password</label>
                            <input type="password" id="reg_password" name="reg_password" required minlength="6" placeholder="At least 6 characters">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;">Create Account</button>
                        <p class="helper-text" style="margin-top:10px;">New accounts are registered as citizens. Staff and admin accounts are created by an administrator.</p>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName, btn) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            btn.classList.add('active');
        }
    </script>
</body>
</html>