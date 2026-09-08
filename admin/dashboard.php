<?php
/**
 * MediQueue - Hospital Administrator & Staff Dashboard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$page_title = "Admin Dashboard";
$conn = get_db_connection();

// 1. High-Level KPI Statistics
$totalPatients = (int)$conn->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$todayApps = (int)$conn->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
$availableDoctors = (int)$conn->query("
    SELECT COUNT(d.id) FROM doctors d 
    LEFT JOIN doctor_availability da ON d.id = da.doctor_id 
    WHERE d.status = 'active' AND COALESCE(da.status, 'available') = 'available'
")->fetchColumn();
$waitingPatients = (int)$conn->query("SELECT COUNT(*) FROM queues WHERE status = 'waiting' AND DATE(joined_at) = CURDATE()")->fetchColumn();
$completedApps = (int)$conn->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'")->fetchColumn();
$cancelledApps = (int)$conn->query("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled'")->fetchColumn();

// Average waiting time for completed queues today
global $db_driver;
if (($db_driver ?? 'mysql') === 'sqlite') {
    $avgWait = (int)$conn->query("
        SELECT COALESCE(AVG(ROUND((strftime('%s', called_at) - strftime('%s', joined_at)) / 60)), 15)
        FROM queues 
        WHERE status IN ('completed', 'called', 'in_consultation') 
          AND called_at IS NOT NULL 
          AND DATE(joined_at) = CURDATE()
    ")->fetchColumn();
} else {
    $avgWait = (int)$conn->query("
        SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, joined_at, called_at)), 15)
        FROM queues 
        WHERE status IN ('completed', 'called', 'in_consultation') 
          AND called_at IS NOT NULL 
          AND DATE(joined_at) = CURDATE()
    ")->fetchColumn();
}
if ($avgWait <= 0) $avgWait = 15; // default reasonable average

// 2. Chart Data: Appointment Status Distribution
$statusRows = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    GROUP BY status
")->fetchAll();

$statusLabels = [];
$statusCounts = [];
foreach ($statusRows as $sr) {
    $statusLabels[] = ucfirst(str_replace('_', ' ', $sr['status']));
    $statusCounts[] = (int)$sr['count'];
}

// 3. Chart Data: Department Appointments Load
$deptRows = $conn->query("
    SELECT dept.department_name, COUNT(a.id) as app_count
    FROM departments dept
    LEFT JOIN doctors d ON dept.id = d.department_id
    LEFT JOIN appointments a ON d.id = a.doctor_id
    GROUP BY dept.id
    ORDER BY app_count DESC
")->fetchAll();

$deptLabels = [];
$deptCounts = [];
foreach ($deptRows as $dr) {
    $deptLabels[] = $dr['department_name'];
    $deptCounts[] = (int)$dr['app_count'];
}

// 4. Live Virtual Queues across hospital
$activeQueues = $conn->query("
    SELECT q.*, u.name as patient_name, doc_u.name as doctor_name, 
           dept.department_name, d.room_number, a.appointment_number
    FROM queues q
    JOIN patients p ON q.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON q.doctor_id = d.id
    JOIN users doc_u ON d.user_id = doc_u.id
    JOIN departments dept ON d.department_id = dept.id
    JOIN appointments a ON q.appointment_id = a.id
    WHERE DATE(q.joined_at) = CURDATE()
    ORDER BY 
        CASE 
            WHEN q.status = 'called' THEN 1
            WHEN q.status = 'in_consultation' THEN 2
            WHEN q.status = 'waiting' THEN 3
            ELSE 4 
        END,
        q.id ASC
    LIMIT 6
")->fetchAll();

// 5. Recent Appointments
$recentApps = $conn->query("
    SELECT a.*, u.name as patient_name, doc_u.name as doctor_name, dept.department_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users doc_u ON d.user_id = doc_u.id
    JOIN departments dept ON d.department_id = dept.id
    ORDER BY a.id DESC
    LIMIT 5
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Hospital Control Center</h3>
        <p class="text-muted mb-0">Overview of patient registrations, queue flow, doctor allocation, and appointments.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline-primary fw-semibold">
            <i class="fa-solid fa-chart-pie me-1"></i> Reports & Audits
        </a>
        <a href="<?= BASE_URL ?>/admin/appointments.php" class="btn btn-primary fw-semibold shadow-sm">
            <i class="fa-solid fa-calendar me-1"></i> All Appointments
        </a>
    </div>
</div>

<!-- KPI Stats Grid -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon primary">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= $totalPatients ?></div>
                <div class="mq-stat-label">Total Patients</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon secondary">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= $todayApps ?></div>
                <div class="mq-stat-label">Today's Visits</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon success">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= $availableDoctors ?></div>
                <div class="mq-stat-label">Doctors Available</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="mq-card mq-stat-card">
            <div class="mq-stat-icon warning">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div>
                <div class="mq-stat-number"><?= $waitingPatients ?></div>
                <div class="mq-stat-label">Waiting in Queue</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="mq-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Completed Consults</span>
                <span class="fs-4 fw-bold text-success"><?= $completedApps ?></span>
            </div>
            <i class="fa-solid fa-check-double fa-2x text-success text-opacity-25"></i>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="mq-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Cancelled Visits</span>
                <span class="fs-4 fw-bold text-danger"><?= $cancelledApps ?></span>
            </div>
            <i class="fa-solid fa-ban fa-2x text-danger text-opacity-25"></i>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="mq-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Avg Patient Wait Time</span>
                <span class="fs-4 fw-bold text-primary">~<?= $avgWait ?> Mins</span>
            </div>
            <i class="fa-solid fa-clock fa-2x text-primary text-opacity-25"></i>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Department Breakdown Chart -->
    <div class="col-lg-7">
        <div class="mq-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">Department Appointments Distribution</h5>
                <span class="badge bg-light text-muted border">Clinical Load</span>
            </div>
            <div style="height: 270px;">
                <canvas id="deptChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Status Doughnut Chart -->
    <div class="col-lg-5">
        <div class="mq-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">Appointment Status Mix</h5>
                <span class="badge bg-light text-muted border">System Wide</span>
            </div>
            <div style="height: 270px; display: flex; align-items: center; justify-content: center;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Live Hospital Queue Monitor -->
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="mq-card p-0 bg-white h-100">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Live Virtual Queue Monitor</h5>
                    <span class="text-muted small">Real-time turn positions across all clinical suites.</span>
                </div>
                <a href="<?= BASE_URL ?>/admin/queues.php" class="btn btn-sm btn-outline-primary fw-semibold">View All Queues</a>
            </div>

            <div class="mq-table-responsive">
                <table class="table mq-table align-middle">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Patient</th>
                            <th>Doctor & Suite</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activeQueues)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted small">No active queue entries today.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activeQueues as $q): ?>
                                <tr class="<?= $q['status'] === 'called' ? 'table-primary bg-opacity-10' : '' ?>">
                                    <td>
                                        <strong class="text-primary">#<?= htmlspecialchars($q['queue_number']) ?></strong>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($q['patient_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark"><?= htmlspecialchars($q['doctor_name']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($q['department_name']) ?> &bull; <?= htmlspecialchars($q['room_number'] ?: 'Suite 101') ?></div>
                                    </td>
                                    <td><?= get_status_badge($q['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Appointments List -->
    <div class="col-lg-5">
        <div class="mq-card p-0 bg-white h-100">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0">Latest Appointments</h5>
                <a href="<?= BASE_URL ?>/admin/appointments.php" class="btn btn-sm btn-link text-primary p-0 text-decoration-none fw-semibold">See All</a>
            </div>

            <div class="p-3">
                <?php if (empty($recentApps)): ?>
                    <p class="text-center text-muted small py-4 mb-0">No recent appointment activity.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentApps as $ra): ?>
                            <div class="list-group-item px-0 py-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="text-dark small d-block"><?= htmlspecialchars($ra['patient_name']) ?></strong>
                                        <span class="text-muted" style="font-size: 0.75rem;">with <?= htmlspecialchars($ra['doctor_name']) ?> (<?= htmlspecialchars($ra['department_name']) ?>)</span>
                                    </div>
                                    <div><?= get_status_badge($ra['status']) ?></div>
                                </div>
                                <div class="d-flex justify-content-between small text-muted mt-1" style="font-size: 0.75rem;">
                                    <span><?= format_date($ra['appointment_date']) ?> at <?= format_time($ra['appointment_time']) ?></span>
                                    <span class="text-primary fw-semibold"><?= htmlspecialchars($ra['appointment_number']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Setup -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Department Appointments Bar Chart
    const deptCtx = document.getElementById('deptChart');
    if (deptCtx) {
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($deptLabels) ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?= json_encode($deptCounts) ?>,
                    backgroundColor: '#0284c7',
                    borderRadius: 8,
                    barThickness: 28
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    // 2. Appointment Status Doughnut Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($statusLabels) ?>,
                datasets: [{
                    data: <?= json_encode($statusCounts) ?>,
                    backgroundColor: [
                        '#0284c7', // confirmed
                        '#0d9488', // in_queue
                        '#16a34a', // completed
                        '#dc2626', // cancelled
                        '#64748b'  // no_show
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                cutout: '70%'
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
