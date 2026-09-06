<?php
/**
 * MediQueue - Admin / Staff: Visitor Pass Management Module
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$page_title = "Manage Visitors";
$conn = get_db_connection();

// Handle Register Visitor by Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_visitor') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $visitorName = trim($_POST['visitor_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $relationship = trim($_POST['relationship'] ?? '');
        $visitDate = trim($_POST['visit_date'] ?? date('Y-m-d'));
        $purpose = trim($_POST['purpose'] ?? '');
        $autoCheckIn = !empty($_POST['auto_check_in']);

        if ($patientId && !empty($visitorName) && !empty($phone)) {
            $status = $autoCheckIn ? 'checked_in' : 'expected';
            $entryTime = $autoCheckIn ? date('H:i:s') : null;

            $ins = $conn->prepare("
                INSERT INTO visitors (patient_id, visitor_name, relationship, phone, visit_date, purpose, entry_time, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([$patientId, $visitorName, $relationship, $phone, $visitDate, $purpose, $entryTime, $status]);

            set_flash('success', "Visitor pass issued for {$visitorName}.");
            header('Location: ' . BASE_URL . '/admin/visitors.php');
            exit;
        }
    }
}

// Handle Check-In / Check-Out Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_visitor_status') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $visId = (int)($_POST['visitor_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');

        if ($visId && in_array($newStatus, ['checked_in', 'checked_out', 'cancelled'], true)) {
            if ($newStatus === 'checked_in') {
                $upd = $conn->prepare("UPDATE visitors SET status = 'checked_in', entry_time = CURTIME() WHERE id = ?");
                $upd->execute([$visId]);
                set_flash('success', 'Visitor checked in.');
            } elseif ($newStatus === 'checked_out') {
                $upd = $conn->prepare("UPDATE visitors SET status = 'checked_out', exit_time = CURTIME() WHERE id = ?");
                $upd->execute([$visId]);
                set_flash('info', 'Visitor checked out.');
            } else {
                $upd = $conn->prepare("UPDATE visitors SET status = 'cancelled' WHERE id = ?");
                $upd->execute([$visId]);
                set_flash('warning', 'Visitor pass cancelled.');
            }
            header('Location: ' . BASE_URL . '/admin/visitors.php');
            exit;
        }
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');

$query = "
    SELECT v.*, u.name as patient_name, u.phone as patient_phone
    FROM visitors v
    JOIN patients p ON v.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (v.visitor_name LIKE ? OR u.name LIKE ? OR v.phone LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($statusFilter)) {
    $query .= " AND v.status = ?";
    $params[] = $statusFilter;
}

if (!empty($dateFilter)) {
    $query .= " AND v.visit_date = ?";
    $params[] = $dateFilter;
}

$query .= " ORDER BY v.visit_date DESC, v.id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$visitors = $stmt->fetchAll();

// Fetch patients list for registration modal
$patientsList = $conn->query("
    SELECT p.id, u.name, u.phone 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    ORDER BY u.name ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Visitor Management Center</h3>
        <p class="text-muted mb-0">Front-desk visitor check-in, companion pass issuance, and security tracking.</p>
    </div>
    <button type="button" class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#adminVisitorModal">
        <i class="fa-solid fa-id-card me-1"></i> Issue Visitor Pass
    </button>
</div>

<!-- Search & Filters -->
<div class="mq-card p-3 mb-4 bg-white">
    <form action="<?= BASE_URL ?>/admin/visitors.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search visitor or patient..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="expected" <?= $statusFilter === 'expected' ? 'selected' : '' ?>>Expected</option>
                <option value="checked_in" <?= $statusFilter === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                <option value="checked_out" <?= $statusFilter === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100 fw-semibold">Filter</button>
            <?php if ($search || $statusFilter || $dateFilter): ?>
                <a href="<?= BASE_URL ?>/admin/visitors.php" class="btn btn-light border" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Visitors Table -->
<div class="mq-card p-0 bg-white">
    <div class="mq-table-responsive">
        <table class="table mq-table align-middle">
            <thead>
                <tr>
                    <th>Visitor Details</th>
                    <th>Patient Accompanying</th>
                    <th>Relationship</th>
                    <th>Visit Date</th>
                    <th>Entry & Exit Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visitors)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted small">No visitor records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($visitors as $v): ?>
                        <tr>
                            <td>
                                <strong class="text-dark d-block"><?= htmlspecialchars($v['visitor_name']) ?></strong>
                                <span class="text-muted small"><?= htmlspecialchars($v['phone']) ?></span>
                            </td>
                            <td>
                                <strong class="text-primary d-block"><?= htmlspecialchars($v['patient_name']) ?></strong>
                                <span class="text-muted small"><?= htmlspecialchars($v['patient_phone']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($v['relationship']) ?></span>
                            </td>
                            <td><?= format_date($v['visit_date']) ?></td>
                            <td>
                                <span class="small d-block text-muted">In: <strong><?= $v['entry_time'] ? format_time($v['entry_time']) : '—' ?></strong></span>
                                <span class="small d-block text-muted">Out: <strong><?= $v['exit_time'] ? format_time($v['exit_time']) : '—' ?></strong></span>
                            </td>
                            <td><?= get_status_badge($v['status']) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($v['status'] === 'expected'): ?>
                                        <form action="<?= BASE_URL ?>/admin/visitors.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="update_visitor_status">
                                            <input type="hidden" name="visitor_id" value="<?= $v['id'] ?>">
                                            <input type="hidden" name="status" value="checked_in">
                                            <button type="submit" class="btn btn-sm btn-success" title="Check In">
                                                <i class="fa-solid fa-door-open me-1"></i> Check In
                                            </button>
                                        </form>
                                    <?php elseif ($v['status'] === 'checked_in'): ?>
                                        <form action="<?= BASE_URL ?>/admin/visitors.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="update_visitor_status">
                                            <input type="hidden" name="visitor_id" value="<?= $v['id'] ?>">
                                            <input type="hidden" name="status" value="checked_out">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Check Out">
                                                <i class="fa-solid fa-door-closed me-1"></i> Check Out
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Print Badge Modal Button -->
                                    <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#badgeModal_<?= $v['id'] ?>" title="Print Badge">
                                        <i class="fa-solid fa-print"></i>
                                    </button>
                                </div>

                                <!-- Printable Badge Modal -->
                                <div class="modal fade" id="badgeModal_<?= $v['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title fw-bold">Visitor Pass Preview</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4 text-center" id="printArea_<?= $v['id'] ?>">
                                                <div class="border border-2 border-primary rounded-4 p-4 bg-light shadow-sm">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="badge bg-primary px-3 py-1 fw-bold">HOSPITAL VISITOR PASS</span>
                                                        <span class="text-muted small"><?= date('Y-m-d') ?></span>
                                                    </div>
                                                    <h3 class="fw-bold text-dark mb-1"><?= htmlspecialchars($v['visitor_name']) ?></h3>
                                                    <span class="badge bg-light text-dark border mb-3"><?= htmlspecialchars($v['relationship']) ?></span>

                                                    <hr class="my-2">
                                                    <div class="text-start small mb-2">
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span class="text-muted">Visiting Patient:</span>
                                                            <strong class="text-dark"><?= htmlspecialchars($v['patient_name']) ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span class="text-muted">Visitor Contact:</span>
                                                            <strong><?= htmlspecialchars($v['phone']) ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between">
                                                            <span class="text-muted">Purpose:</span>
                                                            <strong><?= htmlspecialchars($v['purpose'] ?: 'Consultation companion') ?></strong>
                                                        </div>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.7rem;">Please wear this badge at all times on hospital grounds.</div>
                                                </div>
                                            </div>
                                            <div class="modal-footer justify-content-center">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="btn btn-primary btn-sm fw-semibold" onclick="window.print();">
                                                    <i class="fa-solid fa-print me-1"></i> Print Pass
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Issue Visitor Pass -->
<div class="modal fade" id="adminVisitorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/visitors.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_visitor">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-id-card me-2"></i> Issue Companion Pass</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Select Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">-- Choose Patient --</option>
                            <?php foreach ($patientsList as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['phone']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Visitor Full Name *</label>
                        <input type="text" name="visitor_name" class="form-control" placeholder="Mary Doe" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Relationship *</label>
                            <select name="relationship" class="form-select" required>
                                <option value="Spouse">Spouse</option>
                                <option value="Parent">Parent</option>
                                <option value="Child">Child</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Friend">Friend</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Phone Number *</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+1 (555) 000-0000" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Visit Date *</label>
                        <input type="date" name="visit_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Purpose of Visit</label>
                        <input type="text" name="purpose" class="form-control" placeholder="Accompanying for consultation">
                    </div>

                    <div class="form-check p-3 bg-light rounded border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="auto_check_in" value="1" id="autoCheckIn" checked>
                        <label class="form-check-label small fw-semibold text-dark" for="autoCheckIn">
                            Check In visitor immediately upon issuance
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold">Issue Pass</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
