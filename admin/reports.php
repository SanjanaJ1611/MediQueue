<?php
/**
 * MediQueue - Admin: Clinical Reports & Hospital Audit Generator
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$page_title = "Hospital Reports & Audits";
$conn = get_db_connection();

// Date range filters
$startDate = trim($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));
$doctorFilter = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$statusFilter = trim($_GET['status'] ?? '');

$doctors = $conn->query("
    SELECT d.id, u.name as doctor_name, dept.department_name 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    JOIN departments dept ON d.department_id = dept.id
    ORDER BY u.name ASC
")->fetchAll();

// 1. Filtered Summary Metrics
$summaryStmt = $conn->prepare("
    SELECT 
        COUNT(a.id) as total_appointments,
        COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) as cancelled,
        COUNT(CASE WHEN a.status = 'no_show' THEN 1 END) as no_show,
        COUNT(CASE WHEN a.status IN ('confirmed', 'in_queue') THEN 1 END) as active_upcoming
    FROM appointments a
    WHERE a.appointment_date BETWEEN ? AND ?
      AND (? = 0 OR a.doctor_id = ?)
      AND (? = '' OR a.status = ?)
");
$summaryStmt->execute([$startDate, $endDate, $doctorFilter, $doctorFilter, $statusFilter, $statusFilter]);
$summary = $summaryStmt->fetch();

// 2. Doctor-wise Breakdown Table
$docBreakdownStmt = $conn->prepare("
    SELECT u.name as doctor_name, dept.department_name, d.room_number,
           COUNT(a.id) as total_scheduled,
           COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_count,
           COUNT(CASE WHEN a.status = 'no_show' THEN 1 END) as noshow_count,
           COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) as cancelled_count,
           COALESCE(SUM(CASE WHEN a.status = 'completed' THEN d.consultation_fee ELSE 0 END), 0) as total_revenue
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date BETWEEN ? AND ?
    GROUP BY d.id
    ORDER BY total_scheduled DESC
");
$docBreakdownStmt->execute([$startDate, $endDate]);
$doctorBreakdown = $docBreakdownStmt->fetchAll();

// 3. Filtered Audit Appointments Log
$query = "
    SELECT a.*, p.gender, p.blood_group,
           u.name as patient_name, u.phone as patient_phone,
           doc_u.name as doctor_name, dept.department_name, d.room_number
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users doc_u ON d.user_id = doc_u.id
    JOIN departments dept ON d.department_id = dept.id
    WHERE a.appointment_date BETWEEN ? AND ?
";
$params = [$startDate, $endDate];

if ($doctorFilter > 0) {
    $query .= " AND a.doctor_id = ?";
    $params[] = $doctorFilter;
}

if (!empty($statusFilter)) {
    $query .= " AND a.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";

$logStmt = $conn->prepare($query);
$logStmt->execute($params);
$reportLogs = $logStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <h3 class="fw-bold mb-1">Clinical Analytics & Audit Reports</h3>
        <p class="text-muted mb-0">Generate hospital metrics, doctor performance, patient turnout, and revenue.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary fw-semibold shadow-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Filter Criteria Bar (Hidden when printed) -->
<div class="mq-card p-3 mb-4 bg-white no-print">
    <form action="<?= BASE_URL ?>/admin/reports.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted mb-1">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted mb-1">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted mb-1">Doctor Filter</label>
            <select name="doctor_id" class="form-select">
                <option value="0">All Specialists</option>
                <?php foreach ($doctors as $doc): ?>
                    <option value="<?= $doc['id'] ?>" <?= $doctorFilter == $doc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($doc['doctor_name']) ?> (<?= htmlspecialchars($doc['department_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted mb-1">Status</label>
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="no_show" <?= $statusFilter === 'no_show' ? 'selected' : '' ?>>No Show</option>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100 fw-semibold" style="margin-top: 25px;">Go</button>
        </div>
    </form>
</div>

<!-- Printable Header (Visible on print) -->
<div class="d-none d-print-block text-center mb-4">
    <h2><?= APP_NAME ?> – Hospital Operations Report</h2>
    <p class="text-muted">Report Period: <?= format_date($startDate) ?> to <?= format_date($endDate) ?> &bull; Generated on <?= date('M d, Y h:i A') ?></p>
    <hr>
</div>

<!-- Summary Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="mq-card p-3 bg-white border">
            <span class="text-muted small d-block">Total Appointments</span>
            <span class="fs-3 fw-bold text-dark"><?= (int)$summary['total_appointments'] ?></span>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="mq-card p-3 bg-white border">
            <span class="text-muted small d-block">Completed Consults</span>
            <span class="fs-3 fw-bold text-success"><?= (int)$summary['completed'] ?></span>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="mq-card p-3 bg-white border">
            <span class="text-muted small d-block">Cancelled Visits</span>
            <span class="fs-3 fw-bold text-danger"><?= (int)$summary['cancelled'] ?></span>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="mq-card p-3 bg-white border">
            <span class="text-muted small d-block">No-Show Patients</span>
            <span class="fs-3 fw-bold text-secondary"><?= (int)$summary['no_show'] ?></span>
        </div>
    </div>
</div>

<!-- Doctor Performance Breakdown Table -->
<div class="mq-card p-0 bg-white mb-4">
    <div class="p-3 border-bottom bg-light">
        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-user-doctor me-1 text-primary"></i> Doctor & Department Breakdown</h6>
    </div>
    <div class="mq-table-responsive">
        <table class="table mq-table align-middle">
            <thead>
                <tr>
                    <th>Doctor Name</th>
                    <th>Department</th>
                    <th>Scheduled</th>
                    <th>Completed</th>
                    <th>No-Shows</th>
                    <th>Cancelled</th>
                    <th>Turnout Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($doctorBreakdown as $db): ?>
                    <?php 
                        $rate = $db['total_scheduled'] > 0 ? round(($db['completed_count'] / $db['total_scheduled']) * 100) : 0;
                    ?>
                    <tr>
                        <td><strong class="text-dark"><?= htmlspecialchars($db['doctor_name']) ?></strong></td>
                        <td><?= htmlspecialchars($db['department_name']) ?></td>
                        <td><?= $db['total_scheduled'] ?></td>
                        <td><span class="text-success fw-bold"><?= $db['completed_count'] ?></span></td>
                        <td><span class="text-muted"><?= $db['noshow_count'] ?></span></td>
                        <td><span class="text-danger"><?= $db['cancelled_count'] ?></span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: <?= $rate ?>%"></div>
                                </div>
                                <span class="small fw-semibold text-dark"><?= $rate ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detailed Log Table -->
<div class="mq-card p-0 bg-white">
    <div class="p-3 border-bottom bg-light">
        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-check me-1 text-primary"></i> Appointments Audit Log (<?= count($reportLogs) ?> Entries)</h6>
    </div>
    <div class="mq-table-responsive">
        <table class="table mq-table align-middle">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Ticket ID</th>
                    <th>Patient Name</th>
                    <th>Doctor & Room</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportLogs)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted small">No records match this date range.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportLogs as $rl): ?>
                        <tr>
                            <td>
                                <div><?= format_date($rl['appointment_date']) ?></div>
                                <div class="text-muted small"><?= format_time($rl['appointment_time']) ?></div>
                            </td>
                            <td><strong class="text-primary"><?= htmlspecialchars($rl['appointment_number']) ?></strong></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($rl['patient_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($rl['patient_phone'] ?: 'No phone') ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($rl['doctor_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($rl['department_name']) ?> (<?= htmlspecialchars($rl['room_number'] ?: 'Suite 101') ?>)</div>
                            </td>
                            <td><?= get_status_badge($rl['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
