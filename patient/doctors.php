<?php
/**
 * MediQueue - Patient: Find Doctors & View Availability
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('patient');

$page_title = "Find Doctors";
$conn = get_db_connection();

// Filter parameters
$search = trim($_GET['search'] ?? '');
$selectedDept = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$selectedStatus = trim($_GET['status'] ?? '');

// Fetch all departments for dropdown filter
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll();

// Build query
$query = "
    SELECT d.*, u.name as doctor_name, u.email as doctor_email, u.phone as doctor_phone,
           dept.department_name, dept.icon as dept_icon,
           COALESCE(da.status, 'available') as avail_status,
           da.start_time, da.end_time, da.slot_duration
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN doctor_availability da ON d.id = da.doctor_id
    WHERE d.status = 'active'
";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR d.specialization LIKE ? OR d.qualification LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($selectedDept > 0) {
    $query .= " AND d.department_id = ?";
    $params[] = $selectedDept;
}

if (!empty($selectedStatus)) {
    $query .= " AND COALESCE(da.status, 'available') = ?";
    $params[] = $selectedStatus;
}

$query .= " ORDER BY u.name ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Find Doctors & Specialists</h3>
        <p class="text-muted mb-0">Check real-time availability and book your medical visit.</p>
    </div>
    <a href="<?= BASE_URL ?>/patient/book_appointment.php" class="btn btn-primary fw-semibold">
        <i class="fa-solid fa-calendar-plus me-1"></i> Quick Book
    </a>
</div>

<!-- Search & Filter Bar -->
<div class="mq-card p-3 mb-4 bg-white">
    <form action="<?= BASE_URL ?>/patient/doctors.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search doctor or specialization..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="department_id" class="form-select">
                <option value="0">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>" <?= $selectedDept == $dept['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dept['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Availabilities</option>
                <option value="available" <?= $selectedStatus === 'available' ? 'selected' : '' ?>>Available (Green)</option>
                <option value="busy" <?= $selectedStatus === 'busy' ? 'selected' : '' ?>>Busy (Yellow)</option>
                <option value="unavailable" <?= $selectedStatus === 'unavailable' ? 'selected' : '' ?>>Unavailable (Red)</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100 fw-semibold">Filter</button>
            <?php if (!empty($search) || $selectedDept > 0 || !empty($selectedStatus)): ?>
                <a href="<?= BASE_URL ?>/patient/doctors.php" class="btn btn-light border" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Doctors Grid -->
<div class="row g-4">
    <?php if (empty($doctors)): ?>
        <div class="col-12">
            <div class="mq-card text-center py-5">
                <div class="mq-stat-icon primary mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
                <h5 class="fw-bold">No Doctors Found</h5>
                <p class="text-muted small">Try broadening your search query or department filters.</p>
                <a href="<?= BASE_URL ?>/patient/doctors.php" class="btn btn-sm btn-outline-primary">Clear Filters</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($doctors as $doc): ?>
            <div class="col-lg-4 col-md-6">
                <div class="mq-card h-100 d-flex flex-column p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="user-avatar" style="width: 48px; height: 48px; font-size: 1.15rem;">
                                <?= strtoupper(substr($doc['doctor_name'], 4, 1)) ?>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($doc['doctor_name']) ?></h5>
                                <span class="badge bg-primary-subtle text-primary mt-1">
                                    <i class="fa-solid <?= htmlspecialchars($doc['dept_icon'] ?: 'fa-stethoscope') ?> me-1"></i>
                                    <?= htmlspecialchars($doc['department_name']) ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <?= get_status_badge($doc['avail_status']) ?>
                        </div>
                    </div>

                    <div class="my-2">
                        <div class="text-muted small fw-semibold mb-1">Specialization:</div>
                        <p class="small text-dark mb-2"><?= htmlspecialchars($doc['specialization']) ?></p>

                        <div class="small text-muted mb-1">
                            <i class="fa-solid fa-graduation-cap me-1 text-primary"></i> <?= htmlspecialchars($doc['qualification']) ?>
                        </div>
                        <div class="small text-muted mb-1">
                            <i class="fa-solid fa-briefcase me-1 text-primary"></i> <?= (int)$doc['experience_years'] ?> Years Clinical Experience
                        </div>
                        <div class="small text-muted mb-2">
                            <i class="fa-solid fa-door-open me-1 text-primary"></i> Room: <strong><?= htmlspecialchars($doc['room_number'] ?: 'Room 101') ?></strong>
                        </div>
                        <div class="small text-muted">
                            <i class="fa-solid fa-clock me-1 text-primary"></i> Hours: 
                            <?= format_time($doc['start_time'] ?: '09:00:00') ?> - <?= format_time($doc['end_time'] ?: '17:00:00') ?>
                        </div>
                    </div>

                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small d-block">Consultation Fee</span>
                            <span class="fw-bold text-dark fs-5">$<?= number_format($doc['consultation_fee'], 2) ?></span>
                        </div>
                        <?php if ($doc['avail_status'] === 'unavailable'): ?>
                            <button class="btn btn-secondary btn-sm px-3 disabled" disabled>
                                Unavailable
                            </button>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/patient/book_appointment.php?doctor_id=<?= $doc['id'] ?>" class="btn btn-primary btn-sm px-3 fw-semibold">
                                <i class="fa-regular fa-calendar-check me-1"></i> Book Slot
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
