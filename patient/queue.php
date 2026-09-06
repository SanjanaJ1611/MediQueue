<?php
/**
 * MediQueue - Patient: Real-time Virtual Queue Tracker & Queue History
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('patient');

$page_title = "Virtual Queue Tracker";
$conn = get_db_connection();
$patientId = $_SESSION['role_specific_id'] ?? 0;
$userId = $_SESSION['user_id'];

// Handle Manual Join Virtual Queue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join') {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);

    // Verify appointment belongs to patient and is for today
    $aStmt = $conn->prepare("
        SELECT a.*, d.department_id, dept.department_name 
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN departments dept ON d.department_id = dept.id
        WHERE a.id = ? AND a.patient_id = ? AND a.appointment_date = CURDATE()
    ");
    $aStmt->execute([$appointmentId, $patientId]);
    $appointment = $aStmt->fetch();

    if ($appointment) {
        // Check if queue entry already exists
        $checkQ = $conn->prepare("SELECT id FROM queues WHERE appointment_id = ?");
        $checkQ->execute([$appointmentId]);
        if (!$checkQ->fetch()) {
            $conn->beginTransaction();

            $qSeqStmt = $conn->prepare("SELECT COUNT(*) FROM queues WHERE doctor_id = ? AND DATE(joined_at) = CURDATE()");
            $qSeqStmt->execute([$appointment['doctor_id']]);
            $seq = (int)$qSeqStmt->fetchColumn() + 101;
            $queueNum = generate_queue_number($appointment['department_name'], $seq);

            $pos = calculate_queue_position($conn, $appointment['doctor_id'], PHP_INT_MAX);
            $waitTime = max(0, ($pos - 1) * 15);

            $ins = $conn->prepare("
                INSERT INTO queues (appointment_id, patient_id, doctor_id, queue_number, queue_position, estimated_waiting_time, status, joined_at)
                VALUES (?, ?, ?, ?, ?, ?, 'waiting', NOW())
            ");
            $ins->execute([$appointmentId, $patientId, $appointment['doctor_id'], $queueNum, $pos, $waitTime]);

            $updApp = $conn->prepare("UPDATE appointments SET status = 'in_queue' WHERE id = ?");
            $updApp->execute([$appointmentId]);

            $conn->commit();
            set_flash('success', "You have joined the virtual queue! Ticket #{$queueNum}");
        }
    }
    header('Location: ' . BASE_URL . '/patient/queue.php');
    exit;
}

// Fetch Active Queue for Today
$qStmt = $conn->prepare("
    SELECT q.*, 
           u.name as doctor_name, 
           d.room_number,
           d.specialization,
           dept.department_name,
           a.appointment_number,
           a.appointment_time
    FROM queues q
    JOIN doctors d ON q.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    JOIN appointments a ON q.appointment_id = a.id
    WHERE q.patient_id = ? 
      AND DATE(q.joined_at) = CURDATE()
      AND q.status NOT IN ('cancelled')
    ORDER BY q.id DESC
    LIMIT 1
");
$qStmt->execute([$patientId]);
$activeQueue = $qStmt->fetch();

if ($activeQueue) {
    $activeQueue['queue_position'] = calculate_queue_position($conn, $activeQueue['doctor_id'], $activeQueue['id']);
    $activeQueue['patients_ahead'] = max(0, $activeQueue['queue_position'] - 1);
    $activeQueue['estimated_waiting_time'] = $activeQueue['status'] === 'waiting' ? ($activeQueue['patients_ahead'] * 15) : 0;
}

// Fetch Today's eligible appointment if NOT in queue
$eligibleAppointment = null;
if (!$activeQueue) {
    $elStmt = $conn->prepare("
        SELECT a.*, u.name as doctor_name, dept.department_name, d.room_number
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        JOIN departments dept ON d.department_id = dept.id
        WHERE a.patient_id = ? 
          AND a.appointment_date = CURDATE()
          AND a.status IN ('confirmed', 'pending')
        LIMIT 1
    ");
    $elStmt->execute([$patientId]);
    $eligibleAppointment = $elStmt->fetch();
}

// Fetch Queue History
$histStmt = $conn->prepare("
    SELECT q.*, u.name as doctor_name, dept.department_name, a.appointment_number
    FROM queues q
    JOIN doctors d ON q.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    JOIN appointments a ON q.appointment_id = a.id
    WHERE q.patient_id = ?
    ORDER BY q.id DESC
    LIMIT 10
");
$histStmt->execute([$patientId]);
$queueHistory = $histStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Live Virtual Queue Tracker</h3>
        <p class="text-muted mb-0">Real-time turn progression, estimated wait times, and consultation alerts.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-muted border p-2">
            <span class="queue-pulse-dot me-1"></span> Live Polling Active (4s)
        </span>
    </div>
</div>

<!-- Alert Banner: Dynamic AJAX Target -->
<div id="queueStatusBanner" class="d-none"></div>

<?php if ($activeQueue): ?>
    <!-- Active Queue Console -->
    <div class="row justify-content-center mb-5" id="patientLiveQueue" data-queue-id="<?= $activeQueue['id'] ?>" data-status="<?= $activeQueue['status'] ?>" data-base-url="<?= BASE_URL ?>">
        <div class="col-lg-8">
            <div class="queue-hero-card text-center p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-white text-primary fw-bold px-3 py-1">VIRTUAL QUEUE TICKET</span>
                    <span class="small d-flex align-items-center gap-2">
                        <span class="queue-pulse-dot"></span> Live Connected
                    </span>
                </div>

                <div class="small text-white-50 text-uppercase fw-semibold mb-1">Your Queue Number</div>
                <div class="queue-hero-number my-2"><?= htmlspecialchars($activeQueue['queue_number']) ?></div>
                
                <div class="my-3" id="queueStatusBadgeDisplay">
                    <?= get_status_badge($activeQueue['status']) ?>
                </div>

                <!-- 3 Metric Columns -->
                <div class="row g-3 my-4">
                    <div class="col-4">
                        <div class="p-3 rounded-3 bg-white bg-opacity-10">
                            <span class="small text-white-50 d-block">Current Position</span>
                            <strong class="fs-2 text-white" id="queuePositionDisplay">#<?= $activeQueue['queue_position'] ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded-3 bg-white bg-opacity-10">
                            <span class="small text-white-50 d-block">Patients Ahead</span>
                            <strong class="fs-2 text-white" id="patientsAheadDisplay"><?= $activeQueue['patients_ahead'] ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded-3 bg-white bg-opacity-10">
                            <span class="small text-white-50 d-block">Estimated Wait</span>
                            <strong class="fs-2 text-white" id="estimatedWaitDisplay"><?= $activeQueue['estimated_waiting_time'] ?> Mins</strong>
                        </div>
                    </div>
                </div>

                <!-- Clinical Metadata -->
                <div class="p-3 rounded-3 bg-white bg-opacity-10 text-start text-white-50 small mb-4">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <span>Doctor:</span>
                            <strong class="text-white d-block"><?= htmlspecialchars($activeQueue['doctor_name']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span>Department:</span>
                            <strong class="text-white d-block"><?= htmlspecialchars($activeQueue['department_name']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span>Assigned Room:</span>
                            <strong class="text-white d-block"><?= htmlspecialchars($activeQueue['room_number'] ?: 'Suite 101') ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span>Scheduled Slot:</span>
                            <strong class="text-white d-block"><?= format_time($activeQueue['appointment_time']) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="text-white-50 small">
                    <i class="fa-solid fa-volume-high me-1"></i> Audio chime will automatically alert you when the doctor calls your ticket.
                </div>
            </div>
        </div>
    </div>
<?php elseif ($eligibleAppointment): ?>
    <!-- Eligible to join queue today -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-7">
            <div class="mq-card text-center p-4 p-md-5 border-primary">
                <div class="step-circle bg-primary text-white mx-auto mb-3" style="width: 65px; height: 65px; font-size: 1.6rem;">
                    <i class="fa-solid fa-ticket"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">You Have an Appointment Today!</h4>
                <p class="text-muted small mb-4">
                    Your appointment with <strong><?= htmlspecialchars($eligibleAppointment['doctor_name']) ?></strong> (<?= htmlspecialchars($eligibleAppointment['department_name']) ?>) is scheduled for today at <strong><?= format_time($eligibleAppointment['appointment_time']) ?></strong>.
                </p>
                <form action="<?= BASE_URL ?>/patient/queue.php" method="POST">
                    <input type="hidden" name="action" value="join">
                    <input type="hidden" name="appointment_id" value="<?= $eligibleAppointment['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-lg fw-semibold px-4 py-3 shadow-sm">
                        <i class="fa-solid fa-list-ol me-2"></i> Join Virtual Queue Now
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- No Active Queue -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-7">
            <div class="mq-card text-center p-4 p-md-5">
                <div class="mq-stat-icon primary mx-auto mb-3" style="width: 65px; height: 65px; font-size: 1.8rem;">
                    <i class="fa-solid fa-list-ol"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">No Active Virtual Queue</h4>
                <p class="text-muted small mb-4">
                    Virtual queues are generated for confirmed appointments on the day of your visit. You currently have no active queues for today.
                </p>
                <a href="<?= BASE_URL ?>/patient/book_appointment.php" class="btn btn-primary fw-semibold px-4">
                    <i class="fa-solid fa-plus me-1"></i> Book Today's Visit
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Queue History Table -->
<div class="mq-card p-0 bg-white">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-clock-rotate-left text-muted me-2"></i> My Queue History</h5>
    </div>
    <div class="mq-table-responsive">
        <table class="table mq-table">
            <thead>
                <tr>
                    <th>Queue Ticket</th>
                    <th>Appointment ID</th>
                    <th>Doctor & Department</th>
                    <th>Joined At</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queueHistory)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted small">No virtual queue history found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queueHistory as $hist): ?>
                        <tr>
                            <td>
                                <strong class="text-primary"><?= htmlspecialchars($hist['queue_number']) ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-muted border"><?= htmlspecialchars($hist['appointment_number']) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($hist['doctor_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($hist['department_name']) ?></div>
                            </td>
                            <td>
                                <div><?= format_date($hist['joined_at']) ?></div>
                                <div class="text-muted small"><?= format_time($hist['joined_at']) ?></div>
                            </td>
                            <td>
                                <?= get_status_badge($hist['status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
