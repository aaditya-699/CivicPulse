<?php
/**
 * CIVICPULSE - Logout
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

logoutUser();
header("Location: login.php");
exit();
