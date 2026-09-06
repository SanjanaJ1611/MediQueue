<?php
/**
 * MediQueue - Doctor Availability & Consultation Hours Setup
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('doctor');

$page_title = "Doctor Availability";
$conn = get_db_connection();
$doctorId = $_SESSION['role_specific_id'] ?? 0;

$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please reload and try again.';
    } else {
        $status = trim($_POST['status'] ?? 'available');
        $startTime = trim($_POST['start_time'] ?? '09:00:00');
        $endTime = trim($_POST['end_time'] ?? '17:00:00');
        $slotDuration = (int)($_POST['slot_duration'] ?? 30);
        $dayOfWeek = trim($_POST['day_of_week'] ?? 'All Days');

        $validStatuses = ['available', 'busy', 'unavailable'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'available';
        }

        // Check if availability record exists
        $check = $conn->prepare("SELECT id FROM doctor_availability WHERE doctor_id = ? LIMIT 1");
        $check->execute([$doctorId]);
        $availId = $check->fetchColumn();

        if ($availId) {
            $upd = $conn->prepare("
                UPDATE doctor_availability 
                SET status = ?, start_time = ?, end_time = ?, slot_duration = ?, day_of_week = ? 
                WHERE id = ?
            ");
            $upd->execute([$status, $startTime, $endTime, $slotDuration, $dayOfWeek, $availId]);
        } else {
            $ins = $conn->prepare("
                INSERT INTO doctor_availability (doctor_id, status, start_time, end_time, slot_duration, day_of_week, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([$doctorId, $status, $startTime, $endTime, $slotDuration, $dayOfWeek]);
        }

        set_flash('success', 'Your consultation schedule and availability status have been updated.');
        header('Location: ' . BASE_URL . '/doctor/availability.php');
        exit;
    }
}

// Fetch current availability
$availStmt = $conn->prepare("SELECT * FROM doctor_availability WHERE doctor_id = ? LIMIT 1");
$availStmt->execute([$doctorId]);
$avail = $availStmt->fetch();

$currentStatus = $avail ? $avail['status'] : 'available';
$currentStart = $avail ? $avail['start_time'] : '09:00:00';
$currentEnd = $avail ? $avail['end_time'] : '17:00:00';
$currentDuration = $avail ? $avail['slot_duration'] : 30;
$currentDays = $avail ? $avail['day_of_week'] : 'All Days';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Doctor Availability & Clinic Hours</h3>
        <p class="text-muted mb-0">Define consultation hours and toggle real-time availability badges visible to patients.</p>
    </div>
    <div>
        <span class="text-muted small me-2">Current Badge:</span>
        <?= get_status_badge($currentStatus) ?>
    </div>
</div>

<div class="row g-4 justify-content-center">
    <!-- Quick Status Toggle Card -->
    <div class="col-lg-4">
        <div class="mq-card p-4 text-center mb-4">
            <h5 class="fw-bold text-dark mb-2">Real-Time Status Badge</h5>
            <p class="text-muted small mb-4">This badge displays dynamically to patients in the doctor directory and appointment booking screen.</p>
            
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-outline-success fw-bold py-2 btn-avail-toggle <?= $currentStatus === 'available' ? 'active bg-success text-white' : '' ?>" data-status="available">
                    <i class="fa-solid fa-circle-check me-2"></i> AVAILABLE (Green)
                </button>
                <button type="button" class="btn btn-outline-warning text-dark fw-bold py-2 btn-avail-toggle <?= $currentStatus === 'busy' ? 'active bg-warning text-dark' : '' ?>" data-status="busy">
                    <i class="fa-solid fa-clock me-2"></i> BUSY (Yellow)
                </button>
                <button type="button" class="btn btn-outline-danger fw-bold py-2 btn-avail-toggle <?= $currentStatus === 'unavailable' ? 'active bg-danger text-white' : '' ?>" data-status="unavailable">
                    <i class="fa-solid fa-circle-xmark me-2"></i> UNAVAILABLE (Red)
                </button>
            </div>

            <div class="p-3 bg-light rounded-3 border small text-muted text-start mt-4">
                <strong>Status Meanings:</strong>
                <ul class="ps-3 mb-0 mt-1">
                    <li><strong>Available:</strong> Patients can book active slots and join queues.</li>
                    <li><strong>Busy:</strong> In rounds, meetings, or surgery.</li>
                    <li><strong>Unavailable:</strong> Off duty or on leave. Slot booking disabled.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Consultation Hours Schedule Form -->
    <div class="col-lg-8">
        <div class="mq-card p-4 p-md-5 bg-white">
            <h5 class="fw-bold text-dark mb-1">Consultation Hours & Slot Settings</h5>
            <p class="text-muted small mb-4">Appointments will be partitioned into slots based on these hours.</p>

            <form action="<?= BASE_URL ?>/doctor/availability.php" method="POST" id="availForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="status" id="formStatusInput" value="<?= htmlspecialchars($currentStatus) ?>">

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="startTime" class="form-label small fw-semibold text-muted">Daily Shift Start Time *</label>
                        <input type="time" class="form-control py-2" id="startTime" name="start_time" value="<?= htmlspecialchars($currentStart) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="endTime" class="form-label small fw-semibold text-muted">Daily Shift End Time *</label>
                        <input type="time" class="form-control py-2" id="endTime" name="end_time" value="<?= htmlspecialchars($currentEnd) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="slotDuration" class="form-label small fw-semibold text-muted">Appointment Slot Duration</label>
                        <select class="form-select py-2" id="slotDuration" name="slot_duration">
                            <option value="15" <?= $currentDuration == 15 ? 'selected' : '' ?>>15 Minutes / Patient</option>
                            <option value="20" <?= $currentDuration == 20 ? 'selected' : '' ?>>20 Minutes / Patient</option>
                            <option value="30" <?= $currentDuration == 30 ? 'selected' : '' ?>>30 Minutes / Patient (Standard)</option>
                            <option value="45" <?= $currentDuration == 45 ? 'selected' : '' ?>>45 Minutes / Patient</option>
                            <option value="60" <?= $currentDuration == 60 ? 'selected' : '' ?>>60 Minutes / Patient</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="dayOfWeek" class="form-label small fw-semibold text-muted">Working Schedule Days</label>
                        <select class="form-select py-2" id="dayOfWeek" name="day_of_week">
                            <option value="All Days" <?= $currentDays === 'All Days' ? 'selected' : '' ?>>Monday – Sunday (All Days)</option>
                            <option value="Monday - Friday" <?= $currentDays === 'Monday - Friday' ? 'selected' : '' ?>>Monday – Friday (Weekdays)</option>
                            <option value="Monday - Saturday" <?= $currentDays === 'Monday - Saturday' ? 'selected' : '' ?>>Monday – Saturday</option>
                            <option value="Weekends Only" <?= $currentDays === 'Weekends Only' ? 'selected' : '' ?>>Saturday & Sunday (Weekends)</option>
                        </select>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary fw-semibold px-4 py-2">
                        <i class="fa-regular fa-floppy-disk me-1"></i> Save Schedule Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const doctorId = <?= (int)$doctorId ?>;
    const baseUrl = '<?= BASE_URL ?>';
    const formStatusInput = document.getElementById('formStatusInput');

    const toggleBtns = document.querySelectorAll('.btn-avail-toggle');
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const newSt = btn.getAttribute('data-status');
            
            // Send AJAX to instantly update
            const fd = new FormData();
            fd.append('doctor_id', doctorId);
            fd.append('status', newSt);

            fetch(`${baseUrl}/ajax/update_availability.php`, {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating availability');
                }
            })
            .catch(err => console.error(err));
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
