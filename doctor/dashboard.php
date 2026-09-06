<?php
/**
 * MediQueue - Doctor Console Dashboard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('doctor');

$page_title = "Doctor Dashboard";
$conn = get_db_connection();
$doctorId = $_SESSION['role_specific_id'] ?? 0;
$userId = $_SESSION['user_id'];

// Fetch today's stats for this doctor
$todayStats = [
    'total_today' => 0,
    'waiting'     => 0,
    'in_consult'  => 0,
    'completed'   => 0
];

$statStmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN a.appointment_date = CURDATE() THEN 1 END) as total_today,
        COUNT(CASE WHEN q.status = 'waiting' AND DATE(q.joined_at) = CURDATE() THEN 1 END) as waiting,
        COUNT(CASE WHEN q.status IN ('called', 'in_consultation') AND DATE(q.joined_at) = CURDATE() THEN 1 END) as in_consult,
        COUNT(CASE WHEN q.status = 'completed' AND DATE(q.joined_at) = CURDATE() THEN 1 END) as completed
    FROM appointments a
    LEFT JOIN queues q ON a.id = q.appointment_id
    WHERE a.doctor_id = ?
");
$statStmt->execute([$doctorId]);
$todayStats = $statStmt->fetch() ?: $todayStats;

// Fetch Currently Active Patient (Called or In Consultation)
$activeStmt = $conn->prepare("
    SELECT q.*, p.gender, p.blood_group, p.date_of_birth, u.name as patient_name, u.phone as patient_phone,
           a.appointment_number, a.appointment_time, a.reason
    FROM queues q
    JOIN patients p ON q.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN appointments a ON q.appointment_id = a.id
    WHERE q.doctor_id = ? 
      AND q.status IN ('called', 'in_consultation')
      AND DATE(q.joined_at) = CURDATE()
    ORDER BY q.id ASC
    LIMIT 1
");
$activeStmt->execute([$doctorId]);
$currentPatient = $activeStmt->fetch();

// Fetch Next Patient in line
$nextStmt = $conn->prepare("
    SELECT q.*, u.name as patient_name, a.appointment_number, a.appointment_time
    FROM queues q
    JOIN patients p ON q.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN appointments a ON q.appointment_id = a.id
    WHERE q.doctor_id = ? 
      AND q.status = 'waiting'
      AND DATE(q.joined_at) = CURDATE()
    ORDER BY q.id ASC
    LIMIT 1
");
$nextStmt->execute([$doctorId]);
$nextPatient = $nextStmt->fetch();

// Fetch Today's Active Queue List
$queueListStmt = $conn->prepare("
    SELECT q.*, u.name as patient_name, u.phone as patient_phone,
           p.gender, p.blood_group,
           a.appointment_number, a.appointment_time, a.reason
    FROM queues q
    JOIN patients p ON q.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN appointments a ON q.appointment_id = a.id
    WHERE q.doctor_id = ? 
      AND DATE(q.joined_at) = CURDATE()
      AND q.status NOT IN ('cancelled')
    ORDER BY 
        CASE 
            WHEN q.status = 'called' THEN 1
            WHEN q.status = 'in_consultation' THEN 2
            WHEN q.status = 'waiting' THEN 3
            WHEN q.status = 'completed' THEN 4
            ELSE 5 
        END,
        q.id ASC
");
$queueListStmt->execute([$doctorId]);
$todaysQueue = $queueListStmt->fetchAll();

// Fetch Doctor availability
$availStmt = $conn->prepare("SELECT * FROM doctor_availability WHERE doctor_id = ? LIMIT 1");
$availStmt->execute([$doctorId]);
$avail = $availStmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Doctor Console — <?= htmlspecialchars($_SESSION['user_name']) ?></h3>
        <p class="text-muted mb-0">
            Room: <strong><?= htmlspecialchars($_SESSION['room_number'] ?? 'Suite 101') ?></strong> &bull; 
            Department: <strong><?= htmlspecialchars($_SESSION['department_name'] ?? 'General') ?></strong> &bull;
            Status: <?= get_status_badge($avail['status'] ?? 'available') ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <!-- Call Next Patient Shortcut Button -->
        <button type="button" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm" id="btnCallNextTop">
            <i class="fa-solid fa-bullhorn me-1"></i> CALL NEXT PATIENT
        </button>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon primary">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= (int)$todayStats['total_today'] ?></div>
                <div class="mq-stat-label">Today's Appointments</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon warning">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div>
                <div class="mq-stat-number" id="waitingCountDisplay"><?= (int)$todayStats['waiting'] ?></div>
                <div class="mq-stat-label">Waiting in Hall</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon secondary">
                <i class="fa-solid fa-stethoscope"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= $currentPatient ? '1' : '0' ?></div>
                <div class="mq-stat-label">In Consultation / Called</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon success">
                <i class="fa-solid fa-check-double"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= (int)$todayStats['completed'] ?></div>
                <div class="mq-stat-label">Completed Consults</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Active Patient Spotlight Box -->
    <div class="col-lg-6">
        <div class="mq-card h-100 p-4 border-2 <?= $currentPatient ? 'border-primary' : '' ?>">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-primary text-white fw-bold px-3 py-1">CURRENT CONSULTATION</span>
                <?php if ($currentPatient): ?>
                    <?= get_status_badge($currentPatient['status']) ?>
                <?php endif; ?>
            </div>

            <?php if ($currentPatient): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="user-avatar" style="width: 56px; height: 56px; font-size: 1.4rem;">
                        <?= strtoupper(substr($currentPatient['patient_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-0"><?= htmlspecialchars($currentPatient['patient_name']) ?></h4>
                        <div class="text-muted small">
                            Ticket: <strong class="text-primary">#<?= htmlspecialchars($currentPatient['queue_number']) ?></strong> &bull; 
                            <?= htmlspecialchars($currentPatient['gender'] ?? 'N/A') ?> &bull; 
                            Blood: <strong class="text-danger"><?= htmlspecialchars($currentPatient['blood_group'] ?? 'N/A') ?></strong>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border mb-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Appointment ID:</span>
                        <strong><?= htmlspecialchars($currentPatient['appointment_number']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Scheduled Time:</span>
                        <strong><?= format_time($currentPatient['appointment_time']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Called At:</span>
                        <strong><?= $currentPatient['called_at'] ? date('h:i:s A', strtotime($currentPatient['called_at'])) : 'Just now' ?></strong>
                    </div>
                    <?php if (!empty($currentPatient['reason'])): ?>
                    <hr class="my-2">
                    <div>
                        <span class="text-muted d-block mb-1">Patient Chief Complaint:</span>
                        <div class="text-dark bg-white p-2 rounded border"><?= nl2br(htmlspecialchars($currentPatient['reason'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Consultation State Control Buttons -->
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($currentPatient['status'] === 'called'): ?>
                        <button type="button" class="btn btn-info text-dark fw-semibold flex-grow-1 btn-update-q" data-queue-id="<?= $currentPatient['id'] ?>" data-status="in_consultation">
                            <i class="fa-solid fa-stethoscope me-1"></i> Start Consultation
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-success fw-semibold flex-grow-1 btn-update-q" data-queue-id="<?= $currentPatient['id'] ?>" data-status="completed">
                        <i class="fa-solid fa-check me-1"></i> Complete Consultation
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-update-q" data-queue-id="<?= $currentPatient['id'] ?>" data-status="no_show" title="Patient absent">
                        <i class="fa-solid fa-user-slash me-1"></i> No Show
                    </button>
                </div>

            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mq-stat-icon primary mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <i class="fa-solid fa-user-clock"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No Patient Currently in Room</h5>
                    <p class="text-muted small mb-4">Click "Call Next Patient" to advance the virtual queue and summon the next waiting patient.</p>
                    <button type="button" class="btn btn-primary fw-semibold px-4" onclick="document.getElementById('btnCallNextTop').click();">
                        <i class="fa-solid fa-bullhorn me-1"></i> Call Next Patient
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Up Next Spotlight Box -->
    <div class="col-lg-6">
        <div class="mq-card h-100 p-4">
            <span class="badge bg-light text-muted border fw-bold px-3 py-1 mb-3">UP NEXT IN QUEUE</span>

            <?php if ($nextPatient): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="user-avatar" style="width: 50px; height: 50px; font-size: 1.25rem;">
                        <?= strtoupper(substr($nextPatient['patient_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($nextPatient['patient_name']) ?></h5>
                        <span class="text-muted small">Queue Ticket: <strong class="text-primary">#<?= htmlspecialchars($nextPatient['queue_number']) ?></strong> &bull; Slot: <?= format_time($nextPatient['appointment_time']) ?></span>
                    </div>
                </div>

                <div class="p-3 bg-warning bg-opacity-10 border border-warning-subtle rounded-3 text-warning-emphasis small mb-4">
                    <i class="fa-solid fa-hourglass-half me-1"></i> Patient is checked in and waiting in the waiting area.
                </div>

                <button type="button" class="btn btn-outline-primary w-100 fw-semibold py-2" onclick="document.getElementById('btnCallNextTop').click();">
                    <i class="fa-solid fa-bullhorn me-1"></i> Call #<?= htmlspecialchars($nextPatient['queue_number']) ?> Now
                </button>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fa-solid fa-clipboard-check fa-3x text-muted mb-2 d-block"></i>
                    <h6 class="fw-bold text-dark">Queue is Clear</h6>
                    <p class="text-muted small mb-0">No other patients are currently waiting in today's virtual queue.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Today's Queue Table -->
<div class="mq-card p-0 bg-white">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold text-dark mb-0">Today's Virtual Queue</h5>
            <span class="text-muted small">Live patient turn management and consultation status tracker.</span>
        </div>
        <a href="<?= BASE_URL ?>/doctor/queue.php" class="btn btn-sm btn-outline-primary fw-semibold">
            <i class="fa-solid fa-expand me-1"></i> Full Queue Screen
        </a>
    </div>

    <div class="mq-table-responsive">
        <table class="table mq-table">
            <thead>
                <tr>
                    <th>Queue No</th>
                    <th>Patient Name</th>
                    <th>Appointment</th>
                    <th>Time Slot</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($todaysQueue)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted small">
                            No appointments or virtual queues registered for today.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($todaysQueue as $q): ?>
                        <tr class="<?= $q['status'] === 'called' ? 'table-primary bg-opacity-10' : '' ?>">
                            <td>
                                <strong class="fs-6 text-primary">#<?= htmlspecialchars($q['queue_number']) ?></strong>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($q['patient_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($q['patient_phone'] ?: 'No phone') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($q['appointment_number']) ?></span>
                            </td>
                            <td><?= format_time($q['appointment_time']) ?></td>
                            <td>
                                <span id="queueBadge_<?= $q['id'] ?>"><?= get_status_badge($q['status']) ?></span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($q['status'] === 'waiting'): ?>
                                        <button type="button" class="btn btn-sm btn-primary btn-update-q" data-queue-id="<?= $q['id'] ?>" data-status="called" title="Call Patient">
                                            <i class="fa-solid fa-bullhorn"></i> Call
                                        </button>
                                    <?php elseif ($q['status'] === 'called'): ?>
                                        <button type="button" class="btn btn-sm btn-info text-dark btn-update-q" data-queue-id="<?= $q['id'] ?>" data-status="in_consultation" title="Start Consultation">
                                            <i class="fa-solid fa-stethoscope"></i> Start
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success btn-update-q" data-queue-id="<?= $q['id'] ?>" data-status="completed" title="Complete Consultation">
                                            <i class="fa-solid fa-check"></i> Complete
                                        </button>
                                    <?php elseif ($q['status'] === 'in_consultation'): ?>
                                        <button type="button" class="btn btn-sm btn-success btn-update-q" data-queue-id="<?= $q['id'] ?>" data-status="completed" title="Complete Consultation">
                                            <i class="fa-solid fa-check"></i> Complete
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!in_array($q['status'], ['completed', 'cancelled', 'no_show'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-update-q" data-queue-id="<?= $q['id'] ?>" data-status="no_show" title="Mark No Show">
                                            <i class="fa-solid fa-user-slash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const doctorId = <?= (int)$doctorId ?>;
    const baseUrl = '<?= BASE_URL ?>';

    // Top "Call Next Patient" AJAX Trigger
    const btnCallNext = document.getElementById('btnCallNextTop');
    if (btnCallNext) {
        btnCallNext.addEventListener('click', () => {
            btnCallNext.disabled = true;
            btnCallNext.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Calling...';

            const formData = new FormData();
            formData.append('doctor_id', doctorId);

            fetch(`${baseUrl}/ajax/call_next.php`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'No waiting patients found in queue.');
                    btnCallNext.disabled = false;
                    btnCallNext.innerHTML = '<i class="fa-solid fa-bullhorn me-1"></i> CALL NEXT PATIENT';
                }
            })
            .catch(err => {
                console.error(err);
                btnCallNext.disabled = false;
                btnCallNext.innerHTML = '<i class="fa-solid fa-bullhorn me-1"></i> CALL NEXT PATIENT';
            });
        });
    }

    // Individual Queue Row Action Buttons
    const updateButtons = document.querySelectorAll('.btn-update-q');
    updateButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const qId = btn.getAttribute('data-queue-id');
            const newStatus = btn.getAttribute('data-status');

            btn.disabled = true;
            const formData = new FormData();
            formData.append('queue_id', qId);
            formData.append('status', newStatus);

            fetch(`${baseUrl}/ajax/update_queue.php`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to update queue status.');
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                btn.disabled = false;
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
