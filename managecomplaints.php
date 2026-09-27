<?php
/**
 * CIVICPULSE - Manage Complaints (Staff & Admin)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/complaints.php';

requireLogin();
if (!in_array(getCurrentUserRole(), ['admin', 'staff'], true)) {
    header("Location: unauthorized.php");
    exit();
}
$userRole = getCurrentUserRole();
$userId = getCurrentUserId();

$page = max(1, (int)($_GET['page'] ?? 1));
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$priority = isset($_GET['priority']) ? sanitizeInput($_GET['priority']) : 'all';
if (!in_array($status, array_merge(['all'], COMPLAINT_STATUSES), true)) {
    $status = 'all';
}
if (!in_array($priority, array_merge(['all'], COMPLAINT_PRIORITIES), true)) {
    $priority = 'all';
}

$complaints = getAllComplaints($page, $status, null, $priority);
$total = getTotalComplaintCount($status, null, $priority);
$totalPages = max(1, (int)ceil($total / ITEMS_PER_PAGE));

$pageTitle = 'Manage Complaints';
require __DIR__ . '/header.php';
?>
<div class="container">
    <h1 class="page-title">All Complaints</h1>
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
        <div class="form-group">
            <label for="priority">Priority</label>
            <select id="priority" name="priority" onchange="this.form.submit()">
                <option value="all" <?= $priority === 'all' ? 'selected' : '' ?>>All priorities</option>
                <?php foreach (COMPLAINT_PRIORITIES as $p): ?>
                    <option value="<?= $p ?>" <?= $priority === $p ? 'selected' : '' ?>><?= e(ucfirst($p)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div class="table-wrap">
        <?php if (empty($complaints)): ?>
            <div class="no-data">No complaints found for this filter.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Reporter</th>
                        <th>Title</th>
                        <th>Assigned To</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reported On</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td><?= e($c['reporter_name']) ?></td>
                            <td><?= e(truncateText($c['title'], 45)) ?></td>
                            <td><?= e($c['assigned_staff_name'] ?? '—') ?></td>
                            <td><span class="badge priority-<?= e($c['priority']) ?>"><?= e($c['priority']) ?></span></td>
                            <td><span class="badge status-<?= e($c['status']) ?>"><?= e(statusLabel($c['status'])) ?></span></td>
                            <td><?= e(formatDate($c['created_at'], 'M d, Y')) ?></td>
                            <td class="action-links">
                                <a class="link-view" href="complaintdetail.php?id=<?= (int)$c['complaint_id'] ?>">View</a>
                            </td>
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