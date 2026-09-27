<?php
require_once 'config.php';
$db = getDatabaseConnection();
if ($db->connect_error) {
    echo "Connection failed: " . $db->connect_error;
} else {
    echo "Database connected successfully!";
    echo "<br>Database: " . DB_NAME;
}
?>