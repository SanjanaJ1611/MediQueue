<?php
/**
 * MediQueue - Patient Dashboard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('patient');

$page_title = "Patient Dashboard";
$conn = get_db_connection();
$patientId = $_SESSION['role_specific_id'] ?? 0;
$userId = $_SESSION['user_id'];

// Fetch today's appointment (if any)
$todayAppStmt = $conn->prepare("
    SELECT a.*, d.room_number, d.consultation_fee, u.name as doctor_name, dept.department_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    WHERE a.patient_id = ? 
      AND a.appointment_date = CURDATE()
      AND a.status NOT IN ('cancelled')
    ORDER BY a.appointment_time ASC
    LIMIT 1
");
$todayAppStmt->execute([$patientId]);
$todayAppointment = $todayAppStmt->fetch();

// Fetch active virtual queue for today (if patient joined)
$activeQueue = null;
if ($todayAppointment) {
    $qStmt = $conn->prepare("
        SELECT q.*, d.room_number, u.name as doctor_name, dept.department_name
        FROM queues q
        JOIN doctors d ON q.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        JOIN departments dept ON d.department_id = dept.id
        WHERE q.appointment_id = ?
        LIMIT 1
    ");
    $qStmt->execute([$todayAppointment['id']]);
    $activeQueue = $qStmt->fetch();

    if ($activeQueue) {
        // Calculate real-time position and waiting time
        $activeQueue['queue_position'] = calculate_queue_position($conn, $activeQueue['doctor_id'], $activeQueue['id']);
        $activeQueue['patients_ahead'] = max(0, $activeQueue['queue_position'] - 1);
        $activeQueue['estimated_waiting_time'] = $activeQueue['status'] === 'waiting' ? ($activeQueue['patients_ahead'] * 15) : 0;
    }
}

// Fetch total upcoming appointments
$upcomingStmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM appointments 
    WHERE patient_id = ? 
      AND (appointment_date > CURDATE() OR (appointment_date = CURDATE() AND status IN ('pending', 'confirmed', 'in_queue')))
");
$upcomingStmt->execute([$patientId]);
$upcomingCount = (int)$upcomingStmt->fetchColumn();

// Fetch total completed appointments
$completedStmt = $conn->prepare("
    SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'completed'
");
$completedStmt->execute([$patientId]);
$completedCount = (int)$completedStmt->fetchColumn();

// Fetch recent appointments list
$recentAppStmt = $conn->prepare("
    SELECT a.*, u.name as doctor_name, dept.department_name, d.room_number,
           q.id as queue_id, q.status as queue_status, q.queue_number
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN queues q ON a.id = q.appointment_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 5
");
$recentAppStmt->execute([$patientId]);
$recentAppointments = $recentAppStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Section -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Hello, <?= htmlspecialchars($_SESSION['user_name']) ?> 👋</h3>
        <p class="text-muted mb-0">Welcome to your patient portal. Track your virtual queue and appointments.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/patient/book_appointment.php" class="btn btn-primary fw-semibold shadow-sm">
            <i class="fa-solid fa-calendar-plus me-1"></i> Book Appointment
        </a>
        <a href="<?= BASE_URL ?>/patient/doctors.php" class="btn btn-outline-secondary fw-semibold">
            <i class="fa-solid fa-user-doctor me-1"></i> Browse Doctors
        </a>
    </div>
</div>

<!-- Alert Banner if Doctor is Calling -->
<?php if ($activeQueue && $activeQueue['status'] === 'called'): ?>
<div class="alert alert-primary alert-dismissible fade show d-flex align-items-center mb-4 border-2 border-primary" role="alert">
    <div class="step-circle bg-primary text-white me-3" style="width: 50px; height: 50px; font-size: 1.3rem;">
        <i class="fa-solid fa-bullhorn"></i>
    </div>
    <div class="flex-grow-1">
        <h5 class="alert-heading mb-1 fw-bold">Doctor is Calling You Now!</h5>
        <p class="mb-0">
            Dr. <strong><?= htmlspecialchars($activeQueue['doctor_name']) ?></strong> is ready for your consultation in 
            <strong><?= htmlspecialchars($activeQueue['room_number'] ?: 'Suite 101') ?></strong>. Please proceed to the room.
        </p>
    </div>
    <a href="<?= BASE_URL ?>/patient/queue.php" class="btn btn-primary fw-bold px-3">Live Tracker</a>
</div>
<?php endif; ?>

<!-- Top Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon primary">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= $todayAppointment ? '1' : '0' ?></div>
                <div class="mq-stat-label">Today's Appointments</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon warning">
                <i class="fa-solid fa-list-ol"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= $activeQueue ? ('#' . $activeQueue['queue_position']) : '—' ?></div>
                <div class="mq-stat-label">Queue Position</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon secondary">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= $activeQueue ? ($activeQueue['estimated_waiting_time'] . 'm') : '—' ?></div>
                <div class="mq-stat-label">Estimated Wait</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon success">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= $upcomingCount ?></div>
                <div class="mq-stat-label">Upcoming Visits</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Main Queue Card -->
    <div class="col-lg-5">
        <?php if ($activeQueue): ?>
            <!-- Prominent Queue Tracker Card -->
            <div class="queue-hero-card h-100 d-flex flex-column justify-content-between" id="patientLiveQueue" data-queue-id="<?= $activeQueue['id'] ?>" data-status="<?= $activeQueue['status'] ?>" data-base-url="<?= BASE_URL ?>">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-white text-primary fw-bold px-3 py-1">YOUR QUEUE STATUS</span>
                        <span class="d-flex align-items-center gap-2 small">
                            <span class="queue-pulse-dot"></span> Auto-Syncing
                        </span>
                    </div>
                    <div class="text-center my-3">
                        <div class="small text-white-50 text-uppercase fw-semibold mb-1">Queue Number</div>
                        <div class="queue-hero-number"><?= htmlspecialchars($activeQueue['queue_number']) ?></div>
                        <div class="mt-2" id="queueStatusBadgeDisplay">
                            <?= get_status_badge($activeQueue['status']) ?>
                        </div>
                    </div>

                    <div class="row g-2 text-center my-3">
                        <div class="col-6">
                            <div class="p-2 rounded-3 bg-white bg-opacity-10">
                                <span class="small text-white-50 d-block">Current Position</span>
                                <strong class="fs-4 text-white" id="queuePositionDisplay">#<?= $activeQueue['queue_position'] ?></strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded-3 bg-white bg-opacity-10">
                                <span class="small text-white-50 d-block">Patients Ahead</span>
                                <strong class="fs-4 text-white" id="patientsAheadDisplay"><?= $activeQueue['patients_ahead'] ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-3 bg-white bg-opacity-10 small text-white-50 mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Doctor:</span>
                            <strong class="text-white"><?= htmlspecialchars($activeQueue['doctor_name']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Department:</span>
                            <strong class="text-white"><?= htmlspecialchars($activeQueue['department_name']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Estimated Wait:</span>
                            <strong class="text-white" id="estimatedWaitDisplay"><?= $activeQueue['estimated_waiting_time'] ?> Minutes</strong>
                        </div>
                    </div>
                </div>

                <a href="<?= BASE_URL ?>/patient/queue.php" class="btn btn-light w-100 fw-bold py-2 text-primary shadow-sm">
                    <i class="fa-solid fa-expand me-1"></i> Full Screen Queue View
                </a>
            </div>
        <?php elseif ($todayAppointment): ?>
            <!-- Has appointment today but not joined virtual queue yet -->
            <div class="mq-card h-100 d-flex flex-column justify-content-between border-primary">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">Today's Appointment</h5>
                        <?= get_status_badge($todayAppointment['status']) ?>
                    </div>
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="user-avatar" style="width: 44px; height: 44px;">
                                <?= strtoupper(substr($todayAppointment['doctor_name'], 4, 1)) ?>
                            </div>
                            <div>
                                <strong class="text-dark d-block"><?= htmlspecialchars($todayAppointment['doctor_name']) ?></strong>
                                <span class="text-muted small"><?= htmlspecialchars($todayAppointment['department_name']) ?></span>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between small text-muted">
                            <span>Time Slot:</span>
                            <strong class="text-dark"><?= format_time($todayAppointment['appointment_time']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-1">
                            <span>Room:</span>
                            <strong class="text-dark"><?= htmlspecialchars($todayAppointment['room_number'] ?: 'Room 101') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-1">
                            <span>Ticket ID:</span>
                            <strong class="text-primary"><?= htmlspecialchars($todayAppointment['appointment_number']) ?></strong>
                        </div>
                    </div>
                    <p class="text-muted small">
                        Ready to visit the hospital? Join the virtual queue now to secure your turn and monitor waiting time live.
                    </p>
                </div>
                <form action="<?= BASE_URL ?>/patient/queue.php" method="POST">
                    <input type="hidden" name="action" value="join">
                    <input type="hidden" name="appointment_id" value="<?= $todayAppointment['id'] ?>">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                        <i class="fa-solid fa-list-ol me-1"></i> Join Today's Virtual Queue
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- No appointment today -->
            <div class="mq-card h-100 d-flex flex-column justify-content-center text-center p-4">
                <div class="mq-stat-icon primary mx-auto mb-3" style="width: 65px; height: 65px; font-size: 1.8rem;">
                    <i class="fa-regular fa-calendar-plus"></i>
                </div>
                <h5 class="fw-bold text-dark">No Appointments Scheduled Today</h5>
                <p class="text-muted small mb-4">You have no clinical visits scheduled for today. Book an appointment with one of our specialists.</p>
                <a href="<?= BASE_URL ?>/patient/book_appointment.php" class="btn btn-primary fw-semibold px-4 mx-auto">
                    <i class="fa-solid fa-plus me-1"></i> Book New Appointment
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Appointments Table -->
    <div class="col-lg-7">
        <div class="mq-card h-100 d-flex flex-column">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">My Recent Appointments</h5>
                <a href="<?= BASE_URL ?>/patient/appointments.php" class="small fw-semibold text-primary">View All</a>
            </div>

            <div class="mq-table-responsive flex-grow-1">
                <table class="table mq-table">
                    <thead>
                        <tr>
                            <th>Doctor / Dept</th>
                            <th>Date & Time</th>
                            <th>Appointment ID</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAppointments)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">
                                    <i class="fa-regular fa-calendar-xmark fa-2x mb-2 d-block text-muted"></i>
                                    No appointment records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentAppointments as $app): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($app['doctor_name']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($app['department_name']) ?></div>
                                    </td>
                                    <td>
                                        <div><?= format_date($app['appointment_date']) ?></div>
                                        <div class="text-muted small"><?= format_time($app['appointment_time']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($app['appointment_number']) ?></span>
                                    </td>
                                    <td>
                                        <?= get_status_badge($app['status']) ?>
                                    </td>
                                    <td>
                                        <?php if ($app['appointment_date'] === date('Y-m-d') && in_array($app['status'], ['confirmed', 'in_queue'])): ?>
                                            <a href="<?= BASE_URL ?>/patient/queue.php" class="btn btn-sm btn-outline-primary" title="View Queue">
                                                <i class="fa-solid fa-list-ol"></i> Queue
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL ?>/patient/appointments.php" class="btn btn-sm btn-light border text-muted" title="Details">
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
