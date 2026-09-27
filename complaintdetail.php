<?php
/**
 * CIVICPULSE - Complaint Detail
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/complaints.php';

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$complaintId = (int)($_GET['id'] ?? 0);

$complaint = getComplaintById($complaintId);
if (!$complaint) {
    http_response_code(404);
    die('Complaint not found.');
}
if (!canViewComplaint($complaint, $userId, $userRole)) {
    header("Location: unauthorized.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status' && in_array($userRole, ['staff', 'admin'], true)) {
        $newStatus = sanitizeInput($_POST['status'] ?? '');
        $result = updateComplaintStatus($complaintId, $newStatus, $userId, $userRole);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
    } 
    elseif ($action === 'update_status_and_close' && $userRole === 'admin') {
        $note = sanitizeInput($_POST['admin_note'] ?? '');
        if (empty($note)) {
            setFlash('error', 'A closing note is required.');
        } else {
            $result = updateComplaintStatus($complaintId, 'closed', $userId, $userRole);
            if ($result['success']) {
                addComplaintNote($complaintId, $userId, $note);
            }
            setFlash($result['success'] ? 'success' : 'error', $result['message']);
        }
    }
    elseif ($action === 'add_note' && $userRole === 'admin') {
        $result = addComplaintNote($complaintId, $userId, sanitizeInput($_POST['note'] ?? ''));
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
    }

    header("Location: complaintdetail.php?id=" . $complaintId);
    exit();
}

// Permissions and states
$task = $complaint['task'];
$isAssignedStaff = $userRole === 'staff' && $task && (int)$task['assigned_to'] === (int)$userId;

// Staff permissions - Only on tasks belonging to them
$canVerifyOrReject = $isAssignedStaff && $complaint['status'] === 'reported';
$canMarkInProgress = $isAssignedStaff && $complaint['status'] === 'verified';
$canMarkResolved = $isAssignedStaff && $complaint['status'] === 'in_progress';

// Admin permissions - Only closing solved tickets
$canClose = $userRole === 'admin' && $complaint['status'] === 'resolved';
$canReject = false; // Admins can no longer reject complaints

$backLink = $userRole === 'citizen' ? 'my_complaints.php' : 'managecomplaints.php';
$pageTitle = 'Complaint #' . $complaint['complaint_number'];
require __DIR__ . '/header.php';
?>

<div class="container">
    
    <nav class="breadcrumb" style="margin-bottom: 20px;">
        <a href="<?= e($backLink) ?>" style="color:var(--brand-start); text-decoration:none; font-weight:bold;">&larr; Back</a> 
        / Dashboard / Complaints / View
    </nav>

    <?php renderFlash(); ?>

    <div class="content-split-view">
        <!-- LEFT COLUMN: Issue Details & Actions -->
        <div class="left-column">
            
            <!-- Issue Details -->
            <div class="card content-block">
                <h2 style="margin-top: 0;"><?= e($complaint['title']) ?></h2>
                <div class="badge status-<?= e($complaint['status']) ?>" style="margin-bottom: 15px; display: inline-block;">
                    <?= e(str_replace('_', ' ', ucfirst($complaint['status']))) ?>
                </div>
                
                <p style="white-space: pre-wrap; line-height: 1.5; margin-bottom: 20px;"><?= e($complaint['description']) ?></p>

                <div class="detail-row">
                    <div class="detail-label">Category</div>
                    <div class="detail-value"><?= e($complaint['category_name']) ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Location</div>
                    <div class="detail-value"><?= e($complaint['location_address']) ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Priority</div>
                    <div class="detail-value"><span class="badge priority-<?= e($complaint['priority']) ?>"><?= e($complaint['priority']) ?></span></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Reported On</div>
                    <div class="detail-value"><?= e(formatDate($complaint['created_at'])) ?></div>
                </div>

                <?php if (in_array($userRole, ['staff', 'admin'], true)): ?>
                    <div class="detail-row">
                        <div class="detail-label">Reported By</div>
                        <div class="detail-value"><?= e($complaint['reporter_name']) ?> &middot; <?= e($complaint['reporter_email']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($task): ?>
                    <div class="detail-row">
                        <div class="detail-label">Assigned To</div>
                        <div class="detail-value"><?= e($task['assigned_to_name'] ?? 'Unassigned') ?>
                            <?php if ($task['due_date']): ?><span class="helper-text">— due <?= e(formatDate($task['due_date'], 'M d, Y')) ?></span><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Forms -->
            <?php if ($canVerifyOrReject || $canMarkInProgress || $canMarkResolved || $canClose): ?>
            <div class="card content-block" style="margin-top: 20px;">
                <h2>Actions</h2>

                <?php if ($canVerifyOrReject): ?>
                    <form method="POST" style="display:inline-block; margin-right:10px; margin-bottom:12px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="status" value="verified">
                        <button type="submit" class="btn btn-primary">Verify Report</button>
                    </form>
                    <form method="POST" style="display:inline-block; margin-bottom:12px;" onsubmit="return confirm('Reject this complaint as invalid?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </form>
                <?php endif; ?>

                <?php if ($canMarkInProgress): ?>
                    <form method="POST" style="margin-bottom:12px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="status" value="in_progress">
                        <button type="submit" class="btn btn-primary">Mark as In Progress</button>
                    </form>
                <?php endif; ?>

                <?php if ($canMarkResolved): ?>
                    <form method="POST" style="margin-bottom:12px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="status" value="resolved">
                        <button type="submit" class="btn btn-primary">Mark as Solved</button>
                    </form>
                <?php endif; ?>

                <?php if ($canClose): ?>
                    <form method="POST" style="margin-bottom:12px;" onsubmit="return confirm('Close this complaint? Ensure the note is filled out.');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status_and_close">
                        <textarea name="admin_note" required placeholder="Enter mandatory closing note..." style="width:100%; min-height:80px; margin-bottom:10px; padding:10px; border:1px solid #ccc; border-radius:4px;"></textarea>
                        <button type="submit" class="btn btn-primary" style="width:100%;">Close Complaint</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        </div>

        <!-- RIGHT COLUMN: Attachments -->
        <div class="right-column">
            <div class="card content-block">
                <h3>Attachments (<?= count($complaint['attachments'] ?? []) ?>)</h3>
                
                <?php if (!empty($complaint['attachments'])): ?>
                    <ul class="attachment-list image-gallery" style="list-style-type: none; padding: 0;">
                        <?php foreach ($complaint['attachments'] as $att): ?>
                            <li style="margin-bottom: 10px;">
                                <a href="uploads/<?= e($att['file_path']) ?>" target="_blank" rel="noopener">📎 <?= e($att['file_name']) ?></a>
                                <span class="helper-text">(<?= round($att['file_size'] / 1024) ?> KB)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="helper-text">No attachments provided.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
<!-- NOTES SECTION (Strictly visible only when closed, or to Admin when resolving) -->
        <?php 
        // Determine who has permission to view the final notes
        $canSeeNotes = false;
        if ($complaint['status'] === 'closed') {
            if ($userRole === 'admin') {
                // Admin can always see closed notes
                $canSeeNotes = true;
            } elseif ($userRole === 'citizen' && (int)$complaint['user_id'] === (int)$userId) {
                // Only the exact citizen who filed it can see it
                $canSeeNotes = true;
            } elseif ($userRole === 'staff' && $task && (int)$task['assigned_to'] === (int)$userId) {
                // Only the explicitly assigned staff member can see it
                $canSeeNotes = true;
            }
        }
        ?>

        <?php if ($canSeeNotes || ($userRole === 'admin' && $complaint['status'] === 'resolved')): ?>
        <div class="card content-block" style="margin-top: 20px;">
            <h2>Complaint Notes</h2>
            <!-- 1. Display Existing Notes ONLY IF CLOSED -->
            <?php if ($canSeeNotes): ?>
                <div class="notes-list">
                    <?php 
                    // Filter out automatic system notes so only manual admin notes remain
                    $finalNotes = array_filter($complaint['notes'], function($n) {
                        return strpos(trim($n['note']), 'System:') !== 0;
                    });
                    ?>
                    
                    <?php if (empty($finalNotes)): ?>
                        <p style="color: #666; font-style: italic;">No final notes provided.</p>
                    <?php else: ?>
                        <?php foreach ($finalNotes as $note): ?>
                            <div class="note-item" style="border-left: 3px solid #6c5ce7; padding-left: 10px; margin-bottom: 15px;">
                                <strong><?= e($note['full_name']) ?> (<?= e(ucfirst($note['user_role'])) ?>)</strong>
                                <span style="font-size: 0.85em; color: #666;"> &middot; <?= e(formatDate($note['created_at'], 'M d, Y H:i')) ?></span>
                                <p style="margin: 5px 0 0 0; line-height: 1.4;"><?= nl2br(e($note['note'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- 2. Admin Closing Note Form ONLY IF RESOLVED -->
            <?php if ($userRole === 'admin' && $complaint['status'] === 'resolved'): ?>
                <div style="padding-top: 5px;">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to permanently close this complaint?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_status_and_close">
                        <textarea name="admin_note" required placeholder="Enter the final closing note here to be shared with the citizen..." style="width: 100%; min-height: 80px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px; font-family: inherit; resize: vertical;"></textarea>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Note & Close Complaint</button>
                    </form>
                </div>
            <?php endif; ?>
            
        </div>
        <?php endif; ?>
            
        </div>
<?php require __DIR__ . '/footer.php'; ?>