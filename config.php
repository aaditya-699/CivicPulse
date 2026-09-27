<?php
/**
 * CIVICPULSE - Configuration
 * App constants and the database connection wrapper.
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'civicpulse_db');

// Application Settings
define('APP_NAME', 'CivicPulse');
define('APP_VERSION', '1.1.0');
define('APP_URL', 'http://localhost/civicpulse');

// Security Settings
define('SESSION_TIMEOUT', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900);

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_UPLOAD_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);

// Logging
define('LOG_DIR', __DIR__ . '/logs/');
define('LOG_ERRORS', true);
define('LOG_ACTIVITIES', true);

// Pagination
define('ITEMS_PER_PAGE', 10);

// Create directories
foreach ([UPLOAD_DIR, LOG_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Get the shared database connection.
 */
function getDatabaseConnection() {
    static $connection = null;

    if ($connection === null) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $connection = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

        if ($connection->connect_error) {
            error_log("CivicPulse DB connection failed: " . $connection->connect_error);
            die("Database connection failed. Please contact the administrator.");
        }

        $connection->set_charset("utf8mb4");
    }

    return $connection;
}

// Load other modules
require_once __DIR__ . '/functions.php';

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    logError("[$errno] $errstr in $errfile:$errline");
    return false;
});