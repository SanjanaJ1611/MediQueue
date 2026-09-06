<?php
/**
 * MediQueue - Admin: System Configuration & Admin Profile
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$page_title = "Hospital System Settings";
$conn = get_db_connection();
$userId = $_SESSION['user_id'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session token expired.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!empty($name)) {
            $uStmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $uStmt->execute([$name, $phone, $userId]);
            $_SESSION['user_name'] = $name;

            if (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'Passwords do not match.';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $pwdStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $pwdStmt->execute([$hash, $userId]);
                }
            }

            if (!$error) {
                set_flash('success', 'Hospital settings and administrator profile updated.');
                header('Location: ' . BASE_URL . '/admin/settings.php');
                exit;
            }
        }
    }
}

$adminUser = $conn->prepare("SELECT * FROM users WHERE id = ?");
$adminUser->execute([$userId]);
$admin = $adminUser->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Hospital & Administrator Settings</h3>
        <p class="text-muted mb-0">Manage hospital profile, virtual queue defaults, and administrator credentials.</p>
    </div>
</div>

<div class="row g-4 justify-content-center">
    <div class="col-lg-8">
        <div class="mq-card p-4 p-md-5 bg-white">
            <?php if ($error): ?>
                <div class="alert alert-danger small mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/admin/settings.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <h5 class="fw-bold text-dark mb-3">Administrator Account Details</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Admin Full Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($admin['name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Admin Email (Login)</label>
                        <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($admin['email']) ?>" readonly disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Contact Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($admin['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Access Role</label>
                        <input type="text" class="form-control bg-light" value="Super Administrator / Staff" readonly disabled>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="fw-bold text-dark mb-3">Virtual Queue Parameters</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Default Consultation Slot (Minutes)</label>
                        <input type="number" class="form-control" value="30" readonly disabled>
                        <div class="form-text">Doctor slots are scheduled in 30-minute intervals by default.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Average Turn Duration (Wait Estimation)</label>
                        <input type="number" class="form-control" value="15" readonly disabled>
                        <div class="form-text">Used for estimating patient queue wait times (15 mins/patient).</div>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="fw-bold text-dark mb-2">Change Administrator Password</h5>
                <p class="text-muted small mb-3">Leave empty if you do not wish to change password.</p>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-type password">
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary fw-semibold px-4 py-2">
                        <i class="fa-regular fa-floppy-disk me-1"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
