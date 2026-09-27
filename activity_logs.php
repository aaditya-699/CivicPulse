<?php
/**
 * CIVICPULSE - Activity Logs
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

requireLogin();

// Restrict access to admins
$userRole = getCurrentUserRole();
if ($userRole !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$pageTitle = 'System Activity Logs';
$conn = getDatabaseConnection();

require __DIR__ . '/header.php';
?>

<div class="container">
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <p style="margin-bottom: 20px; color: #64748b;">Audit trail of user logins, status modifications, and system actions across the portal.</p>

    <div class="table-wrap">
        <?php
        $result = $conn->query("SELECT * FROM activity_logs ORDER BY log_date DESC LIMIT 50");
        if ($result && $result->num_rows > 0):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= (int)$row['log_id'] ?></td>
                            <td><strong><?= e($row['user_name']) ?></strong></td>
                            <td><span class="badge priority-medium"><?= e($row['action_type']) ?></span></td>
                            <td><?= e($row['description']) ?></td>
                            <td><?= e(formatDate($row['log_date'], 'M d, Y H:i')) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <div class="empty-icon">📋</div>
                <p>No activity logs recorded yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>