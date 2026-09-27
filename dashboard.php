<?php
/**
 * CIVICPULSE - Dashboard
 * Shows a different view depending on role. Each role gets stats scoped
 * to what's actually relevant to them (this used to show every role the
 * same site-wide totals — fixed here).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/complaints.php';

requireLogin();

$userRole = getCurrentUserRole();
$userId = getCurrentUserId();

if ($userRole === 'citizen') {
    $pageTitle = 'My Dashboard';
    $stats = getCitizenComplaintStatistics($userId);
    $recentComplaints = getComplaintsByCitizen($userId, 1);
} elseif ($userRole === 'staff') {
    $pageTitle = 'Staff Dashboard';
    $stats = getStaffComplaintStatistics($userId);
    $recentComplaints = array_slice(getComplaintsAssignedToStaff($userId), 0, 5);
} else { // admin
    $pageTitle = 'Administration Dashboard';
    $stats = getComplaintStatistics();
    $recentComplaints = getAllComplaints(1);
}

require __DIR__ . '/header.php';
?>
<div class="container">
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <?php renderFlash(); ?>
<div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= (int)($stats['total'] ?? 0) ?></div>
            <div class="stat-label">Total Complaints</div>
        </div>
        
        <?php if ($userRole === 'citizen'): ?>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($stats['by_status']['reported'] ?? 0) ?></div>
                <div class="stat-label">Newly Reported</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($stats['by_status']['in_progress'] ?? 0) ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= ((int)($stats['by_status']['resolved'] ?? 0)) + ((int)($stats['by_status']['closed'] ?? 0)) ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            
        <?php elseif ($userRole === 'staff'): ?>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($stats['by_status']['in_progress'] ?? 0) ?></div>
                <div class="stat-label">In Progress (Mine)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= ((int)($stats['by_status']['resolved'] ?? 0)) + ((int)($stats['by_status']['closed'] ?? 0)) ?></div>
                <div class="stat-label">Resolved (Mine)</div>
            </div>
            
        <?php else: ?>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($stats['by_status']['verified'] ?? 0) ?></div>
                <div class="stat-label">Awaiting Assignment</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($stats['by_status']['resolved'] ?? 0) ?></div>
                <div class="stat-label">Awaiting Closure</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['avg_resolution_days'] ?? 'N/A' ?></div>
                <div class="stat-label">Avg. Resolution (Days)</div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($userRole === 'citizen'): ?>
        <div class="action-buttons">
            <a href="report_complaint.php" class="action-btn">+ Report New Issue</a>
            <a href="my_complaints.php" class="action-btn">All My Complaints</a>
            <a href="profile.php" class="action-btn">My Profile</a>
        </div>
    <?php elseif ($userRole === 'staff'): ?>
        <div class="action-buttons">
            <a href="assignedcomplaints.php" class="action-btn">My Assigned Tasks</a>
            <a href="managecomplaints.php" class="action-btn">Browse All Complaints</a>
            <a href="profile.php" class="action-btn">My Profile</a>
        </div>
    <?php else: ?>
        <div class="action-buttons">
            <a href="managecomplaints.php" class="action-btn">Manage Complaints</a>
            <a href="manage_users.php" class="action-btn">Manage Users</a>
            <a href="reports.php" class="action-btn">View Reports</a>
            <a href="activity_logs.php" class="action-btn">Activity Logs</a>
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <?php if (empty($recentComplaints)): ?>
            <div class="no-data">
                <div class="empty-icon">📋</div>
                <?php if ($userRole === 'citizen'): ?>
                    <p>You haven't reported any issues yet.</p>
                    <p style="margin-top:8px;"><a href="report_complaint.php" style="color:#667eea; font-weight:600;">Report your first complaint</a></p>
                <?php else: ?>
                    <p>Nothing to show here yet.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentComplaints as $c): ?>
                        <tr>
                            <td><?= e(truncateText($c['title'], 45)) ?></td>
                            <td><?= e($c['category_name']) ?></td>
                            <td><span class="badge priority-<?= e($c['priority']) ?>"><?= e($c['priority']) ?></span></td>
                            <td><span class="badge status-<?= e($c['status']) ?>"><?= e(statusLabel($c['status'])) ?></span></td>
                            <td><?= e(formatDate($c['created_at'], 'M d, Y')) ?></td>
                            <td class="action-links"><a class="link-view" href="complaintdetail.php?id=<?= (int)$c['complaint_id'] ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
