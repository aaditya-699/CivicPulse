<?php
/**
 * CIVICPULSE - Report Complaint
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/complaints.php';

requireLogin();
requireRole('citizen');

$userId = getCurrentUserId();
$error = '';

$db = getDatabaseConnection();
$categories = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name")
    ->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();

    $categoryId = (int)($_POST['category_id'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $location = sanitizeInput($_POST['location'] ?? '');
    $priority = sanitizeInput($_POST['priority'] ?? 'medium');
    $latitude = ($_POST['latitude'] ?? '') !== '' ? (float)$_POST['latitude'] : null;
    $longitude = ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null;

    $result = createComplaint($userId, $categoryId, $title, $description, $location, $priority, $latitude, $longitude);

    if ($result['success']) {
        // Attachment is optional — if one was chosen, upload it now that we
        // have a complaint_id to attach it to. A failed upload doesn't roll
        // back the complaint; it's reported alongside the success message.
        $uploadNote = '';
        if (!empty($_FILES['attachment']['name'])) {
            $uploadResult = uploadComplaintAttachment($result['complaint_id'], $_FILES['attachment'], $userId);
            if (!$uploadResult['success'] && empty($uploadResult['skipped'])) {
                $uploadNote = ' (Note: attachment upload failed — ' . $uploadResult['message'] . ')';
            }
        }

        setFlash('success', 'Complaint #' . $result['complaint_number'] . ' reported successfully!' . $uploadNote);
        header("Location: my_complaints.php");
        exit();
    }

    $error = $result['message'];
}

$pageTitle = 'Report Infrastructure Defect';
require __DIR__ . '/header.php';
?>
<div class="container-narrow">
    <h1 class="page-title">Report Infrastructure Defect</h1>
    <p class="page-subtitle">Help us improve municipal infrastructure. Report any defects you notice.</p>

    <div class="card">
        <?php if ($error): ?>
            <div class="alert alert-error"><strong>Error:</strong> <?= e($error) ?></div>
        <?php endif; ?>

        <div class="info-box">
            <strong>📌 Tip:</strong> Provide clear details and a photo for faster resolution. Be specific about location.
        </div>

        <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
            <?= csrfField() ?>

            <div class="form-group">
                <label for="category">Category <span class="required">*</span></label>
                <select id="category" name="category_id" required>
                    <option value="">-- Select a category --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['category_id'] ?>" <?= (($_POST['category_id'] ?? '') == $category['category_id']) ? 'selected' : '' ?>>
                            <?= e($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="title">Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" required maxlength="200" placeholder="Brief title of the issue" value="<?= e($_POST['title'] ?? '') ?>">
                <div class="helper-text">Short summary (5–200 characters)</div>
            </div>

            <div class="form-group">
                <label for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" required placeholder="Detailed description of the defect..."><?= e($_POST['description'] ?? '') ?></textarea>
                <div class="helper-text">At least 20 characters — the more detail, the faster it can be triaged</div>
            </div>

            <div class="form-group">
                <label for="location">Location <span class="required">*</span></label>
                <input type="text" id="location" name="location" required placeholder="Street name, landmark, or address" value="<?= e($_POST['location'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="latitude">Latitude (Optional)</label>
                    <input type="text" id="latitude" name="latitude" placeholder="e.g., 27.7172" value="<?= e($_POST['latitude'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="longitude">Longitude (Optional)</label>
                    <input type="text" id="longitude" name="longitude" placeholder="e.g., 85.3240" value="<?= e($_POST['longitude'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Priority Level</label>
                <div class="priority-group">
                    <?php foreach (['low' => 'Low (Minor)', 'medium' => 'Medium (Standard)', 'high' => 'High (Urgent)', 'critical' => 'Critical (Safety)'] as $val => $label): ?>
                        <div class="priority-option">
                            <label>
                                <input type="radio" name="priority" value="<?= $val ?>" <?= ((($_POST['priority'] ?? 'medium') === $val)) ? 'checked' : '' ?>>
                                <?= e($label) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="attachment">Photo Evidence (Optional)</label>
                <div class="file-input-wrapper">
                    <input type="file" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf">
                </div>
                <div class="helper-text">JPG, PNG, GIF, or PDF — up to 5MB</div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary">Submit Complaint</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    function validateForm() {
        const title = document.getElementById('title').value.trim();
        const description = document.getElementById('description').value.trim();

        if (title.length < 5) {
            alert('Title must be at least 5 characters');
            return false;
        }
        if (description.length < 20) {
            alert('Description must be at least 20 characters');
            return false;
        }

        const lat = document.getElementById('latitude').value.trim();
        const lng = document.getElementById('longitude').value.trim();
        if (lat && isNaN(parseFloat(lat))) {
            alert('Latitude must be a valid number');
            return false;
        }
        if (lng && isNaN(parseFloat(lng))) {
            alert('Longitude must be a valid number');
            return false;
        }
        return true;
    }
</script>
<?php require __DIR__ . '/footer.php'; ?>
