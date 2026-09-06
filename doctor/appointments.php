<?php
/**
 * MediQueue - Doctor Appointments Master List
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('doctor');

$page_title = "Doctor Appointments";
$conn = get_db_connection();
$doctorId = $_SESSION['role_specific_id'] ?? 0;

// Handle manual status update from doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $appId = (int)($_POST['appointment_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $valid = ['confirmed', 'completed', 'cancelled', 'no_show'];

        if ($appId && in_array($newStatus, $valid, true)) {
            $upd = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
            $upd->execute([$newStatus, $appId, $doctorId]);

            // Sync with queue if any
            $qUpd = $conn->prepare("UPDATE queues SET status = ? WHERE appointment_id = ?");
            $qUpd->execute([$newStatus, $appId]);

            set_flash('success', "Appointment status updated to " . ucfirst(str_replace('_', ' ', $newStatus)));
            header('Location: ' . BASE_URL . '/doctor/appointments.php');
            exit;
        }
    }
}

// Filters
$dateFilter = trim($_GET['date'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT a.*, p.gender, p.blood_group, p.date_of_birth, p.address, p.emergency_contact,
           u.name as patient_name, u.phone as patient_phone, u.email as patient_email,
           q.queue_number, q.status as queue_status
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN queues q ON a.id = q.appointment_id
    WHERE a.doctor_id = ?
";
$params = [$doctorId];

if (!empty($dateFilter)) {
    $query .= " AND a.appointment_date = ?";
    $params[] = $dateFilter;
}

if (!empty($statusFilter)) {
    $query .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR a.appointment_number LIKE ? OR u.phone LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Appointment Schedule</h3>
        <p class="text-muted mb-0">View all clinical appointments, consultation histories, and patient complaints.</p>
    </div>
</div>

<!-- Search & Date Filter Card -->
<div class="mq-card p-3 mb-4 bg-white">
    <form action="<?= BASE_URL ?>/doctor/appointments.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search patient or ticket #..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>" placeholder="Filter by date">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="in_queue" <?= $statusFilter === 'in_queue' ? 'selected' : '' ?>>In Queue</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="no_show" <?= $statusFilter === 'no_show' ? 'selected' : '' ?>>No Show</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100 fw-semibold">Filter</button>
            <?php if ($dateFilter || $statusFilter || $search): ?>
                <a href="<?= BASE_URL ?>/doctor/appointments.php" class="btn btn-light border" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
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
                    <th>Patient Name</th>
                    <th>Date & Time</th>
                    <th>Complaint / Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted small">
                            No appointments found matching your filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $app): ?>
                        <tr>
                            <td>
                                <strong class="text-primary"><?= htmlspecialchars($app['appointment_number']) ?></strong>
                                <?php if ($app['queue_number']): ?>
                                    <span class="d-block badge bg-primary-subtle text-primary border border-primary-subtle mt-1">#<?= htmlspecialchars($app['queue_number']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($app['patient_name']) ?></div>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($app['gender'] ?? 'N/A') ?> &bull; Blood: <strong class="text-danger"><?= htmlspecialchars($app['blood_group'] ?? 'N/A') ?></strong>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= format_date($app['appointment_date']) ?></div>
                                <div class="text-muted small"><?= format_time($app['appointment_time']) ?></div>
                            </td>
                            <td style="max-width: 250px;">
                                <div class="small text-muted text-truncate" title="<?= htmlspecialchars($app['reason'] ?? '') ?>">
                                    <?= htmlspecialchars($app['reason'] ?: 'Routine consultation / checkup') ?>
                                </div>
                            </td>
                            <td>
                                <?= get_status_badge($app['status']) ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- Details Button -->
                                    <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#docAppModal_<?= $app['id'] ?>" title="Patient Details">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>

                                    <!-- Quick Status Toggle Dropdown -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Status
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                            <form action="<?= BASE_URL ?>/doctor/appointments.php" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?= $app['id'] ?>">
                                                <li><button class="dropdown-item small" type="submit" name="status" value="confirmed"><i class="fa-solid fa-calendar-check me-2 text-primary"></i> Confirmed</button></li>
                                                <li><button class="dropdown-item small" type="submit" name="status" value="completed"><i class="fa-solid fa-check-double me-2 text-success"></i> Completed</button></li>
                                                <li><button class="dropdown-item small" type="submit" name="status" value="no_show"><i class="fa-solid fa-user-slash me-2 text-secondary"></i> No Show</button></li>
                                                <li><button class="dropdown-item small text-danger" type="submit" name="status" value="cancelled"><i class="fa-solid fa-ban me-2"></i> Cancelled</button></li>
                                            </form>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Detailed Patient Modal -->
                                <div class="modal fade" id="docAppModal_<?= $app['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title fw-bold">Patient Clinical Record</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="d-flex align-items-center gap-3 mb-3">
                                                    <div class="user-avatar" style="width: 50px; height: 50px;">
                                                        <?= strtoupper(substr($app['patient_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <h5 class="fw-bold mb-0"><?= htmlspecialchars($app['patient_name']) ?></h5>
                                                        <div class="text-muted small"><?= htmlspecialchars($app['patient_email']) ?> &bull; <?= htmlspecialchars($app['patient_phone']) ?></div>
                                                    </div>
                                                </div>

                                                <div class="p-3 bg-light rounded-3 border mb-3 small">
                                                    <div class="row g-2">
                                                        <div class="col-6">
                                                            <span class="text-muted d-block">DOB:</span>
                                                            <strong><?= $app['date_of_birth'] ? format_date($app['date_of_birth']) : 'N/A' ?></strong>
                                                        </div>
                                                        <div class="col-6">
                                                            <span class="text-muted d-block">Blood Group:</span>
                                                            <strong class="text-danger"><?= htmlspecialchars($app['blood_group'] ?: 'O+') ?></strong>
                                                        </div>
                                                        <div class="col-6">
                                                            <span class="text-muted d-block">Gender:</span>
                                                            <strong><?= htmlspecialchars($app['gender'] ?: 'Male') ?></strong>
                                                        </div>
                                                        <div class="col-6">
                                                            <span class="text-muted d-block">Emergency Contact:</span>
                                                            <strong><?= htmlspecialchars($app['emergency_contact'] ?: 'N/A') ?></strong>
                                                        </div>
                                                        <div class="col-12">
                                                            <span class="text-muted d-block">Address:</span>
                                                            <strong><?= htmlspecialchars($app['address'] ?: 'N/A') ?></strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <span class="text-muted small fw-semibold d-block mb-1">Chief Medical Complaint:</span>
                                                    <div class="p-2 border rounded bg-white small text-dark">
                                                        <?= nl2br(htmlspecialchars($app['reason'] ?: 'No symptoms described.')) ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
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
