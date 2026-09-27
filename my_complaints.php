<?php
/**
 * CIVICPULSE - My Complaints
 * Was a dead link (report_complaint.php pointed here but the file didn't
 * exist). Paginated, filterable list of the logged-in citizen's own reports.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/complaints.php';

requireLogin();
requireRole('citizen');

$userId = getCurrentUserId();
$page = max(1, (int)($_GET['page'] ?? 1));
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
if (!in_array($status, array_merge(['all'], COMPLAINT_STATUSES), true)) {
    $status = 'all';
}

$complaints = getComplaintsByCitizen($userId, $page, $status);
$total = getCitizenComplaintCount($userId, $status);
$totalPages = max(1, (int)ceil($total / ITEMS_PER_PAGE));

$pageTitle = 'My Complaints';
require __DIR__ . '/header.php';
?>
<div class="container">
    <div class="top-bar">
        <h1 class="page-title" style="margin-bottom:0;">My Complaints</h1>
        <a href="report_complaint.php" class="btn btn-primary">+ Report New Issue</a>
    </div>
    <?php renderFlash(); ?>

    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" onchange="this.form.submit()">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All statuses</option>
                <?php foreach (COMPLAINT_STATUSES as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(statusLabel($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div class="table-wrap">
        <?php if (empty($complaints)): ?>
            <div class="no-data">
                <div class="empty-icon">📋</div>
                <p>No complaints match this filter.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Complaint #</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Attachments</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td><?= e($c['complaint_number']) ?></td>
                            <td><?= e(truncateText($c['title'], 45)) ?></td>
                            <td><?= e($c['category_name']) ?></td>
                            <td><span class="badge priority-<?= e($c['priority']) ?>"><?= e($c['priority']) ?></span></td>
                            <td><span class="badge status-<?= e($c['status']) ?>"><?= e(statusLabel($c['status'])) ?></span></td>
                            <td><?= e(formatDate($c['created_at'], 'M d, Y')) ?></td>
                            <td><?= (int)$c['attachment_count'] ?></td>
                            <td class="action-links"><a class="link-view" href="complaintdetail.php?id=<?= (int)$c['complaint_id'] ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="<?= e(urlWith(['page' => $p])) ?>" class="<?= $p === $page ? 'current' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
