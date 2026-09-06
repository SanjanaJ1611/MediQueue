<?php
/**
 * MediQueue - Admin: Doctor Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$page_title = "Manage Doctors";
$conn = get_db_connection();

// Fetch Departments
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll();

// Handle Add New Doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_doctor') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $specialization = trim($_POST['specialization'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $experience = (int)($_POST['experience_years'] ?? 5);
        $room = trim($_POST['room_number'] ?? 'Suite 101');
        $fee = (float)($_POST['consultation_fee'] ?? 50.00);
        $password = $_POST['password'] ?? 'doctor123';

        if (!empty($name) && !empty($email) && $departmentId > 0) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                set_flash('danger', 'Email already exists in system.');
            } else {
                $conn->beginTransaction();
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insU = $conn->prepare("INSERT INTO users (name, email, password, role, phone, status, created_at) VALUES (?, ?, ?, 'doctor', ?, 'active', NOW())");
                $insU->execute([$name, $email, $hash, $phone]);
                $newUid = $conn->lastInsertId();

                $insD = $conn->prepare("
                    INSERT INTO doctors (user_id, department_id, specialization, qualification, experience_years, room_number, consultation_fee, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $insD->execute([$newUid, $departmentId, $specialization, $qualification, $experience, $room, $fee]);
                $newDocId = $conn->lastInsertId();

                // Add default availability
                $insA = $conn->prepare("
                    INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, slot_duration, status, created_at)
                    VALUES (?, 'All Days', '09:00:00', '17:00:00', 30, 'available', NOW())
                ");
                $insA->execute([$newDocId]);

                $conn->commit();
                set_flash('success', "Doctor {$name} added successfully.");
                header('Location: ' . BASE_URL . '/admin/doctors.php');
                exit;
            }
        }
    }
}

// Handle Delete Doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_doctor') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId) {
            $del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'doctor'");
            $del->execute([$userId]);
            set_flash('success', 'Doctor record deleted successfully.');
            header('Location: ' . BASE_URL . '/admin/doctors.php');
            exit;
        }
    }
}

// Fetch all doctors with department info
$doctors = $conn->query("
    SELECT d.*, u.id as user_id, u.name, u.email, u.phone, dept.department_name,
           COALESCE(da.status, 'available') as avail_status,
           COUNT(a.id) as total_appointments
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN doctor_availability da ON d.id = da.doctor_id
    LEFT JOIN appointments a ON d.id = a.doctor_id
    GROUP BY d.id
    ORDER BY u.name ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Doctor Directory</h3>
        <p class="text-muted mb-0">Manage hospital specialists, departments, consultation fees, and clinical suites.</p>
    </div>
    <button type="button" class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#newDoctorModal">
        <i class="fa-solid fa-user-doctor me-1"></i> Add New Doctor
    </button>
</div>

<!-- Doctors Table -->
<div class="mq-card p-0 bg-white">
    <div class="mq-table-responsive">
        <table class="table mq-table align-middle">
            <thead>
                <tr>
                    <th>Doctor Name</th>
                    <th>Department</th>
                    <th>Specialization & Qualification</th>
                    <th>Room / Suite</th>
                    <th>Fee</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($doctors)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted small">No doctors found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($doctors as $doc): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width: 38px; height: 38px;">
                                        <?= strtoupper(substr($doc['name'], 4, 1)) ?>
                                    </div>
                                    <div>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($doc['name']) ?></strong>
                                        <span class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($doc['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <?= htmlspecialchars($doc['department_name']) ?>
                                </span>
                            </td>
                            <td style="max-width: 250px;">
                                <div class="fw-semibold text-dark small"><?= htmlspecialchars($doc['specialization']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($doc['qualification']) ?></div>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark small"><?= htmlspecialchars($doc['room_number'] ?: 'Suite 101') ?></span>
                            </td>
                            <td>
                                <strong class="text-dark">$<?= number_format($doc['consultation_fee'], 2) ?></strong>
                            </td>
                            <td>
                                <?= get_status_badge($doc['avail_status']) ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= BASE_URL ?>/admin/appointments.php?search=<?= urlencode($doc['name']) ?>" class="btn btn-sm btn-outline-primary" title="View Appointments">
                                        <i class="fa-solid fa-calendar-check"></i>
                                    </a>
                                    <form action="<?= BASE_URL ?>/admin/doctors.php" method="POST" class="d-inline" onsubmit="return confirm('Remove this doctor and their user account?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_doctor">
                                        <input type="hidden" name="user_id" value="<?= $doc['user_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Doctor">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New Doctor -->
<div class="modal fade" id="newDoctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/doctors.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_doctor">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-doctor me-2"></i> Register New Medical Specialist</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Full Doctor Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="Dr. Arthur Mitchell" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Email Address (Login) *</label>
                            <input type="email" name="email" class="form-control" placeholder="doctor@mediqueue.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+1 (555) 019-2000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Default Password</label>
                            <input type="text" name="password" class="form-control" value="doctor123">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Department *</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">-- Choose Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Assigned Suite / Room *</label>
                            <input type="text" name="room_number" class="form-control" value="Suite 101" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-muted">Clinical Specialization *</label>
                            <input type="text" name="specialization" class="form-control" placeholder="e.g. Interventional Cardiology & Hypertension" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-muted">Qualifications & Degrees</label>
                            <input type="text" name="qualification" class="form-control" placeholder="e.g. MD, FACC, Harvard Medical School">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Years Experience</label>
                            <input type="number" name="experience_years" class="form-control" value="5" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Consultation Fee ($)</label>
                            <input type="number" step="0.01" name="consultation_fee" class="form-control" value="65.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold">Save Doctor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
