<?php
/**
 * CIVICPULSE - Settings
 *
 * BUG FIX: this page used to call requireRole('admin'), which meant staff
 * and citizen accounts had no way to change their password anywhere in the
 * app. Password change is now available to every logged-in role; the
 * system-info panel stays admin-only.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

requireLogin();
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrfCheckOrDie();

    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        setFlash('error', 'New passwords do not match.');
    } else {
        $result = changePassword($userId, $oldPassword, $newPassword);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
    }
    header("Location: settings.php");
    exit();
}

$user = getCurrentUser();
$pageTitle = 'Settings';
require __DIR__ . '/header.php';
?>
<div class="container-narrow">
    <h1 class="page-title">Settings</h1>
    <?php renderFlash(); ?>

    <?php if ($userRole === 'admin'): ?>
        <div class="card">
            <h2>System Information</h2>
            <div class="info-box">
                <p><strong>Application:</strong> <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?></p>
                <p><strong>Database:</strong> <?= e(DB_NAME) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Your Account</h2>
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" value="<?= e($user['full_name']) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" value="<?= e($user['email']) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Role</label>
            <input type="text" value="<?= e(ucfirst($userRole)) ?>" readonly>
        </div>
    </div>

    <div class="card">
        <h2>Change Password</h2>
        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="old_password">Current Password</label>
                <input type="password" id="old_password" name="old_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
                <div class="helper-text">Minimum 8 characters</div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
