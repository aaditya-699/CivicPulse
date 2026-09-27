<?php
/**
 * CIVICPULSE - My Assigned Complaints (Staff)
 *
 * BUG FIX: this used to run `WHERE c.assigned_to = ?` directly against the
 * complaints table. There is no assigned_to column on complaints — only on
 * tasks — so this page threw a fatal SQL error for every staff user.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/complaints.php';

requireLogin();
requireRole('staff');

$staffId = getCurrentUserId();
$complaints = getComplaintsAssignedToStaff($staffId);

$pageTitle = 'My Assigned Tasks';
require __DIR__ . '/header.php';
?>
<div class="container">
    <h1 class="page-title">My Assigned Tasks</h1>
    <?php renderFlash(); ?>

    <div class="table-wrap">
        <?php if (empty($complaints)): ?>
            <div class="no-data">
                <div class="empty-icon">🗂️</div>
                <p>Nothing assigned to you right now.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Complaint Status</th>
                        <th>Task Status</th>
                        <th>Due Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td><?= e(truncateText($c['title'], 45)) ?></td>
                            <td><?= e($c['category_name']) ?></td>
                            <td><span class="badge priority-<?= e($c['priority']) ?>"><?= e($c['priority']) ?></span></td>
                            <td><span class="badge status-<?= e($c['status']) ?>"><?= e(statusLabel($c['status'])) ?></span></td>
                            <td><?= e(statusLabel($c['task_status'])) ?></td>
                            <td><?= $c['due_date'] ? e(formatDate($c['due_date'], 'M d, Y')) : '—' ?></td>
                            <td class="action-links"><a class="link-view" href="complaintdetail.php?id=<?= (int)$c['complaint_id'] ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
