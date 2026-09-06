<?php
/**
 * MediQueue - Patient: Book Hospital Appointment
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('patient');

$page_title = "Book Appointment";
$conn = get_db_connection();
$patientId = $_SESSION['role_specific_id'] ?? 0;
$userId = $_SESSION['user_id'];

$error = '';
$successBooking = null;

// Pre-selected parameters from GET
$preDoctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$preDeptId   = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Fetch departments
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll();

// Fetch all active doctors
$doctors = $conn->query("
    SELECT d.*, u.name as doctor_name, dept.department_name,
           COALESCE(da.status, 'available') as avail_status
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN doctor_availability da ON d.id = da.doctor_id
    WHERE d.status = 'active'
    ORDER BY u.name ASC
")->fetchAll();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session security token expired. Please try submitting again.';
    } else {
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $appointmentDate = trim($_POST['appointment_date'] ?? '');
        $appointmentTime = trim($_POST['appointment_time'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $joinQueue = !empty($_POST['join_queue']);

        // Validation
        if (!$doctorId || empty($appointmentDate) || empty($appointmentTime)) {
            $error = 'Please select a doctor, appointment date, and an available time slot.';
        } elseif (strtotime($appointmentDate) < strtotime(date('Y-m-d'))) {
            $error = 'You cannot book appointments in the past. Please select today or a future date.';
        } else {
            try {
                $conn->beginTransaction();

                // 1. Check double-booking collision
                global $db_driver;
                $lockClause = (isset($db_driver) && $db_driver === 'mysql') ? 'FOR UPDATE' : '';
                $collisionStmt = $conn->prepare("
                    SELECT id FROM appointments 
                    WHERE doctor_id = ? 
                      AND appointment_date = ? 
                      AND appointment_time = ? 
                      AND status NOT IN ('cancelled', 'no_show')
                    {$lockClause}
                ");
                $collisionStmt->execute([$doctorId, $appointmentDate, $appointmentTime]);
                if ($collisionStmt->fetch()) {
                    $conn->rollBack();
                    $error = 'Sorry, this time slot has just been booked by another patient. Please choose another available slot.';
                } else {
                    // 2. Generate unique appointment number
                    $appNumber = generate_appointment_number();
                    
                    // Initial status
                    $initStatus = ($joinQueue && $appointmentDate === date('Y-m-d')) ? 'in_queue' : 'confirmed';

                    // 3. Insert Appointment
                    $insApp = $conn->prepare("
                        INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, appointment_number, status, reason, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $insApp->execute([$patientId, $doctorId, $appointmentDate, $appointmentTime, $appNumber, $initStatus, $reason]);
                    $appointmentId = $conn->lastInsertId();

                    // 4. Fetch Doctor & Dept details for queue ticket and notification
                    $docInfoStmt = $conn->prepare("
                        SELECT d.room_number, u.name as doctor_name, dept.department_name, u.id as doctor_user_id
                        FROM doctors d
                        JOIN users u ON d.user_id = u.id
                        JOIN departments dept ON d.department_id = dept.id
                        WHERE d.id = ?
                    ");
                    $docInfoStmt->execute([$doctorId]);
                    $docInfo = $docInfoStmt->fetch();

                    $queueTicket = null;
                    // 5. Join Virtual Queue if selected and appointment is for today
                    if ($initStatus === 'in_queue') {
                        $qSeqStmt = $conn->prepare("SELECT COUNT(*) FROM queues WHERE doctor_id = ? AND DATE(joined_at) = CURDATE()");
                        $qSeqStmt->execute([$doctorId]);
                        $seq = (int)$qSeqStmt->fetchColumn() + 101;
                        $queueNum = generate_queue_number($docInfo['department_name'], $seq);

                        $pos = calculate_queue_position($conn, $doctorId, PHP_INT_MAX);
                        $waitTime = max(0, ($pos - 1) * 15);

                        $insQueue = $conn->prepare("
                            INSERT INTO queues (appointment_id, patient_id, doctor_id, queue_number, queue_position, estimated_waiting_time, status, joined_at)
                            VALUES (?, ?, ?, ?, ?, ?, 'waiting', NOW())
                        ");
                        $insQueue->execute([$appointmentId, $patientId, $doctorId, $queueNum, $pos, $waitTime]);
                        $queueId = $conn->lastInsertId();

                        $queueTicket = [
                            'id'                     => $queueId,
                            'queue_number'           => $queueNum,
                            'queue_position'         => $pos,
                            'estimated_waiting_time' => $waitTime
                        ];
                    }

                    // 6. Create notifications
                    create_notification(
                        $conn,
                        $userId,
                        'Appointment Confirmed',
                        "Your appointment ({$appNumber}) with Dr. {$docInfo['doctor_name']} is confirmed for " . format_date($appointmentDate) . " at " . format_time($appointmentTime) . ".",
                        'success'
                    );

                    create_notification(
                        $conn,
                        $docInfo['doctor_user_id'],
                        'New Appointment Booked',
                        "Patient {$_SESSION['user_name']} has booked an appointment for " . format_date($appointmentDate) . " at " . format_time($appointmentTime) . ".",
                        'info'
                    );

                    $conn->commit();

                    $successBooking = [
                        'appointment_id'     => $appointmentId,
                        'appointment_number' => $appNumber,
                        'doctor_name'        => $docInfo['doctor_name'],
                        'department_name'    => $docInfo['department_name'],
                        'room_number'        => $docInfo['room_number'],
                        'date'               => $appointmentDate,
                        'time'               => $appointmentTime,
                        'queue'              => $queueTicket
                    ];
                }
            } catch (Exception $e) {
                $conn->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<input type="hidden" id="appBaseUrl" value="<?= BASE_URL ?>">

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Book Hospital Appointment</h3>
        <p class="text-muted mb-0">Select your specialist doctor, preferred date, and real-time available time slot.</p>
    </div>
    <a href="<?= BASE_URL ?>/patient/appointments.php" class="btn btn-outline-secondary fw-semibold">
        <i class="fa-solid fa-calendar-check me-1"></i> My Appointments
    </a>
</div>

<?php if ($successBooking): ?>
    <!-- Booking Confirmation Card -->
    <div class="row justify-content-center my-4">
        <div class="col-lg-8">
            <div class="mq-card shadow-sm p-4 p-md-5 border-success text-center">
                <div class="step-circle bg-success text-white mb-3" style="width: 70px; height: 70px; font-size: 2rem;">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h3 class="fw-bold text-dark mb-1">Appointment Confirmed!</h3>
                <p class="text-muted small mb-4">Your visit has been successfully registered with MediQueue.</p>

                <div class="p-4 bg-light rounded-3 border text-start mb-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Appointment Number</span>
                            <span class="fs-5 fw-bold text-primary"><?= htmlspecialchars($successBooking['appointment_number']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Specialist Doctor</span>
                            <span class="fs-5 fw-bold text-dark"><?= htmlspecialchars($successBooking['doctor_name']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Department & Room</span>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($successBooking['department_name']) ?> (<?= htmlspecialchars($successBooking['room_number'] ?: 'Suite 101') ?>)</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Date & Time</span>
                            <span class="fw-semibold text-dark"><?= format_date($successBooking['date']) ?> at <?= format_time($successBooking['time']) ?></span>
                        </div>
                    </div>

                    <?php if ($successBooking['queue']): ?>
                        <hr class="my-3">
                        <div class="p-3 bg-primary bg-opacity-10 border border-primary-subtle rounded-3 text-center">
                            <span class="badge bg-primary px-3 py-1 mb-2">VIRTUAL QUEUE TICKET</span>
                            <div class="fs-3 fw-bold text-primary"><?= htmlspecialchars($successBooking['queue']['queue_number']) ?></div>
                            <div class="small text-muted mt-1">
                                Position: <strong>#<?= $successBooking['queue']['queue_position'] ?></strong> &bull; Estimated Wait: <strong><?= $successBooking['queue']['estimated_waiting_time'] ?> Minutes</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <?php if ($successBooking['queue']): ?>
                        <a href="<?= BASE_URL ?>/patient/queue.php" class="btn btn-primary fw-semibold px-4 py-2">
                            <i class="fa-solid fa-list-ol me-1"></i> Track Live Queue
                        </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/patient/appointments.php" class="btn btn-outline-secondary fw-semibold px-4 py-2">
                        View All Appointments
                    </a>
                    <a href="<?= BASE_URL ?>/patient/book_appointment.php" class="btn btn-light border fw-semibold px-4 py-2">
                        Book Another
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- Booking Form -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="mq-card p-4 p-md-5 bg-white">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show small d-flex align-items-center mb-4" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>/patient/book_appointment.php" method="POST" id="bookingForm">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <!-- Step 1: Select Doctor -->
                    <div class="mb-4">
                        <label for="bookingDoctor" class="form-label fw-semibold text-dark">1. Select Doctor & Specialty *</label>
                        <select class="form-select py-2" id="bookingDoctor" name="doctor_id" required>
                            <option value="">-- Choose a doctor --</option>
                            <?php foreach ($doctors as $doc): ?>
                                <?php $selected = ($preDoctorId === (int)$doc['id']) ? 'selected' : ''; ?>
                                <option value="<?= $doc['id'] ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($doc['doctor_name']) ?> — <?= htmlspecialchars($doc['department_name']) ?> (Fee: $<?= number_format($doc['consultation_fee'], 0) ?>) [<?= ucfirst($doc['avail_status']) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Choose from our verified clinical specialists across departments.</div>
                    </div>

                    <!-- Step 2: Select Date -->
                    <div class="mb-4">
                        <label for="bookingDate" class="form-label fw-semibold text-dark">2. Appointment Date *</label>
                        <input type="date" class="form-control py-2" id="bookingDate" name="appointment_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                        <div class="form-text">Appointments can be scheduled for today or upcoming dates.</div>
                    </div>

                    <!-- Step 3: Available Time Slots (Dynamically Fetched via AJAX) -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-dark d-flex justify-content-between">
                            <span>3. Available Time Slot *</span>
                            <span class="small text-muted fw-normal"><i class="fa-solid fa-bolt text-warning me-1"></i> Real-time slot collision check</span>
                        </label>
                        <div id="availableSlotsContainer" class="p-3 bg-light rounded-3 border">
                            <p class="text-muted small my-2">
                                <i class="fa-solid fa-circle-info me-1"></i> Please choose a doctor and date above to load live 30-minute consultation slots.
                            </p>
                        </div>
                    </div>

                    <!-- Step 4: Reason for Consultation -->
                    <div class="mb-4">
                        <label for="bookingReason" class="form-label fw-semibold text-dark">4. Reason for Visit (Symptoms / Notes)</label>
                        <textarea class="form-control" id="bookingReason" name="reason" rows="3" placeholder="Briefly describe your symptoms, medical concerns, or whether this is a routine follow-up..."></textarea>
                    </div>

                    <!-- Step 5: Queue Option -->
                    <div class="form-check p-3 bg-primary bg-opacity-10 border border-primary-subtle rounded-3 mb-4 ms-0">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="join_queue" value="1" id="joinQueueCheck" checked>
                        <label class="form-check-label fw-semibold text-dark small" for="joinQueueCheck">
                            Automatically join today's Virtual Queue upon booking (Recommended for same-day visits)
                        </label>
                        <div class="text-muted small ps-4 mt-1">
                            You will instantly receive your digital queue number (e.g. CAR-105) and can track your turn live without sitting in the waiting hall.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold py-2 shadow-sm">
                        <i class="fa-regular fa-calendar-check me-2"></i> Confirm & Book Appointment
                    </button>
                </form>
            </div>
        </div>

        <!-- Helpful Side Card -->
        <div class="col-lg-4">
            <div class="mq-card bg-light border p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-shield-halved text-primary me-2"></i> Booking Guarantee</h5>
                <ul class="list-unstyled small text-muted lh-lg mb-0">
                    <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> <strong>Zero Double-Booking:</strong> Selected slots are locked immediately.</li>
                    <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> <strong>Live Virtual Queue:</strong> Minimize physical waiting in the hospital lobby.</li>
                    <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i> <strong>SMS / On-screen Alerts:</strong> Receive an instant chime when your doctor calls your number.</li>
                    <li><i class="fa-solid fa-check text-success me-2"></i> <strong>Free Rescheduling:</strong> Cancel or re-book anytime prior to the consultation.</li>
                </ul>
            </div>

            <div class="mq-card p-4">
                <h6 class="fw-bold text-dark mb-2">Need Clinical Assistance?</h6>
                <p class="text-muted small mb-3">If you are experiencing severe symptoms or emergencies, do not wait for a queue ticket.</p>
                <div class="p-2 rounded bg-danger bg-opacity-10 border border-danger-subtle text-danger small fw-semibold text-center">
                    <i class="fa-solid fa-phone me-1"></i> Hospital Emergency: (555) 019-9911
                </div>
            </div>
        </div>
    </div>

    <!-- Trigger initial slot loading if doctor is pre-selected -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const docSelect = document.getElementById('bookingDoctor');
            if (docSelect && docSelect.value) {
                docSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
