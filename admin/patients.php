<?php
/**
 * MediQueue - Admin: Patient Master Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$page_title = "Manage Patients";
$conn = get_db_connection();

// Handle Delete Patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_patient') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId) {
            $del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'");
            $del->execute([$userId]);
            set_flash('success', 'Patient record and associated histories deleted.');
            header('Location: ' . BASE_URL . '/admin/patients.php');
            exit;
        }
    }
}

// Handle Add Patient by Admin/Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_patient') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');
        $bloodGroup = trim($_POST['blood_group'] ?? 'O+');
        $address = trim($_POST['address'] ?? '');
        $emergency = trim($_POST['emergency_contact'] ?? '');
        $password = $_POST['password'] ?? 'patient123';

        if (!empty($name) && !empty($email)) {
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                set_flash('danger', 'Email already exists in system.');
            } else {
                $conn->beginTransaction();
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insU = $conn->prepare("INSERT INTO users (name, email, password, role, phone, status, created_at) VALUES (?, ?, ?, 'patient', ?, 'active', NOW())");
                $insU->execute([$name, $email, $hash, $phone]);
                $newUid = $conn->lastInsertId();

                $insP = $conn->prepare("INSERT INTO patients (user_id, date_of_birth, gender, blood_group, address, emergency_contact, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $insP->execute([$newUid, !empty($dob) ? $dob : null, $gender, $bloodGroup, $address, $emergency]);

                $conn->commit();
                set_flash('success', "Patient {$name} successfully registered.");
                header('Location: ' . BASE_URL . '/admin/patients.php');
                exit;
            }
        }
    }
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$bloodFilter = trim($_GET['blood_group'] ?? '');

$query = "
    SELECT p.*, u.name, u.email, u.phone, u.status as user_status,
           COUNT(a.id) as total_appointments
    FROM patients p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN appointments a ON p.id = a.patient_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($bloodFilter)) {
    $query .= " AND p.blood_group = ?";
    $params[] = $bloodFilter;
}

$query .= " GROUP BY p.id ORDER BY p.id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Patient Registry</h3>
        <p class="text-muted mb-0">Browse, search, and manage registered patients and their clinical visit histories.</p>
    </div>
    <button type="button" class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#newPatientModal">
        <i class="fa-solid fa-user-plus me-1"></i> Register New Patient
    </button>
</div>

<!-- Search Bar -->
<div class="mq-card p-3 mb-4 bg-white">
    <form action="<?= BASE_URL ?>/admin/patients.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-4">
            <select name="blood_group" class="form-select">
                <option value="">All Blood Groups</option>
                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                    <option value="<?= $bg ?>" <?= $bloodFilter === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100 fw-semibold">Filter</button>
            <?php if ($search || $bloodFilter): ?>
                <a href="<?= BASE_URL ?>/admin/patients.php" class="btn btn-light border" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Patients Table -->
<div class="mq-card p-0 bg-white">
    <div class="mq-table-responsive">
        <table class="table mq-table align-middle">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Contact & Email</th>
                    <th>Demographics</th>
                    <th>Address</th>
                    <th>Total Visits</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted small">No patients found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width: 38px; height: 38px;">
                                        <?= strtoupper(substr($p['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($p['name']) ?></strong>
                                        <span class="text-muted" style="font-size: 0.75rem;">Reg: <?= date('M d, Y', strtotime($p['created_at'])) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark small fw-semibold"><?= htmlspecialchars($p['phone'] ?: 'N/A') ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($p['email']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= htmlspecialchars($p['blood_group'] ?: 'O+') ?></span>
                                <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($p['gender'] ?: 'Male') ?></span>
                                <?php if ($p['date_of_birth']): ?>
                                    <span class="text-muted d-block small mt-1"><?= format_date($p['date_of_birth']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 200px;">
                                <span class="small text-muted text-truncate d-block" title="<?= htmlspecialchars($p['address'] ?? '') ?>">
                                    <?= htmlspecialchars($p['address'] ?: 'Not recorded') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary fw-bold"><?= $p['total_appointments'] ?> Appointments</span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- View Appointments Modal Trigger -->
                                    <a href="<?= BASE_URL ?>/admin/appointments.php?search=<?= urlencode($p['name']) ?>" class="btn btn-sm btn-outline-primary" title="View Patient Appointments">
                                        <i class="fa-solid fa-calendar-check"></i>
                                    </a>

                                    <!-- Delete Patient Form -->
                                    <form action="<?= BASE_URL ?>/admin/patients.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this patient and their history?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_patient">
                                        <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Patient">
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

<!-- Modal: Add New Patient -->
<div class="modal fade" id="newPatientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/patients.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_patient">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2"></i> Register New Patient</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Full Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="Eleanor Vance" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Email Address *</label>
                            <input type="email" name="email" class="form-control" placeholder="patient@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Contact Phone *</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+1 (555) 000-0000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Default Password</label>
                            <input type="text" name="password" class="form-control" value="patient123">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Date of Birth</label>
                            <input type="date" name="dob" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Blood Group</label>
                            <select name="blood_group" class="form-select">
                                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                    <option value="<?= $bg ?>"><?= $bg ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Residential Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Street, City, State">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Emergency Contact</label>
                            <input type="tel" name="emergency_contact" class="form-control" placeholder="Guardian / relative phone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold">Save & Register Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
