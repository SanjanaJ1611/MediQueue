<?php
/**
 * MediQueue - Admin: All Hospital Appointments Oversight
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$page_title = "Manage Appointments";
$conn = get_db_connection();

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $appId = (int)($_POST['appointment_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $valid = ['pending', 'confirmed', 'in_queue', 'completed', 'cancelled', 'no_show'];

        if ($appId && in_array($newStatus, $valid, true)) {
            $upd = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            $upd->execute([$newStatus, $appId]);

            // Sync with queue if any
            $qUpd = $conn->prepare("UPDATE queues SET status = ? WHERE appointment_id = ?");
            $qUpd->execute([$newStatus, $appId]);

            set_flash('success', "Appointment status updated to " . ucfirst(str_replace('_', ' ', $newStatus)));
            header('Location: ' . BASE_URL . '/admin/appointments.php');
            exit;
        }
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$deptFilter = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$statusFilter = trim($_GET['status'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');

$departments = $conn->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll();

$query = "
    SELECT a.*, p.gender, p.blood_group,
           u.name as patient_name, u.phone as patient_phone,
           doc_u.name as doctor_name, dept.department_name, d.room_number,
           q.queue_number, q.status as queue_status
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users doc_u ON d.user_id = doc_u.id
    JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN queues q ON a.id = q.appointment_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR doc_u.name LIKE ? OR a.appointment_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($deptFilter > 0) {
    $query .= " AND d.department_id = ?";
    $params[] = $deptFilter;
}

if (!empty($statusFilter)) {
    $query .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if (!empty($dateFilter)) {
    $query .= " AND a.appointment_date = ?";
    $params[] = $dateFilter;
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Hospital Appointments Master</h3>
        <p class="text-muted mb-0">Monitor and update all patient visits across clinical departments.</p>
    </div>
</div>

<!-- Search & Multi-Filter Bar -->
<div class="mq-card p-3 mb-4 bg-white">
    <form action="<?= BASE_URL ?>/admin/appointments.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search patient or doctor..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="department_id" class="form-select">
                <option value="0">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>" <?= $deptFilter == $dept['id'] ? 'selected' : '' ?>><?= htmlspecialchars($dept['department_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="in_queue" <?= $statusFilter === 'in_queue' ? 'selected' : '' ?>>In Queue</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="no_show" <?= $statusFilter === 'no_show' ? 'selected' : '' ?>>No Show</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary w-100 fw-semibold">Filter</button>
            <?php if ($search || $deptFilter || $statusFilter || $dateFilter): ?>
                <a href="<?= BASE_URL ?>/admin/appointments.php" class="btn btn-light border" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Appointments Table -->
<div class="mq-card p-0 bg-white">
    <div class="mq-table-responsive">
        <table class="table mq-table align-middle">
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Patient</th>
                    <th>Doctor & Dept</th>
                    <th>Schedule</th>
                    <th>Queue Ticket</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted small">No appointments matching filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $app): ?>
                        <tr>
                            <td>
                                <strong class="text-primary"><?= htmlspecialchars($app['appointment_number']) ?></strong>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($app['patient_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($app['patient_phone'] ?: 'N/A') ?></div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($app['doctor_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($app['department_name']) ?> (<?= htmlspecialchars($app['room_number'] ?: 'Suite 101') ?>)</div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= format_date($app['appointment_date']) ?></div>
                                <div class="text-muted small"><?= format_time($app['appointment_time']) ?></div>
                            </td>
                            <td>
                                <?php if ($app['queue_number']): ?>
                                    <span class="badge bg-primary text-white">#<?= htmlspecialchars($app['queue_number']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= get_status_badge($app['status']) ?>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Update
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <form action="<?= BASE_URL ?>/admin/appointments.php" method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?= $app['id'] ?>">
                                            <li><button class="dropdown-item small" type="submit" name="status" value="confirmed">Mark Confirmed</button></li>
                                            <li><button class="dropdown-item small" type="submit" name="status" value="completed">Mark Completed</button></li>
                                            <li><button class="dropdown-item small" type="submit" name="status" value="no_show">Mark No Show</button></li>
                                            <li><button class="dropdown-item small text-danger" type="submit" name="status" value="cancelled">Mark Cancelled</button></li>
                                        </form>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
