<?php
/**
 * CIVICPULSE - Reports & Statistics (Admin only)
 * Same page as before, now actually reachable from the nav bar.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/complaints.php';

requireLogin();
requireRole('admin');

$stats = getComplaintStatistics();

$pageTitle = 'Reports & Statistics';
require __DIR__ . '/header.php';
?>
<div class="container">
    <h1 class="page-title">Reports &amp; Statistics</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= (int)($stats['total'] ?? 0) ?></div>
            <div class="stat-label">Total Complaints</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['avg_resolution_days'] ?? 'N/A' ?></div>
            <div class="stat-label">Avg. Resolution (Days)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= (int)($stats['by_status']['closed'] ?? 0) ?></div>
            <div class="stat-label">Closed</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= (int)($stats['by_status']['rejected'] ?? 0) ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <div class="card">
        <h2>Complaints by Status</h2>
        <ul class="attachment-list" style="list-style:none;">
            <?php foreach (COMPLAINT_STATUSES as $s): $count = $stats['by_status'][$s] ?? 0; ?>
                <li style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #eee;">
                    <span><?= e(statusLabel($s)) ?></span>
                    <strong><?= $count ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card">
        <h2>Complaints by Category</h2>
        <ul class="attachment-list" style="list-style:none;">
            <?php foreach (($stats['by_category'] ?? []) as $category => $count): ?>
                <li style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #eee;">
                    <span><?= e($category) ?></span>
                    <strong><?= $count ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
