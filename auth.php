<?php
/**
 * CIVICPULSE - Authentication Module
 * Session handling, login/registration, role checks, login throttling.
 */

require_once __DIR__ . '/config.php';

// ============================================================================
// SESSION CONFIGURATION
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Idle timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// ============================================================================
// LOGIN THROTTLING
// ============================================================================

function isLockedOut($email) {
    $db = getDatabaseConnection();
    $since = date('Y-m-d H:i:s', time() - LOCKOUT_DURATION);

    $sql = "SELECT COUNT(*) AS attempts FROM activity_log
            WHERE action_type = 'LOGIN_FAILED' AND new_value = ? AND action_timestamp > ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ss", $email, $since);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ((int)$row['attempts']) >= MAX_LOGIN_ATTEMPTS;
}

function recordFailedLogin($email) {
    logActivity(null, 'LOGIN_FAILED', 'users', 0, $email);
}

// ============================================================================
// AUTHENTICATION FUNCTIONS
// ============================================================================

function registerUser($email, $password, $fullName, $phone) {
    $db = getDatabaseConnection();

    if (!isValidEmail($email)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    if (empty($fullName)) {
        return ['success' => false, 'message' => 'Full name is required'];
    }

    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if ($exists) {
        return ['success' => false, 'message' => 'Email already registered'];
    }

    $passwordHash = hashPassword($password);
    $role = 'citizen';

    $stmt = $db->prepare(
        "INSERT INTO users (email, password_hash, full_name, phone_number, user_role)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssss", $email, $passwordHash, $fullName, $phone, $role);

    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        $stmt->close();
        logActivity($userId, 'REGISTRATION', 'users', $userId, "New citizen registered: $email");
        return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
    }

    $stmt->close();
    return ['success' => false, 'message' => 'Registration failed. Please try again.'];
}

function loginUser($email, $password) {
    if (!isValidEmail($email)) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    if (isLockedOut($email)) {
        return ['success' => false, 'message' => 'Too many failed attempts. Please try again in a few minutes.'];
    }

    $db = getDatabaseConnection();
    $sql = "SELECT user_id, email, password_hash, full_name, user_role, is_active
            FROM users WHERE email = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        recordFailedLogin($email);
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user['is_active']) {
        recordFailedLogin($email);
        return ['success' => false, 'message' => 'This account has been deactivated. Contact an administrator.'];
    }

    if (!verifyPassword($password, $user['password_hash'])) {
        recordFailedLogin($email);
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    // Prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['user_role'];
    $_SESSION['logged_in'] = true;

    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $updateStmt->bind_param("i", $user['user_id']);
    $updateStmt->execute();
    $updateStmt->close();

    logActivity($user['user_id'], 'LOGIN', 'users', $user['user_id'], '');

    return ['success' => true, 'message' => 'Login successful', 'user_role' => $user['user_role']];
}

function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id'], '');
    }
    $_SESSION = [];
    session_destroy();
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return getUserById(getCurrentUserId());
}

function hasRole($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    $userRole = getCurrentUserRole();
    return $userRole === 'admin' || $userRole === $requiredRole;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header("Location: unauthorized.php");
        exit();
    }
}

function changePassword($userId, $oldPassword, $newPassword) {
    $db = getDatabaseConnection();

    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'New password must be at least 6 characters'];
    }

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        return ['success' => false, 'message' => 'User not found'];
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if (!verifyPassword($oldPassword, $user['password_hash'])) {
        logActivity($userId, 'PASSWORD_CHANGE_FAILED', 'users', $userId, 'Incorrect current password');
        return ['success' => false, 'message' => 'Current password is incorrect'];
    }

    $newHash = hashPassword($newPassword);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->bind_param("si", $newHash, $userId);

    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'PASSWORD_CHANGED', 'users', $userId, '');
        return ['success' => true, 'message' => 'Password changed successfully'];
    }

    $stmt->close();
    return ['success' => false, 'message' => 'Password change failed'];
}

function getUserById($userId) {
    $db = getDatabaseConnection();
    $sql = "SELECT user_id, email, full_name, phone_number, user_role, address, city, postal_code,
                   is_active, created_at, last_login
            FROM users WHERE user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

function updateUserProfile($userId, $fullName, $phone, $address, $city, $postalCode) {
    if (empty($fullName)) {
        return ['success' => false, 'message' => 'Full name is required'];
    }

    $db = getDatabaseConnection();
    $sql = "UPDATE users SET full_name = ?, phone_number = ?, address = ?, city = ?, postal_code = ?
            WHERE user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("sssssi", $fullName, $phone, $address, $city, $postalCode, $userId);

    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'PROFILE_UPDATED', 'users', $userId, '');
        return ['success' => true, 'message' => 'Profile updated successfully'];
    }

    $stmt->close();
    return ['success' => false, 'message' => 'Profile update failed'];
}