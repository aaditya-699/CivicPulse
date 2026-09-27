<?php
/**
 * CIVICPULSE - Shared Page Header
 * Included by every logged-in page. Expects $pageTitle to be set by the
 * including page. Builds the nav bar from the current user's role so each
 * page doesn't have to hand-maintain its own link list.
 */

if (!isLoggedIn()) {
    // Defensive fallback — every page should already have called requireLogin().
    header("Location: login.php");
    exit();
}

$__role = getCurrentUserRole();
$__user = getCurrentUser();
$__navLinks = [];

if ($__role === 'citizen') {
    $__navLinks = [
        'dashboard.php' => 'Dashboard',
        'report_complaint.php' => 'Report Issue',
        'my_complaints.php' => 'My Complaints',
        'profile.php' => 'Profile',
        'settings.php' => 'Settings',
    ];
} elseif ($__role === 'staff') {
    $__navLinks = [
        'dashboard.php' => 'Dashboard',
        'assignedcomplaints.php' => 'My Assigned Tasks',
        'managecomplaints.php' => 'All Complaints',
        'profile.php' => 'Profile',
        'settings.php' => 'Settings',
    ];
} elseif ($__role === 'admin') {
    $__navLinks = [
        'dashboard.php' => 'Dashboard',
        'managecomplaints.php' => 'Complaints',
        'manage_users.php' => 'Users',
        'reports.php' => 'Reports',
        'activity_logs.php' => 'Activity Logs',
        'settings.php' => 'Settings',
    ];
}

$__currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'CivicPulse') ?> - CivicPulse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="sidebar-open">
    
    <!-- Fixed Top Bar with Hamburger Menu -->
    <header class="global-topbar">
        <div class="topbar-left">
            <button class="hamburger-btn" onclick="toggleSidebar()" title="Toggle Menu">&#9776;</button>
            <a href="dashboard.php" class="logo">🏛️ CivicPulse</a>
        </div>
        
        <div class="topbar-right">
            <div class="user-info">
                Welcome, <strong><?= e($__user['full_name']) ?></strong><br>
                <small><?= e(ucfirst($__role)) ?> Account</small>
            </div>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </header>

    <!-- App Container (Sidebar + Main Content) -->
    <div class="global-app-container">
        
        <!-- Left Collapsible Sidebar -->
        <aside class="global-sidebar" id="appSidebar">
            <nav class="sidebar-nav">
                <?php foreach ($__navLinks as $href => $label): ?>
                    <a href="<?= e($href) ?>" class="<?= $__currentPage === $href ? 'active' : '' ?>">
                        <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- Main Content Starts Here (Closes in footer.php) -->
        <main class="global-main-content">