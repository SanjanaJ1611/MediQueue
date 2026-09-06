<?php
/**
 * MediQueue - Doctor Dedicated Virtual Queue Console
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('doctor');

$page_title = "Doctor Queue Console";
$conn = get_db_connection();
$doctorId = $_SESSION['role_specific_id'] ?? 0;

$statusFilter = trim($_GET['status'] ?? '');

$query = "
    SELECT q.*, p.gender, p.blood_group, p.date_of_birth, u.name as patient_name, u.phone as patient_phone,
           a.appointment_number, a.appointment_time, a.reason
    FROM queues q
    JOIN patients p ON q.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN appointments a ON q.appointment_id = a.id
    WHERE q.doctor_id = ? 
      AND DATE(q.joined_at) = CURDATE()
";
$params = [$doctorId];

if (!empty($statusFilter)) {
    $query .= " AND q.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY 
    CASE 
        WHEN q.status = 'called' THEN 1
        WHEN q.status = 'in_consultation' THEN 2
        WHEN q.status = 'waiting' THEN 3
        WHEN q.status = 'completed' THEN 4
        ELSE 5 
    END,
    q.id ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$queueRows = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Live Patient Queue Console</h3>
        <p class="text-muted mb-0">Manage today's consultation queue, advance turn positions, and record clinical statuses.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm" id="btnCallNextQueue">
            <i class="fa-solid fa-bullhorn me-1"></i> CALL NEXT PATIENT
        </button>
    </div>
</div>

<!-- Filter Tabs -->
<div class="mq-card p-3 mb-4 bg-white">
    <div class="nav nav-pills flex-wrap gap-1">
        <a href="<?= BASE_URL ?>/doctor/queue.php" class="nav-link <?= empty($statusFilter) ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">All Today</a>
        <a href="<?= BASE_URL ?>/doctor/queue.php?status=waiting" class="nav-link <?= $statusFilter === 'waiting' ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">Waiting</a>
        <a href="<?= BASE_URL ?>/doctor/queue.php?status=called" class="nav-link <?= $statusFilter === 'called' ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">Called</a>
        <a href="<?= BASE_URL ?>/doctor/queue.php?status=in_consultation" class="nav-link <?= $statusFilter === 'in_consultation' ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">In Consultation</a>
        <a href="<?= BASE_URL ?>/doctor/queue.php?status=completed" class="nav-link <?= $statusFilter === 'completed' ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">Completed</a>
        <a href="<?= BASE_URL ?>/doctor/queue.php?status=no_show" class="nav-link <?= $statusFilter === 'no_show' ? 'active bg-primary' : 'text-muted' ?> py-1 px-3 small fw-semibold">No Show</a>
    </div>
</div>

<!-- Queue Management Table -->
<div class="mq-card p-0 bg-white shadow-sm">
    <div class="mq-table-responsive">
        <table class="table mq-table align-middle">
            <thead>
                <tr>
                    <th>Queue No</th>
                    <th>Patient Demographics</th>
                    <th>Ticket / Slot</th>
                    <th>Chief Complaint</th>
                    <th>Status</th>
                    <th>Clinical Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queueRows)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted small">
                            <i class="fa-solid fa-list-check fa-2x mb-2 d-block text-muted"></i>
                            No patients in queue matching this filter for today.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queueRows as $row): ?>
                        <tr class="<?= $row['status'] === 'called' ? 'table-primary bg-opacity-10' : '' ?>">
                            <td>
                                <strong class="fs-5 text-primary">#<?= htmlspecialchars($row['queue_number']) ?></strong>
                                <span class="text-muted d-block" style="font-size: 0.75rem;">Pos: <?= $row['queue_position'] ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($row['patient_name']) ?></div>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($row['gender'] ?? 'N/A') ?> &bull; 
                                    Blood: <strong class="text-danger"><?= htmlspecialchars($row['blood_group'] ?? 'N/A') ?></strong> &bull; 
                                    <?= htmlspecialchars($row['patient_phone'] ?: 'No Phone') ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border mb-1"><?= htmlspecialchars($row['appointment_number']) ?></span>
                                <div class="text-muted small"><?= format_time($row['appointment_time']) ?></div>
                            </td>
                            <td style="max-width: 250px;">
                                <span class="small text-dark"><?= htmlspecialchars($row['reason'] ?: 'Routine follow-up / general consult') ?></span>
                            </td>
                            <td>
                                <?= get_status_badge($row['status']) ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <?php if ($row['status'] === 'waiting'): ?>
                                        <button type="button" class="btn btn-sm btn-primary btn-queue-action" data-queue-id="<?= $row['id'] ?>" data-status="called">
                                            <i class="fa-solid fa-bullhorn me-1"></i> Call
                                        </button>
                                    <?php elseif ($row['status'] === 'called'): ?>
                                        <button type="button" class="btn btn-sm btn-info text-dark btn-queue-action" data-queue-id="<?= $row['id'] ?>" data-status="in_consultation">
                                            <i class="fa-solid fa-stethoscope me-1"></i> Start
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success btn-queue-action" data-queue-id="<?= $row['id'] ?>" data-status="completed">
                                            <i class="fa-solid fa-check me-1"></i> Complete
                                        </button>
                                    <?php elseif ($row['status'] === 'in_consultation'): ?>
                                        <button type="button" class="btn btn-sm btn-success btn-queue-action" data-queue-id="<?= $row['id'] ?>" data-status="completed">
                                            <i class="fa-solid fa-check me-1"></i> Complete
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!in_array($row['status'], ['completed', 'cancelled', 'no_show'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-queue-action" data-queue-id="<?= $row['id'] ?>" data-status="no_show" title="Patient No Show">
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

    const btnCall = document.getElementById('btnCallNextQueue');
    if (btnCall) {
        btnCall.addEventListener('click', () => {
            btnCall.disabled = true;
            btnCall.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Calling...';

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
                    alert(data.message || 'No waiting patients found.');
                    btnCall.disabled = false;
                    btnCall.innerHTML = '<i class="fa-solid fa-bullhorn me-1"></i> CALL NEXT PATIENT';
                }
            })
            .catch(err => {
                console.error(err);
                btnCall.disabled = false;
                btnCall.innerHTML = '<i class="fa-solid fa-bullhorn me-1"></i> CALL NEXT PATIENT';
            });
        });
    }

    const actionBtns = document.querySelectorAll('.btn-queue-action');
    actionBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const qId = btn.getAttribute('data-queue-id');
            const st = btn.getAttribute('data-status');
            btn.disabled = true;

            const fd = new FormData();
            fd.append('queue_id', qId);
            fd.append('status', st);

            fetch(`${baseUrl}/ajax/update_queue.php`, {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating status');
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
