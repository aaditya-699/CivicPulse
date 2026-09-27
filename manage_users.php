<?php
/**
 * CIVICPULSE - Manage Users (Admin only)
 *
 * BUG FIX: the INSERT below used to target a column called `password` —
 * the users table has no such column, only `password_hash` — so account
 * creation failed with a SQL error on every attempt.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

requireLogin();
requireRole('admin');

$db = getDatabaseConnection();
$adminId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheckOrDie();

    if (isset($_POST['create_user'])) {
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitizeInput($_POST['user_role'] ?? '');

        if (!in_array($role, ['admin', 'staff'], true)) {
            setFlash('error', 'Invalid role selection.');
        } elseif (!isValidEmail($email)) {
            setFlash('error', 'Please enter a valid email address.');
        } elseif (strlen($password) < 6) {
            setFlash('error', 'Password must be at least 6 characters.');
        } else {
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($exists) {
                setFlash('error', 'That email is already registered.');
            } else {
                $passwordHash = hashPassword($password);
                $stmt = $db->prepare(
                    "INSERT INTO users (full_name, email, password_hash, user_role) VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param("ssss", $fullName, $email, $passwordHash, $role);
                if ($stmt->execute()) {
                    logActivity($adminId, 'USER_CREATED', 'users', $stmt->insert_id, "$role account: $email");
                    setFlash('success', ucfirst($role) . ' account created successfully.');
                } else {
                    setFlash('error', 'Could not create account. Please try again.');
                }
                $stmt->close();
            }
        }

    } elseif (isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        if ($delId === (int)$adminId) {
            setFlash('error', 'You cannot delete your own account.');
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ? AND user_role IN ('admin','staff')");
            $stmt->bind_param("i", $delId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logActivity($adminId, 'USER_DELETED', 'users', $delId, '');
                setFlash('success', 'Account deleted. They can no longer log in.');
            } else {
                setFlash('error', 'Could not delete that account.');
            }
            $stmt->close();
        }
    }

    header("Location: manage_users.php");
    exit();
}

$users = $db->query("SELECT * FROM users WHERE user_role IN ('admin', 'staff') ORDER BY user_role, full_name")
    ->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage Users';
require __DIR__ . '/header.php';
?>
<div class="container">
    <h1 class="page-title">Manage Staff &amp; Admin Accounts</h1>
    <p class="page-subtitle">Citizens self-register from the login page and aren't listed here.</p>
    <?php renderFlash(); ?>

    <div class="card">
        <h2>Create Staff or Admin Account</h2>
        <form method="POST">
            <?= csrfField() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Temporary Password</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="user_role">Role</label>
                    <select id="user_role" name="user_role">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="create_user" class="btn btn-primary">Create Account</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= e($u['full_name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e(ucfirst($u['user_role'])) ?></td>
                        <td><?= $u['is_active'] ? 'Active' : 'Inactive' ?></td>
                        <td><?= $u['last_login'] ? e(formatDate($u['last_login'])) : 'Never' ?></td>
                        <td>
                            <?php if ((int)$u['user_id'] !== (int)$adminId): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this account? They will no longer be able to log in.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="delete_id" value="<?= (int)$u['user_id'] ?>">
                                    <button type="submit" class="link-delete" style="border:none; background:none; cursor:pointer; font:inherit; padding:5px 10px; border-radius:3px; font-weight:600;">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="helper-text">(you)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
