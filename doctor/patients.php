<?php
/**
 * MediQueue - Doctor: Assigned Patients Directory
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('doctor');

$page_title = "Assigned Patients";
$conn = get_db_connection();
$doctorId = $_SESSION['role_specific_id'] ?? 0;

$search = trim($_GET['search'] ?? '');

$query = "
    SELECT p.*, u.name as patient_name, u.email as patient_email, u.phone as patient_phone,
           COUNT(a.id) as total_appointments,
           MAX(a.appointment_date) as last_visit_date
    FROM patients p
    JOIN users u ON p.user_id = u.id
    JOIN appointments a ON p.id = a.patient_id
    WHERE a.doctor_id = ?
";
$params = [$doctorId];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$query .= " GROUP BY p.id ORDER BY last_visit_date DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">My Patient Directory</h3>
        <p class="text-muted mb-0">Review clinical patient profiles and consultation track records assigned to you.</p>
    </div>
</div>

<!-- Search Bar -->
<div class="mq-card p-3 mb-4 bg-white">
    <form action="<?= BASE_URL ?>/doctor/patients.php" method="GET" class="d-flex gap-2">
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search patient name, phone, or email..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn btn-primary fw-semibold px-4">Search</button>
        <?php if ($search): ?>
            <a href="<?= BASE_URL ?>/doctor/patients.php" class="btn btn-light border" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
        <?php endif; ?>
    </form>
</div>

<!-- Patients Grid -->
<div class="row g-4">
    <?php if (empty($patients)): ?>
        <div class="col-12">
            <div class="mq-card text-center py-5">
                <div class="mq-stat-icon primary mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <h5 class="fw-bold">No Patients Found</h5>
                <p class="text-muted small">Patients with appointments booked under your name will appear here.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($patients as $pt): ?>
            <div class="col-md-6 col-lg-4">
                <div class="mq-card h-100 p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="user-avatar" style="width: 50px; height: 50px; font-size: 1.25rem;">
                            <?= strtoupper(substr($pt['patient_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($pt['patient_name']) ?></h5>
                            <span class="text-muted small">
                                <?= htmlspecialchars($pt['gender'] ?? 'N/A') ?> &bull; Blood: <strong class="text-danger"><?= htmlspecialchars($pt['blood_group'] ?: 'O+') ?></strong>
                            </span>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border small mb-3 flex-grow-1">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Contact:</span>
                            <strong class="text-dark"><?= htmlspecialchars($pt['patient_phone'] ?: 'N/A') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Email:</span>
                            <strong class="text-dark"><?= htmlspecialchars($pt['patient_email']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Total Consultations:</span>
                            <span class="badge bg-primary-subtle text-primary"><?= $pt['total_appointments'] ?> Visits</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Last Visit:</span>
                            <strong><?= format_date($pt['last_visit_date']) ?></strong>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="<?= BASE_URL ?>/doctor/appointments.php?search=<?= urlencode($pt['patient_name']) ?>" class="btn btn-sm btn-outline-primary fw-semibold w-100">
                            <i class="fa-regular fa-calendar-check me-1"></i> View Appointments History
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
