<?php
/**
 * MediQueue - Doctor Profile Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('doctor');

$page_title = "Doctor Profile";
$conn = get_db_connection();
$userId = $_SESSION['user_id'];
$doctorId = $_SESSION['role_specific_id'] ?? 0;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please reload and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $experience = (int)($_POST['experience_years'] ?? 0);
        $roomNumber = trim($_POST['room_number'] ?? '');
        $fee = (float)($_POST['consultation_fee'] ?? 0);

        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($specialization)) {
            $error = 'Doctor name and specialization cannot be empty.';
        } else {
            try {
                $conn->beginTransaction();

                // 1. Update user record
                $uStmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $uStmt->execute([$name, $phone, $userId]);
                $_SESSION['user_name'] = $name;

                // 2. Update doctor record
                $dStmt = $conn->prepare("
                    UPDATE doctors 
                    SET specialization = ?, qualification = ?, experience_years = ?, room_number = ?, consultation_fee = ?
                    WHERE id = ?
                ");
                $dStmt->execute([$specialization, $qualification, $experience, $roomNumber, $fee, $doctorId]);
                $_SESSION['room_number'] = $roomNumber;

                // 3. Update password if requested
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
                set_flash('success', 'Doctor profile updated successfully.');
                header('Location: ' . BASE_URL . '/doctor/profile.php');
                exit;

            } catch (Exception $e) {
                $conn->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

// Fetch doctor details
$stmt = $conn->prepare("
    SELECT u.name, u.email, u.phone, d.*, dept.department_name
    FROM users u
    JOIN doctors d ON u.id = d.user_id
    JOIN departments dept ON d.department_id = dept.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Doctor Profile & Credentials</h3>
        <p class="text-muted mb-0">Manage your clinical specialties, consultation fee, room allocation, and account credentials.</p>
    </div>
</div>

<div class="row g-4 justify-content-center">
    <!-- Overview Card -->
    <div class="col-lg-4">
        <div class="mq-card text-center p-4">
            <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2.2rem;">
                <?= strtoupper(substr($profile['name'], 4, 1)) ?>
            </div>
            <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($profile['name']) ?></h5>
            <span class="badge bg-primary-subtle text-primary mb-3"><?= htmlspecialchars($profile['department_name']) ?> Specialist</span>

            <div class="p-3 bg-light rounded-3 border text-start small mb-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Assigned Room:</span>
                    <strong class="text-primary"><?= htmlspecialchars($profile['room_number'] ?: 'Suite 101') ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Fee per Consult:</span>
                    <strong class="text-dark">$<?= number_format($profile['consultation_fee'], 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Experience:</span>
                    <strong><?= $profile['experience_years'] ?> Years</strong>
                </div>
            </div>
            <p class="text-muted small mb-0"><?= htmlspecialchars($profile['email']) ?></p>
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

            <form action="<?= BASE_URL ?>/doctor/profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <h5 class="fw-bold text-dark mb-3">Clinical Profile</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="dName" class="form-label small fw-semibold text-muted">Full Doctor Name *</label>
                        <input type="text" class="form-control" id="dName" name="name" value="<?= htmlspecialchars($profile['name']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="dEmail" class="form-label small fw-semibold text-muted">Email (Read-only)</label>
                        <input type="email" class="form-control bg-light" id="dEmail" value="<?= htmlspecialchars($profile['email']) ?>" readonly disabled>
                    </div>

                    <div class="col-md-6">
                        <label for="dPhone" class="form-label small fw-semibold text-muted">Contact Phone</label>
                        <input type="tel" class="form-control" id="dPhone" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="dDept" class="form-label small fw-semibold text-muted">Department (Read-only)</label>
                        <input type="text" class="form-control bg-light" id="dDept" value="<?= htmlspecialchars($profile['department_name']) ?>" readonly disabled>
                    </div>

                    <div class="col-md-12">
                        <label for="dSpec" class="form-label small fw-semibold text-muted">Specialization *</label>
                        <input type="text" class="form-control" id="dSpec" name="specialization" value="<?= htmlspecialchars($profile['specialization']) ?>" required>
                    </div>

                    <div class="col-md-12">
                        <label for="dQual" class="form-label small fw-semibold text-muted">Qualifications & Medical Degrees</label>
                        <input type="text" class="form-control" id="dQual" name="qualification" value="<?= htmlspecialchars($profile['qualification']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="dExp" class="form-label small fw-semibold text-muted">Years Experience</label>
                        <input type="number" class="form-control" id="dExp" name="experience_years" value="<?= (int)$profile['experience_years'] ?>" min="0">
                    </div>

                    <div class="col-md-4">
                        <label for="dRoom" class="form-label small fw-semibold text-muted">Assigned Room Number</label>
                        <input type="text" class="form-control" id="dRoom" name="room_number" value="<?= htmlspecialchars($profile['room_number']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="dFee" class="form-label small fw-semibold text-muted">Consultation Fee ($)</label>
                        <input type="number" step="0.01" class="form-control" id="dFee" name="consultation_fee" value="<?= (float)$profile['consultation_fee'] ?>">
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="fw-bold text-dark mb-2">Change Password</h5>
                <p class="text-muted small mb-3">Leave blank if you do not want to alter your password.</p>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="newPass" class="form-label small fw-semibold text-muted">New Password</label>
                        <input type="password" class="form-control" id="newPass" name="new_password" placeholder="At least 6 characters">
                    </div>
                    <div class="col-md-6">
                        <label for="confPass" class="form-label small fw-semibold text-muted">Confirm New Password</label>
                        <input type="password" class="form-control" id="confPass" name="confirm_password" placeholder="Re-type new password">
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary fw-semibold px-4 py-2">
                        <i class="fa-regular fa-floppy-disk me-1"></i> Save Doctor Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
