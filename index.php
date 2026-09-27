<?php
/**
 * CIVICPULSE - Entry Point
 * Anyone hitting the site root lands on the login page (or, if already
 * logged in, on their dashboard).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
