<?php
/**
 * CIVICPULSE - My Profile
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

requireLogin();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrfCheckOrDie();

    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $postalCode = sanitizeInput($_POST['postal_code'] ?? '');

    $result = updateUserProfile($userId, $fullName, $phone, $address, $city, $postalCode);
    setFlash($result['success'] ? 'success' : 'error', $result['message']);
    header("Location: profile.php");
    exit();
}

$user = getCurrentUser();
$pageTitle = 'My Profile';
require __DIR__ . '/header.php';
?>
<div class="container-narrow">
    <h1 class="page-title">My Profile</h1>
    <?php renderFlash(); ?>

    <div class="card">
        <h2>Edit Profile</h2>
        <form method="POST">
            <?= csrfField() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" required value="<?= e($user['full_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" value="<?= e($user['email']) ?>" readonly>
                    <div class="helper-text">Email cannot be changed</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?= e($user['phone_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" value="<?= e($user['city'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= e($user['address'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="postal_code">Postal Code</label>
                    <input type="text" id="postal_code" name="postal_code" value="<?= e($user['postal_code'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
        </form>
    </div>

    <div class="card">
        <h2>Account Information</h2>
        <div class="form-group">
            <label>Account Created</label>
            <input type="text" value="<?= e(formatDate($user['created_at'])) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Last Login</label>
            <input type="text" value="<?= $user['last_login'] ? e(formatDate($user['last_login'])) : 'Never' ?>" readonly>
        </div>
        <a href="settings.php" class="btn btn-secondary btn-sm">Change Password →</a>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
