<?php
/**
 * MediQueue - Admin: Virtual Queues Control Board
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$page_title = "Manage Virtual Queues";
$conn = get_db_connection();

$doctorFilter = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$statusFilter = trim($_GET['status'] ?? '');

$doctors = $conn->query("
    SELECT d.id, u.name as doctor_name, dept.department_name 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    JOIN departments dept ON d.department_id = dept.id
    ORDER BY u.name ASC
")->fetchAll();

$query = "
    SELECT q.*, u.name as patient_name, u.phone as patient_phone,
           doc_u.name as doctor_name, dept.department_name, d.room_number,
           a.appointment_number, a.appointment_time
    FROM queues q
    JOIN patients p ON q.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON q.doctor_id = d.id
    JOIN users doc_u ON d.user_id = doc_u.id
    JOIN departments dept ON d.department_id = dept.id
    JOIN appointments a ON q.appointment_id = a.id
    WHERE DATE(q.joined_at) = CURDATE()
";
$params = [];

if ($doctorFilter > 0) {
    $query .= " AND q.doctor_id = ?";
    $params[] = $doctorFilter;
}

if (!empty($statusFilter)) {
    $query .= " AND q.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY 
    CASE 
        WHEN q.status = 'called' THEN 1
        WHEN q.status = 'in_consultation' THEN 2
        WHEN q.status = 'waiting' THEN 3
        ELSE 4 
    END,
    q.id ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$queues = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Live Virtual Queues Board</h3>
        <p class="text-muted mb-0">Monitor today's patient progression across all active medical suites.</p>
    </div>
</div>

<!-- Filters -->
<div class="mq-card p-3 mb-4 bg-white">
    <form action="<?= BASE_URL ?>/admin/queues.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <select name="doctor_id" class="form-select">
                <option value="0">All Doctors & Clinics</option>
                <?php foreach ($doctors as $doc): ?>
                    <option value="<?= $doc['id'] ?>" <?= $doctorFilter == $doc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($doc['doctor_name']) ?> (<?= htmlspecialchars($doc['department_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select">
                <option value="">All Queue Statuses</option>
                <option value="waiting" <?= $statusFilter === 'waiting' ? 'selected' : '' ?>>Waiting</option>
                <option value="called" <?= $statusFilter === 'called' ? 'selected' : '' ?>>Called</option>
                <option value="in_consultation" <?= $statusFilter === 'in_consultation' ? 'selected' : '' ?>>In Consultation</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="no_show" <?= $statusFilter === 'no_show' ? 'selected' : '' ?>>No Show</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100 fw-semibold">Filter Queues</button>
            <?php if ($doctorFilter || $statusFilter): ?>
                <a href="<?= BASE_URL ?>/admin/queues.php" class="btn btn-light border" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Queues Table -->
<div class="mq-card p-0 bg-white">
    <div class="mq-table-responsive">
        <table class="table mq-table align-middle">
            <thead>
                <tr>
                    <th>Queue Ticket</th>
                    <th>Patient Name</th>
                    <th>Assigned Doctor & Room</th>
                    <th>Joined At</th>
                    <th>Position / Est Wait</th>
                    <th>Status</th>
                    <th>Admin Override</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queues)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted small">No active queue entries found for today.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queues as $q): ?>
                        <tr class="<?= $q['status'] === 'called' ? 'table-primary bg-opacity-10' : '' ?>">
                            <td>
                                <strong class="fs-6 text-primary">#<?= htmlspecialchars($q['queue_number']) ?></strong>
                                <span class="d-block text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($q['appointment_number']) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($q['patient_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($q['patient_phone'] ?: 'N/A') ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($q['doctor_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($q['department_name']) ?> &bull; <?= htmlspecialchars($q['room_number'] ?: 'Suite 101') ?></div>
                            </td>
                            <td>
                                <span class="small text-dark"><?= date('h:i A', strtotime($q['joined_at'])) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">#<?= $q['queue_position'] ?></span>
                                <span class="text-muted small ms-1"><?= $q['estimated_waiting_time'] ?>m</span>
                            </td>
                            <td>
                                <?= get_status_badge($q['status']) ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-admin-q" data-queue-id="<?= $q['id'] ?>" data-status="called" title="Call Now">
                                        <i class="fa-solid fa-bullhorn"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success btn-admin-q" data-queue-id="<?= $q['id'] ?>" data-status="completed" title="Mark Completed">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-admin-q" data-queue-id="<?= $q['id'] ?>" data-status="no_show" title="Mark No Show">
                                        <i class="fa-solid fa-user-slash"></i>
                                    </button>
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
    const baseUrl = '<?= BASE_URL ?>';
    const adminBtns = document.querySelectorAll('.btn-admin-q');
    adminBtns.forEach(btn => {
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
                    alert(data.message || 'Error updating queue');
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
