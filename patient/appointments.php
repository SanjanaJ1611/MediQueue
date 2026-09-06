<?php
/**
 * MediQueue - Patient Appointments List & Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('patient');

$page_title = "My Appointments";
$conn = get_db_connection();
$patientId = $_SESSION['role_specific_id'] ?? 0;
$userId = $_SESSION['user_id'];

// Handle Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $appId = (int)($_POST['appointment_id'] ?? 0);
        
        // Verify appointment belongs to this patient
        $check = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND patient_id = ?");
        $check->execute([$appId, $patientId]);
        $app = $check->fetch();

        if ($app && !in_array($app['status'], ['completed', 'cancelled'])) {
            $conn->beginTransaction();
            // Update appointment
            $cStmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
            $cStmt->execute([$appId]);

            // Cancel any associated queue
            $qStmt = $conn->prepare("UPDATE queues SET status = 'cancelled' WHERE appointment_id = ?");
            $qStmt->execute([$appId]);

            // Recalculate queue if it was today
            if ($app['appointment_date'] === date('Y-m-d')) {
                recalculate_doctor_queue_times($conn, $app['doctor_id']);
            }

            create_notification($conn, $userId, 'Appointment Cancelled', "Your appointment ({$app['appointment_number']}) has been successfully cancelled.", 'warning');

            $conn->commit();
            set_flash('success', 'Appointment successfully cancelled.');
        } else {
            set_flash('danger', 'Unable to cancel this appointment.');
        }
    }
    header('Location: ' . BASE_URL . '/patient/appointments.php');
    exit;
}

// Filters
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT a.*, d.room_number, d.consultation_fee, u.name as doctor_name, dept.department_name,
           q.id as queue_id, q.queue_number, q.status as queue_status, q.queue_position
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN queues q ON a.id = q.appointment_id
    WHERE a.patient_id = ?
";
$params = [$patientId];

if (!empty($statusFilter)) {
    if ($statusFilter === 'upcoming') {
        $query .= " AND a.status IN ('confirmed', 'pending') AND a.appointment_date >= CURDATE()";
    } elseif ($statusFilter === 'in_queue') {
        $query .= " AND (a.status = 'in_queue' OR q.status IN ('waiting', 'called', 'in_consultation'))";
    } else {
        $query .= " AND a.status = ?";
        $params[] = $statusFilter;
    }
}

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR a.appointment_number LIKE ? OR dept.department_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">My Appointments</h3>
        <p class="text-muted mb-0">View your consultation history, join virtual queues, or cancel visits.</p>
    </div>
    <a href="<?= BASE_URL ?>/patient/book_appointment.php" class="btn btn-primary fw-semibold">
        <i class="fa-solid fa-plus me-1"></i> Book New Appointment
    </a>
</div>

<!-- Filter Tabs & Search -->
<div class="mq-card p-3 mb-4 bg-white">
    <div class="row g-3 align-items-center">
        <div class="col-lg-7">
            <div class="nav nav-pills flex-wrap gap-1">
                <a href="<?= BASE_URL ?>/patient/appointments.php" class="nav-link <?= empty($statusFilter) ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">All</a>
                <a href="<?= BASE_URL ?>/patient/appointments.php?status=upcoming" class="nav-link <?= $statusFilter === 'upcoming' ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">Upcoming</a>
                <a href="<?= BASE_URL ?>/patient/appointments.php?status=in_queue" class="nav-link <?= $statusFilter === 'in_queue' ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">In Queue</a>
                <a href="<?= BASE_URL ?>/patient/appointments.php?status=completed" class="nav-link <?= $statusFilter === 'completed' ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">Completed</a>
                <a href="<?= BASE_URL ?>/patient/appointments.php?status=cancelled" class="nav-link <?= $statusFilter === 'cancelled' ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">Cancelled</a>
            </div>
        </div>
        <div class="col-lg-5">
            <form action="<?= BASE_URL ?>/patient/appointments.php" method="GET" class="d-flex gap-2">
                <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by doctor or ID..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn btn-outline-primary fw-semibold">Search</button>
            </form>
        </div>
    </div>
</div>

<!-- Appointments Table -->
<div class="mq-card p-0 bg-white">
    <div class="mq-table-responsive">
        <table class="table mq-table">
            <thead>
                <tr>
                    <th>Ticket / ID</th>
                    <th>Doctor & Department</th>
                    <th>Date & Time</th>
                    <th>Status</th>
                    <th>Virtual Queue</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fa-regular fa-calendar-xmark fa-2x mb-2 d-block text-muted"></i>
                            No appointments found matching your criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $app): ?>
                        <tr>
                            <td>
                                <strong class="text-primary d-block"><?= htmlspecialchars($app['appointment_number']) ?></strong>
                                <span class="text-muted" style="font-size: 0.75rem;">Created <?= date('M d, Y', strtotime($app['created_at'])) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($app['doctor_name']) ?></div>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($app['department_name']) ?> &bull; <?= htmlspecialchars($app['room_number'] ?: 'Suite 101') ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= format_date($app['appointment_date']) ?></div>
                                <div class="text-muted small"><?= format_time($app['appointment_time']) ?></div>
                            </td>
                            <td>
                                <?= get_status_badge($app['status']) ?>
                            </td>
                            <td>
                                <?php if ($app['queue_number']): ?>
                                    <span class="badge bg-primary text-white">
                                        <i class="fa-solid fa-ticket me-1"></i> <?= htmlspecialchars($app['queue_number']) ?>
                                    </span>
                                    <span class="d-block small text-muted mt-1"><?= ucfirst($app['queue_status'] ?? 'waiting') ?></span>
                                <?php elseif ($app['appointment_date'] === date('Y-m-d') && in_array($app['status'], ['confirmed', 'pending'])): ?>
                                    <form action="<?= BASE_URL ?>/patient/queue.php" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="join">
                                        <input type="hidden" name="appointment_id" value="<?= $app['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary fw-semibold py-1">
                                            <i class="fa-solid fa-list-ol me-1"></i> Join Queue
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">Not in queue</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($app['queue_number'] && in_array($app['queue_status'], ['waiting', 'called', 'in_consultation'])): ?>
                                        <a href="<?= BASE_URL ?>/patient/queue.php" class="btn btn-sm btn-primary" title="Track Queue">
                                            <i class="fa-solid fa-satellite-dish"></i>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Details Button -->
                                    <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#appModal_<?= $app['id'] ?>" title="View Details">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>

                                    <!-- Cancel Button (if cancellable) -->
                                    <?php if (!in_array($app['status'], ['completed', 'cancelled', 'no_show'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal_<?= $app['id'] ?>" title="Cancel Appointment">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Details Modal -->
                                <div class="modal fade" id="appModal_<?= $app['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title fw-bold">Appointment Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="text-center mb-3">
                                                    <span class="badge bg-primary px-3 py-2 fs-6"><?= htmlspecialchars($app['appointment_number']) ?></span>
                                                </div>
                                                <ul class="list-group list-group-flush small">
                                                    <li class="list-group-item d-flex justify-content-between">
                                                        <span class="text-muted">Doctor:</span>
                                                        <strong><?= htmlspecialchars($app['doctor_name']) ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between">
                                                        <span class="text-muted">Department:</span>
                                                        <strong><?= htmlspecialchars($app['department_name']) ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between">
                                                        <span class="text-muted">Room Number:</span>
                                                        <strong><?= htmlspecialchars($app['room_number'] ?: 'Suite 101') ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between">
                                                        <span class="text-muted">Date & Time:</span>
                                                        <strong><?= format_date($app['appointment_date']) ?> at <?= format_time($app['appointment_time']) ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between">
                                                        <span class="text-muted">Consultation Fee:</span>
                                                        <strong>$<?= number_format($app['consultation_fee'], 2) ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between">
                                                        <span class="text-muted">Status:</span>
                                                        <div><?= get_status_badge($app['status']) ?></div>
                                                    </li>
                                                    <?php if (!empty($app['reason'])): ?>
                                                    <li class="list-group-item">
                                                        <span class="text-muted d-block mb-1">Reason / Symptoms:</span>
                                                        <div class="p-2 bg-light rounded text-dark"><?= nl2br(htmlspecialchars($app['reason'])) ?></div>
                                                    </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cancel Confirmation Modal -->
                                <?php if (!in_array($app['status'], ['completed', 'cancelled', 'no_show'])): ?>
                                <div class="modal fade" id="cancelModal_<?= $app['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <form action="<?= BASE_URL ?>/patient/appointments.php" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="appointment_id" value="<?= $app['id'] ?>">

                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> Cancel Appointment</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body text-center py-4">
                                                    <p class="mb-2">Are you sure you want to cancel your appointment with <strong><?= htmlspecialchars($app['doctor_name']) ?></strong>?</p>
                                                    <p class="text-muted small mb-0">Scheduled for: <strong><?= format_date($app['appointment_date']) ?> at <?= format_time($app['appointment_time']) ?></strong>. This will also release your virtual queue ticket.</p>
                                                </div>
                                                <div class="modal-footer justify-content-center">
                                                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">No, Keep Appointment</button>
                                                    <button type="submit" class="btn btn-danger fw-semibold">Yes, Cancel Appointment</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
