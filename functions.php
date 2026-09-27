<?php
/**
 * CIVICPULSE - Helper Functions
 * Sanitizing, CSRF, flash messages, logging, formatting.
 */

// ============================================================================
// INPUT HANDLING
// ============================================================================

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data ?? '');
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $data);
}

/**
 * HTML-escape output. Use in every view.
 */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function truncateText($text, $length = 45) {
    $text = (string)$text;
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $length, '…');
    }
    return strlen($text) > $length ? substr($text, 0, $length - 1) . '…' : $text;
}

// ============================================================================
// CSRF PROTECTION
// ============================================================================

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfVerify($token) {
    return isset($_SESSION['csrf_token']) && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfCheckOrDie() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfVerify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Security check failed. Please go back, refresh the page, and try again.');
        }
    }
}

// ============================================================================
// FLASH MESSAGES
// ============================================================================

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function renderFlash() {
    $flash = getFlash();
    if (!$flash) {
        return;
    }
    echo '<div class="alert alert-' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
}

// ============================================================================
// LOGGING
// ============================================================================

function logError($message) {
    if (LOG_ERRORS) {
        $logFile = LOG_DIR . 'error_' . date('Y-m-d') . '.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

function logActivity($userId, $action, $entityType, $entityId, $details = '') {
    if (!LOG_ACTIVITIES) {
        return;
    }
    $db = getDatabaseConnection();
    $ipAddress = getClientIpAddress();

    $sql = "INSERT INTO activity_log (user_id, action_type, entity_type, entity_id, new_value, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ississ", $userId, $action, $entityType, $entityId, $details, $ipAddress);
        $stmt->execute();
        $stmt->close();
    }
}

function getClientIpAddress() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ============================================================================
// PASSWORDS
// ============================================================================

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ============================================================================
// FORMATTING
// ============================================================================

function formatDate($date, $format = 'M d, Y H:i') {
    if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

function statusLabel($status) {
    return ucwords(str_replace('_', ' ', (string)$status));
}

/**
 * FIXED: Completed the jsonResponse function to prevent fatal syntax errors
 * Returns a standardized JSON response for AJAX calls
 */
function jsonResponse($success, $message, $data = null) {
    // Construct the base response array
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    // Append data if it exists
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    // Set headers and output JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    
    // Terminate script execution
    exit();
}
?>