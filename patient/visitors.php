<?php
/**
 * MediQueue - Patient: Visitor Pass Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('patient');

$page_title = "Manage Visitors";
$conn = get_db_connection();
$patientId = $_SESSION['role_specific_id'] ?? 0;
$userId = $_SESSION['user_id'];

$error = '';

// Handle New Visitor Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_visitor') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $visitorName = trim($_POST['visitor_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $relationship = trim($_POST['relationship'] ?? '');
        $visitDate = trim($_POST['visit_date'] ?? date('Y-m-d'));
        $purpose = trim($_POST['purpose'] ?? '');

        if (empty($visitorName) || empty($phone) || empty($relationship)) {
            $error = 'Please provide visitor name, contact phone, and relationship.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO visitors (patient_id, visitor_name, relationship, phone, visit_date, purpose, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'expected', NOW())
            ");
            $stmt->execute([$patientId, $visitorName, $relationship, $phone, $visitDate, $purpose]);

            set_flash('success', "Visitor pass registered for {$visitorName}.");
            header('Location: ' . BASE_URL . '/patient/visitors.php');
            exit;
        }
    }
}

// Handle Cancel Visitor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_visitor') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $visId = (int)($_POST['visitor_id'] ?? 0);
        $upd = $conn->prepare("UPDATE visitors SET status = 'cancelled' WHERE id = ? AND patient_id = ?");
        $upd->execute([$visId, $patientId]);
        set_flash('warning', 'Visitor pass cancelled.');
        header('Location: ' . BASE_URL . '/patient/visitors.php');
        exit;
    }
}

// Fetch visitors for this patient
$visitors = $conn->prepare("
    SELECT * FROM visitors 
    WHERE patient_id = ? 
    ORDER BY visit_date DESC, id DESC
");
$visitors->execute([$patientId]);
$visitorList = $visitors->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Companion & Visitor Passes</h3>
        <p class="text-muted mb-0">Pre-register family members or attendants accompanying you to your appointments.</p>
    </div>
    <button type="button" class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#newVisitorModal">
        <i class="fa-solid fa-user-plus me-1"></i> Pre-Register Visitor
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Visitors Table -->
<div class="mq-card p-0 bg-white">
    <div class="mq-table-responsive">
        <table class="table mq-table">
            <thead>
                <tr>
                    <th>Visitor Name</th>
                    <th>Relationship</th>
                    <th>Phone Contact</th>
                    <th>Visit Date</th>
                    <th>Entry / Exit</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visitorList)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted small">
                            <i class="fa-solid fa-id-badge fa-2x mb-2 d-block text-muted"></i>
                            No companion visitor passes registered. Click "Pre-Register Visitor" above.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($visitorList as $vis): ?>
                        <tr>
                            <td>
                                <strong class="text-dark d-block"><?= htmlspecialchars($vis['visitor_name']) ?></strong>
                                <?php if (!empty($vis['purpose'])): ?>
                                    <span class="text-muted small"><?= htmlspecialchars($vis['purpose']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($vis['relationship']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($vis['phone']) ?></td>
                            <td><?= format_date($vis['visit_date']) ?></td>
                            <td>
                                <span class="small text-muted d-block">In: <?= $vis['entry_time'] ? format_time($vis['entry_time']) : '—' ?></span>
                                <span class="small text-muted">Out: <?= $vis['exit_time'] ? format_time($vis['exit_time']) : '—' ?></span>
                            </td>
                            <td><?= get_status_badge($vis['status']) ?></td>
                            <td>
                                <?php if ($vis['status'] === 'expected'): ?>
                                    <form action="<?= BASE_URL ?>/patient/visitors.php" method="POST" class="d-inline" onsubmit="return confirm('Cancel this visitor pass?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="cancel_visitor">
                                        <input type="hidden" name="visitor_id" value="<?= $vis['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Pass">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Register Visitor Modal -->
<div class="modal fade" id="newVisitorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/patient/visitors.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_visitor">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-id-card me-2"></i> Pre-Register Companion Pass</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="visName" class="form-label small fw-semibold text-muted">Visitor Full Name *</label>
                        <input type="text" class="form-control" id="visName" name="visitor_name" placeholder="e.g. Mary Doe" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label for="visRel" class="form-label small fw-semibold text-muted">Relationship *</label>
                            <select class="form-select" id="visRel" name="relationship" required>
                                <option value="Spouse">Spouse</option>
                                <option value="Parent">Parent</option>
                                <option value="Child">Child</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Friend / Companion">Friend / Companion</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="visPhone" class="form-label small fw-semibold text-muted">Mobile Number *</label>
                            <input type="tel" class="form-control" id="visPhone" name="phone" placeholder="+1 (555) 000-0000" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="visDate" class="form-label small fw-semibold text-muted">Expected Visit Date *</label>
                        <input type="date" class="form-control" id="visDate" name="visit_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="visPurpose" class="form-label small fw-semibold text-muted">Purpose of Visit</label>
                        <input type="text" class="form-control" id="visPurpose" name="purpose" placeholder="e.g. Consultation companion / driver">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold">Register Visitor Pass</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
