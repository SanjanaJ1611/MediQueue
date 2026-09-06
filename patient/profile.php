<?php
/**
 * MediQueue - Patient: Profile Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('patient');

$page_title = "My Profile";
$conn = get_db_connection();
$userId = $_SESSION['user_id'];
$patientId = $_SESSION['role_specific_id'] ?? 0;

$error = '';
$success = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please reload and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');
        $bloodGroup = trim($_POST['blood_group'] ?? 'O+');
        $address = trim($_POST['address'] ?? '');
        $emergencyContact = trim($_POST['emergency_contact'] ?? '');

        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($name)) {
            $error = 'Name cannot be empty.';
        } else {
            try {
                $conn->beginTransaction();

                // 1. Update user table
                $uStmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $uStmt->execute([$name, $phone, $userId]);
                $_SESSION['user_name'] = $name;

                // 2. Update patient table
                $pStmt = $conn->prepare("
                    UPDATE patients 
                    SET date_of_birth = ?, gender = ?, blood_group = ?, address = ?, emergency_contact = ? 
                    WHERE user_id = ?
                ");
                $pStmt->execute([!empty($dob) ? $dob : null, $gender, $bloodGroup, $address, $emergencyContact, $userId]);

                // 3. Update password if provided
                if (!empty($newPassword)) {
                    if (strlen($newPassword) < 6) {
                        throw new Exception('New password must be at least 6 characters long.');
                    }
                    if ($newPassword !== $confirmPassword) {
                        throw new Exception('Passwords do not match.');
                    }
                    $pwdHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $pwdStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $pwdStmt->execute([$pwdHash, $userId]);
                }

                $conn->commit();
                set_flash('success', 'Your profile details have been updated successfully.');
                header('Location: ' . BASE_URL . '/patient/profile.php');
                exit;

            } catch (Exception $e) {
                $conn->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

// Fetch current user and patient record
$stmt = $conn->prepare("
    SELECT u.name, u.email, u.phone, p.* 
    FROM users u 
    LEFT JOIN patients p ON u.id = p.user_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">My Patient Profile</h3>
        <p class="text-muted mb-0">Manage your personal demographics, emergency contacts, and account security.</p>
    </div>
</div>

<div class="row g-4 justify-content-center">
    <!-- User Overview Card -->
    <div class="col-lg-4">
        <div class="mq-card text-center p-4">
            <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2.2rem;">
                <?= strtoupper(substr($profile['name'], 0, 1)) ?>
            </div>
            <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($profile['name']) ?></h5>
            <span class="badge bg-primary-subtle text-primary mb-3">Registered Patient</span>
            
            <div class="p-3 bg-light rounded-3 border text-start small mb-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Email:</span>
                    <strong><?= htmlspecialchars($profile['email']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Blood Group:</span>
                    <strong class="text-danger"><?= htmlspecialchars($profile['blood_group'] ?: 'O+') ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Gender:</span>
                    <strong><?= htmlspecialchars($profile['gender'] ?: 'Male') ?></strong>
                </div>
            </div>

            <p class="text-muted small mb-0">Member since <?= date('M Y', strtotime($profile['created_at'])) ?></p>
        </div>
    </div>

    <!-- Edit Profile Form -->
    <div class="col-lg-8">
        <div class="mq-card p-4 p-md-5 bg-white">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/patient/profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <h5 class="fw-bold text-dark mb-3">Personal Demographics</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="pName" class="form-label small fw-semibold text-muted">Full Name *</label>
                        <input type="text" class="form-control" id="pName" name="name" value="<?= htmlspecialchars($profile['name']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="pEmail" class="form-label small fw-semibold text-muted">Email Address (Read-only)</label>
                        <input type="email" class="form-control bg-light" id="pEmail" value="<?= htmlspecialchars($profile['email']) ?>" readonly disabled>
                    </div>

                    <div class="col-md-6">
                        <label for="pPhone" class="form-label small fw-semibold text-muted">Contact Phone</label>
                        <input type="tel" class="form-control" id="pPhone" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="pDob" class="form-label small fw-semibold text-muted">Date of Birth</label>
                        <input type="date" class="form-control" id="pDob" name="dob" value="<?= htmlspecialchars($profile['date_of_birth'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="pGender" class="form-label small fw-semibold text-muted">Gender</label>
                        <select class="form-select" id="pGender" name="gender">
                            <option value="Male" <?= ($profile['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($profile['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= ($profile['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="pBlood" class="form-label small fw-semibold text-muted">Blood Group</label>
                        <select class="form-select" id="pBlood" name="blood_group">
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= ($profile['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="pAddress" class="form-label small fw-semibold text-muted">Residential Address</label>
                        <textarea class="form-control" id="pAddress" name="address" rows="2"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="pEmergency" class="form-label small fw-semibold text-muted">Emergency Contact Number</label>
                        <input type="tel" class="form-control" id="pEmergency" name="emergency_contact" value="<?= htmlspecialchars($profile['emergency_contact'] ?? '') ?>" placeholder="Guardian or relative phone">
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="fw-bold text-dark mb-2">Change Password</h5>
                <p class="text-muted small mb-3">Leave blank if you do not wish to update your password.</p>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="newPwd" class="form-label small fw-semibold text-muted">New Password</label>
                        <input type="password" class="form-control" id="newPwd" name="new_password" placeholder="At least 6 characters">
                    </div>
                    <div class="col-md-6">
                        <label for="confPwd" class="form-label small fw-semibold text-muted">Confirm New Password</label>
                        <input type="password" class="form-control" id="confPwd" name="confirm_password" placeholder="Re-type new password">
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary fw-semibold px-4">
                        <i class="fa-regular fa-floppy-disk me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
