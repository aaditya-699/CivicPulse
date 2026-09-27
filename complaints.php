<?php
/**
 * CIVICPULSE - Complaints & Tasks Module
 * CRUD for complaints, file attachments, and the staff-assignment workflow
 * (backed by the `tasks` table).
 *
 * Status workflow (matches the `status` ENUM in the database exactly —
 * no magic strings outside this ENUM are ever written to the column):
 *   reported -> verified -> in_progress -> resolved -> closed
 *                  \-> rejected (from reported or verified)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

const COMPLAINT_STATUSES = ['reported', 'verified', 'in_progress', 'resolved', 'closed', 'rejected'];
const COMPLAINT_PRIORITIES = ['low', 'medium', 'high', 'critical'];

// ============================================================================
// CREATE
// ============================================================================

function createComplaint($userId, $categoryId, $title, $description, $location, $priority, $latitude, $longitude) {
    $db = getDatabaseConnection();

    if (empty($title) || strlen($title) < 5) return ['success' => false, 'message' => 'Title too short'];
    if (empty($description) || strlen($description) < 20) return ['success' => false, 'message' => 'Description too short'];
    if (empty($location)) return ['success' => false, 'message' => 'Location required'];
    if ($categoryId <= 0) return ['success' => false, 'message' => 'Invalid category'];
    if (!in_array($priority, COMPLAINT_PRIORITIES, true)) $priority = 'medium';

    $complaintNumber = generateComplaintNumber();
    $status = 'reported';

    $db->begin_transaction();
    try {
        // 1. Insert Complaint
        $sql = "INSERT INTO complaints (complaint_number, user_id, category_id, title, description, location_address, latitude, longitude, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("siisssddss", $complaintNumber, $userId, $categoryId, $title, $description, $location, $latitude, $longitude, $priority, $status);
        $stmt->execute();
        $complaintId = $stmt->insert_id;
        $stmt->close();

        // 2. Determine Staff Assignment & 24hr Absence Logic
        $assignedStaffId = null;
        $sysNote = null;
        
        $catStmt = $db->prepare("SELECT c.default_staff_id, u.last_login FROM categories c LEFT JOIN users u ON c.default_staff_id = u.user_id WHERE c.category_id = ?");
        $catStmt->bind_param("i", $categoryId);
        $catStmt->execute();
        $catData = $catStmt->get_result()->fetch_assoc();
        $catStmt->close();

        if ($catData && !empty($catData['default_staff_id'])) {
            $assignedStaffId = $catData['default_staff_id'];
            $isInactive = true;

            // Check if logged in within the last 24 hours (86400 seconds)
            if (!empty($catData['last_login']) && (time() - strtotime($catData['last_login']) <= 86400)) {
                $isInactive = false;
            }

            if ($isInactive) {
                // Find an active staff member with the lowest active task count
                $altStmt = $db->query("
                    SELECT u.user_id, COUNT(t.task_id) as active_tasks 
                    FROM users u 
                    LEFT JOIN tasks t ON u.user_id = t.assigned_to AND t.task_status != 'closed'
                    WHERE u.user_role = 'staff' AND u.is_active = 1 AND u.last_login >= NOW() - INTERVAL 24 HOUR
                    GROUP BY u.user_id 
                    ORDER BY active_tasks ASC LIMIT 1
                ");
                if ($altStmt && $altRow = $altStmt->fetch_assoc()) {
                    $assignedStaffId = $altRow['user_id'];
                    $sysNote = "System: Default category staff inactive for 24+ hours. Auto-assigned to active staff member with lowest workload.";
                } else {
                    $sysNote = "System: Default staff is inactive, but no other active staff found. Kept default assignment.";
                }
            } else {
                $sysNote = "System: Complaint auto-assigned to default category staff.";
            }

            // 3. Create the Task & System Note
            if ($assignedStaffId) {
                $taskStmt = $db->prepare("INSERT INTO tasks (complaint_id, assigned_to, task_status, assigned_date) VALUES (?, ?, 'assigned', NOW())");
                $taskStmt->bind_param("ii", $complaintId, $assignedStaffId);
                $taskStmt->execute();
                $taskStmt->close();
                
                addComplaintNote($complaintId, $userId, $sysNote);
            }
        }

        $db->commit();
        logActivity($userId, 'COMPLAINT_CREATED', 'complaints', $complaintId, "Title: $title");
        return ['success' => true, 'message' => 'Complaint registered successfully', 'complaint_id' => $complaintId, 'complaint_number' => $complaintNumber];
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Failed to create complaint'];
    }
}

// ============================================================================
// READ
// ============================================================================

function getComplaintById($complaintId) {
    $db = getDatabaseConnection();
    $sql = "SELECT c.*, cat.category_name, u.full_name AS reporter_name,
                   u.email AS reporter_email, u.phone_number AS reporter_phone
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.category_id
            JOIN users u ON c.user_id = u.user_id
            WHERE c.complaint_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $complaint = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($complaint) {
        $complaint['attachments'] = getComplaintAttachments($complaintId);
        $complaint['task'] = getComplaintTask($complaintId);
        $complaint['notes'] = getComplaintNotes($complaintId);
    }

    return $complaint;
}

/** Can this user view this complaint's detail page? */
function canViewComplaint(array $complaint, $userId, $userRole) {
    if ($userRole === 'admin') {
        return true;
    }
    if ($userRole === 'citizen') {
        return (int)$complaint['user_id'] === (int)$userId;
    }
    if ($userRole === 'staff') {
        // Allow if specifically assigned to this task
        if (isset($complaint['task']) && (int)$complaint['task']['assigned_to'] === (int)$userId) {
            return true;
        }
        // Allow if they are the default manager for this complaint's category
        $db = getDatabaseConnection();
        $stmt = $db->prepare("SELECT default_staff_id FROM categories WHERE category_id = ?");
        $stmt->bind_param("i", $complaint['category_id']);
        $stmt->execute();
        $cat = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $cat && (int)$cat['default_staff_id'] === (int)$userId;
    }
    return false;
}

function getComplaintsByCitizen($userId, $page = 1, $status = null) {
    $db = getDatabaseConnection();
    $offset = ($page - 1) * ITEMS_PER_PAGE;

    $where = "WHERE c.user_id = ?";
    $params = [$userId];
    $types = "i";

    if ($status && $status !== 'all') {
        $where .= " AND c.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $sql = "SELECT c.complaint_id, c.complaint_number, c.title, c.priority, c.status, c.created_at,
                   cat.category_name,
                   COUNT(DISTINCT ca.attachment_id) AS attachment_count
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.category_id
            LEFT JOIN complaint_attachments ca ON c.complaint_id = ca.complaint_id
            $where
            GROUP BY c.complaint_id
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";

    $params[] = ITEMS_PER_PAGE;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $complaints = [];
    while ($row = $result->fetch_assoc()) {
        $complaints[] = $row;
    }
    $stmt->close();
    return $complaints;
}

function getCitizenComplaintCount($userId, $status = null) {
    $db = getDatabaseConnection();
    $sql = "SELECT COUNT(*) AS total FROM complaints WHERE user_id = ?";
    $params = [$userId];
    $types = "i";
    if ($status && $status !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$row['total'];
}

/** Full queue for staff/admin, with optional filters. */
function getAllComplaints($page = 1, $status = null, $category = null, $priority = null) {
    $db = getDatabaseConnection();
    $offset = ($page - 1) * ITEMS_PER_PAGE;

    $where = "WHERE 1=1";
    $params = [];
    $types = "";

    // Automatically check role context
    $currentUserRole = function_exists('getCurrentUserRole') ? getCurrentUserRole() : null;
    $currentUserId = function_exists('getCurrentUserId') ? getCurrentUserId() : null;

    if ($currentUserRole === 'staff') {
        $where .= " AND (cat.default_staff_id = ? OR t.assigned_to = ?)";
        $params[] = $currentUserId;
        $params[] = $currentUserId;
        $types .= "ii";
    }

    if ($status && $status !== 'all') {
        $where .= " AND c.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    if ($category && $category !== 'all') {
        $where .= " AND c.category_id = ?";
        $params[] = $category;
        $types .= "i";
    }
    if ($priority && $priority !== 'all') {
        $where .= " AND c.priority = ?";
        $params[] = $priority;
        $types .= "s";
    }

    $sql = "SELECT c.complaint_id, c.complaint_number, c.title, c.priority, c.status, c.created_at,
                   cat.category_name, u.full_name AS reporter_name,
                   t.assigned_to, staff.full_name AS assigned_staff_name
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.category_id
            JOIN users u ON c.user_id = u.user_id
            LEFT JOIN tasks t ON t.complaint_id = c.complaint_id
            LEFT JOIN users staff ON t.assigned_to = staff.user_id
            $where
            GROUP BY c.complaint_id
            ORDER BY
                CASE c.priority
                    WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3
                END,
                c.created_at DESC
            LIMIT ? OFFSET ?";

    $params[] = ITEMS_PER_PAGE;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $complaints = [];
    while ($row = $result->fetch_assoc()) {
        $complaints[] = $row;
    }
    $stmt->close();
    return $complaints;
}
function getTotalComplaintCount($status = null, $category = null, $priority = null) {
    $db = getDatabaseConnection();
    
    $join = "";
    $where = "WHERE 1=1";
    $params = [];
    $types = "";

    $currentUserRole = function_exists('getCurrentUserRole') ? getCurrentUserRole() : null;
    $currentUserId = function_exists('getCurrentUserId') ? getCurrentUserId() : null;

    if ($currentUserRole === 'staff') {
        $join = " JOIN categories cat ON c.category_id = cat.category_id 
                  LEFT JOIN tasks t ON t.complaint_id = c.complaint_id ";
        $where .= " AND (cat.default_staff_id = ? OR t.assigned_to = ?)";
        $params[] = $currentUserId;
        $params[] = $currentUserId;
        $types .= "ii";
    }

    if ($status && $status !== 'all') {
        $where .= " AND c.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    if ($category && $category !== 'all') {
        $where .= " AND c.category_id = ?";
        $params[] = $category;
        $types .= "i";
    }
    if ($priority && $priority !== 'all') {
        $where .= " AND c.priority = ?";
        $params[] = $priority;
        $types .= "s";
    }

    $sql = "SELECT COUNT(DISTINCT c.complaint_id) AS total FROM complaints c $join $where";
    
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$row['total'];
}
/** Complaints currently assigned to a specific staff member (via the tasks table). */
function getComplaintsAssignedToStaff($staffId) {
    $db = getDatabaseConnection();
    $sql = "SELECT c.complaint_id, c.complaint_number, c.title, c.priority, c.status, c.created_at,
                   cat.category_name, t.task_status, t.due_date, t.assigned_date
            FROM tasks t
            JOIN complaints c ON c.complaint_id = t.complaint_id
            JOIN categories cat ON c.category_id = cat.category_id
            WHERE t.assigned_to = ?
            ORDER BY
                CASE c.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
                t.assigned_date DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $staffId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/** All active staff, for the "assign to" dropdown. */
function getStaffList() {
    $db = getDatabaseConnection();
    $result = $db->query(
        "SELECT user_id, full_name FROM users WHERE user_role = 'staff' AND is_active = 1 ORDER BY full_name"
    );
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// ============================================================================
// UPDATE
// ============================================================================

function updateComplaintStatus($complaintId, $status, $actorId, $actorRole) {
    if (!in_array($status, COMPLAINT_STATUSES, true)) return ['success' => false, 'message' => 'Invalid status'];
    
    $db = getDatabaseConnection();
    $current = getComplaintById($complaintId);
    if (!$current) return ['success' => false, 'message' => 'Complaint not found'];

    // Strict Role Enforcement
    $allowedTransitions = [
        'staff' => [
            'reported' => ['verified', 'rejected'],
            'verified' => ['in_progress'],
            'in_progress' => ['resolved'],
        ],
        'admin' => [
            'resolved' => ['closed'],
        ],
    ];

    $from = $current['status'];
    $allowed = $allowedTransitions[$actorRole][$from] ?? [];

    if ($actorRole === 'staff') {
        $task = $current['task'];
        $isAssignedToActor = $task && (int)$task['assigned_to'] === (int)$actorId;
        if (!$isAssignedToActor) {
            return ['success' => false, 'message' => 'You can only update complaints assigned to your dashboard'];
        }
    }

    if (!in_array($status, $allowed, true)) {
        return ['success' => false, 'message' => "Unauthorized. Cannot move '$from' to '$status'."];
    }

    $sql = "UPDATE complaints SET status = ?, updated_at = NOW()";
    if ($status === 'resolved') $sql .= ", resolved_at = NOW()";
    $sql .= " WHERE complaint_id = ?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $status, $complaintId);

    if ($stmt->execute()) {
        $stmt->close();
        logActivity($actorId, 'COMPLAINT_STATUS_UPDATED', 'complaints', $complaintId, "From $from to $status");
        return ['success' => true, 'message' => 'Status updated successfully'];
    }
    return ['success' => false, 'message' => 'Update failed'];
}

/** Admin assigns a verified complaint to a staff member; creates the task and moves status to in_progress. */
function assignComplaintToStaff($complaintId, $staffId, $adminId, $dueDate = null) {
    $db = getDatabaseConnection();

    $complaint = getComplaintById($complaintId);
    if (!$complaint) {
        return ['success' => false, 'message' => 'Complaint not found'];
    }
    if ($complaint['status'] !== 'verified') {
        return ['success' => false, 'message' => "Only 'verified' complaints can be assigned"];
    }

    $stmt = $db->prepare("SELECT user_id FROM users WHERE user_id = ? AND user_role = 'staff' AND is_active = 1");
    $stmt->bind_param("i", $staffId);
    $stmt->execute();
    $validStaff = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    if (!$validStaff) {
        return ['success' => false, 'message' => 'Please choose a valid, active staff member'];
    }

    $db->begin_transaction();
    try {
        $existing = getComplaintTask($complaintId);
        if ($existing) {
            $stmt = $db->prepare(
                "UPDATE tasks SET assigned_to = ?, task_status = 'assigned', due_date = ?, assigned_date = NOW()
                 WHERE task_id = ?"
            );
            $stmt->bind_param("isi", $staffId, $dueDate, $existing['task_id']);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO tasks (complaint_id, assigned_to, task_status, due_date) VALUES (?, ?, 'assigned', ?)"
            );
            $stmt->bind_param("iis", $complaintId, $staffId, $dueDate);
        }
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("UPDATE complaints SET status = 'in_progress', updated_at = NOW() WHERE complaint_id = ?");
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $stmt->close();

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        logError('assignComplaintToStaff failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Assignment failed. Please try again.'];
    }

    logActivity($adminId, 'COMPLAINT_ASSIGNED', 'complaints', $complaintId, "Assigned to staff #$staffId");
    return ['success' => true, 'message' => 'Complaint assigned successfully'];
}

function addComplaintNote($complaintId, $userId, $note) {
    if (trim($note) === '') {
        return ['success' => false, 'message' => 'Note cannot be empty'];
    }
    $db = getDatabaseConnection();
    $stmt = $db->prepare(
        "INSERT INTO complaint_notes (complaint_id, user_id, note, created_at) VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param("iis", $complaintId, $userId, $note);
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Note added'];
    }
    $stmt->close();
    return ['success' => false, 'message' => 'Failed to add note'];
}

function getComplaintNotes($complaintId) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare(
        "SELECT n.note, n.created_at, u.full_name, u.user_role
         FROM complaint_notes n JOIN users u ON n.user_id = u.user_id
         WHERE n.complaint_id = ? ORDER BY n.created_at ASC"
    );
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }
    $stmt->close();
    return $notes;
}

// ============================================================================
// DELETE
// ============================================================================

/** Admin may only delete complaints that have already been closed. */
function deleteComplaint($complaintId, $actorId) {
    $db = getDatabaseConnection();

    $stmt = $db->prepare("SELECT status FROM complaints WHERE complaint_id = ?");
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $complaint = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$complaint) {
        return ['success' => false, 'message' => 'Complaint not found'];
    }
    if ($complaint['status'] !== 'closed' && $complaint['status'] !== 'rejected') {
        return ['success' => false, 'message' => "Only 'closed' or 'rejected' complaints can be deleted"];
    }

    // Clean up attachment files on disk before removing their DB rows.
    foreach (getComplaintAttachments($complaintId) as $attachment) {
        $fullPath = UPLOAD_DIR . basename($attachment['file_path']);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    $db->begin_transaction();
    try {
        foreach (['complaint_attachments', 'tasks', 'complaint_notes'] as $table) {
            $stmt = $db->prepare("DELETE FROM $table WHERE complaint_id = ?");
            $stmt->bind_param("i", $complaintId);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $db->prepare("DELETE FROM complaints WHERE complaint_id = ?");
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        logError('deleteComplaint failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Delete failed'];
    }

    logActivity($actorId, 'COMPLAINT_DELETED', 'complaints', $complaintId, '');
    return ['success' => true, 'message' => 'Complaint deleted successfully'];
}

// ============================================================================
// ATTACHMENTS
// ============================================================================

function uploadComplaintAttachment($complaintId, array $uploadedFile, $userId) {
    if (!isset($uploadedFile['error']) || $uploadedFile['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'message' => 'No file selected', 'skipped' => true];
    }
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed (error code ' . $uploadedFile['error'] . ')'];
    }
    if ($uploadedFile['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB'];
    }

    $fileExt = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, ALLOWED_EXTENSIONS, true)) {
        return ['success' => false, 'message' => 'File type not allowed. Allowed: JPG, PNG, GIF, PDF'];
    }

    // Don't trust the extension or the browser-supplied MIME type — sniff the
    // actual bytes so a renamed .php can't sneak in as "photo.jpg".
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $uploadedFile['tmp_name']);
    finfo_close($finfo);
    if (!in_array($realMime, ALLOWED_MIME_TYPES, true)) {
        return ['success' => false, 'message' => 'File content does not match an allowed type'];
    }

    $fileName = 'complaint_' . $complaintId . '_' . bin2hex(random_bytes(8)) . '.' . $fileExt;
    $filePath = UPLOAD_DIR . $fileName;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'File upload failed'];
    }

    $db = getDatabaseConnection();
    $fileSize = $uploadedFile['size'];
    $sql = "INSERT INTO complaint_attachments (complaint_id, file_name, file_path, file_type, file_size, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("isssii", $complaintId, $fileName, $fileName, $realMime, $fileSize, $userId);

    if ($stmt->execute()) {
        $attachmentId = $stmt->insert_id;
        $stmt->close();
        logActivity($userId, 'ATTACHMENT_UPLOADED', 'complaint_attachments', $complaintId, $fileName);
        return ['success' => true, 'message' => 'File uploaded successfully', 'attachment_id' => $attachmentId];
    }

    $stmt->close();
    @unlink($filePath);
    return ['success' => false, 'message' => 'Could not save attachment record'];
}

function getComplaintAttachments($complaintId) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare(
        "SELECT attachment_id, file_name, file_path, file_type, file_size, uploaded_at
         FROM complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at DESC"
    );
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $result = $stmt->get_result();
    $attachments = [];
    while ($row = $result->fetch_assoc()) {
        $attachments[] = $row;
    }
    $stmt->close();
    return $attachments;
}

// ============================================================================
// HELPERS
// ============================================================================

function getComplaintTask($complaintId) {
    $db = getDatabaseConnection();
    $sql = "SELECT t.*, u.full_name AS assigned_to_name, u.email AS assigned_to_email
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.user_id
            WHERE t.complaint_id = ?
            ORDER BY t.assigned_date DESC
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $task ?: null;
}

/** Dashboard/report stats scoped to everything (admin view). */
function getComplaintStatistics() {
    $db = getDatabaseConnection();
    $stats = ['total' => 0, 'by_status' => [], 'by_category' => [], 'avg_resolution_days' => null];

    $result = $db->query("SELECT COUNT(*) AS count FROM complaints");
    $stats['total'] = (int)$result->fetch_assoc()['count'];

    $result = $db->query("SELECT status, COUNT(*) AS count FROM complaints GROUP BY status");
    while ($row = $result->fetch_assoc()) {
        $stats['by_status'][$row['status']] = (int)$row['count'];
    }

    $result = $db->query(
        "SELECT c.category_name, COUNT(comp.complaint_id) AS count
         FROM categories c LEFT JOIN complaints comp ON c.category_id = comp.category_id
         GROUP BY c.category_id ORDER BY count DESC"
    );
    while ($row = $result->fetch_assoc()) {
        $stats['by_category'][$row['category_name']] = (int)$row['count'];
    }

    $result = $db->query(
        "SELECT ROUND(AVG(DATEDIFF(resolved_at, created_at)), 1) AS avg_days
         FROM complaints WHERE resolved_at IS NOT NULL"
    );
    $stats['avg_resolution_days'] = $result->fetch_assoc()['avg_days'];

    return $stats;
}

/** Dashboard stats scoped to one citizen's own complaints. */
function getCitizenComplaintStatistics($userId) {
    $db = getDatabaseConnection();
    $stats = ['total' => 0, 'by_status' => []];

    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM complaints WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stats['total'] = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $db->prepare("SELECT status, COUNT(*) AS total FROM complaints WHERE user_id = ? GROUP BY status");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats['by_status'][$row['status']] = (int)$row['total'];
    }
    $stmt->close();

    return $stats;
}

/** Dashboard stats scoped to one staff member's assigned complaints. */
function getStaffComplaintStatistics($staffId) {
    $db = getDatabaseConnection();
    $stats = ['total' => 0, 'by_status' => []];

    $sql = "SELECT c.status, COUNT(*) AS total
            FROM tasks t JOIN complaints c ON c.complaint_id = t.complaint_id
            WHERE t.assigned_to = ?
            GROUP BY c.status";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats['by_status'][$row['status']] = (int)$row['total'];
        $stats['total'] += (int)$row['total'];
    }
    $stmt->close();

    return $stats;
}
function generateComplaintNumber() {
    // Generates a unique, readable tracking ID like "CP-20260927-4829"
    return 'CP-' . date('Ymd') . '-' . random_int(1000, 9999);
}