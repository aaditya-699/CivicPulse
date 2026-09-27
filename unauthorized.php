<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$pageTitle = 'Access Denied';
require __DIR__ . '/header.php';
?>
<div class="container-narrow" style="text-align:center; padding-top:60px;">
    <div class="card">
        <div style="font-size:48px; margin-bottom:10px;">🚫</div>
        <h1 class="page-title">Access Denied</h1>
        <p class="page-subtitle">Your account doesn't have permission to view that page.</p>
        <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
