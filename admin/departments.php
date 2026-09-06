<?php
/**
 * MediQueue - Admin: Department Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$page_title = "Manage Departments";
$conn = get_db_connection();

// Handle Add Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_dept') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $name = trim($_POST['department_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-stethoscope');

        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO departments (department_name, description, icon, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$name, $desc, $icon]);
            set_flash('success', "Department '{$name}' created.");
            header('Location: ' . BASE_URL . '/admin/departments.php');
            exit;
        }
    }
}

// Handle Delete Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dept') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $deptId = (int)($_POST['dept_id'] ?? 0);
        // Check if any doctors assigned
        $chk = $conn->prepare("SELECT COUNT(*) FROM doctors WHERE department_id = ?");
        $chk->execute([$deptId]);
        if ($chk->fetchColumn() > 0) {
            set_flash('danger', 'Cannot delete department with active assigned doctors. Please reassign doctors first.');
        } else {
            $del = $conn->prepare("DELETE FROM departments WHERE id = ?");
            $del->execute([$deptId]);
            set_flash('success', 'Department deleted successfully.');
        }
        header('Location: ' . BASE_URL . '/admin/departments.php');
        exit;
    }
}

// Fetch all departments with doctor counts
$departments = $conn->query("
    SELECT d.*, COUNT(doc.id) as doctor_count
    FROM departments d
    LEFT JOIN doctors doc ON d.id = doc.department_id
    GROUP BY d.id
    ORDER BY d.id ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Clinical Departments</h3>
        <p class="text-muted mb-0">Manage hospital medical divisions, descriptions, and iconography.</p>
    </div>
    <button type="button" class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#newDeptModal">
        <i class="fa-solid fa-plus me-1"></i> Add Department
    </button>
</div>

<!-- Department Grid -->
<div class="row g-4">
    <?php foreach ($departments as $dept): ?>
        <div class="col-lg-4 col-md-6">
            <div class="mq-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="mq-stat-icon primary">
                        <i class="fa-solid <?= htmlspecialchars($dept['icon'] ?: 'fa-stethoscope') ?>"></i>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <?= $dept['doctor_count'] ?> Doctors
                    </span>
                </div>
                <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($dept['department_name']) ?></h5>
                <p class="text-muted small mb-4 flex-grow-1"><?= htmlspecialchars($dept['description']) ?></p>

                <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Icon: <code><?= htmlspecialchars($dept['icon']) ?></code></span>
                    <?php if ($dept['doctor_count'] == 0): ?>
                        <form action="<?= BASE_URL ?>/admin/departments.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this department?');">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="delete_dept">
                            <input type="hidden" name="dept_id" value="<?= $dept['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Department">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/admin/doctors.php" class="btn btn-sm btn-light border text-muted">View Doctors</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="newDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/departments.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_dept">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-hospital me-2"></i> Add Medical Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Department Name *</label>
                        <input type="text" name="department_name" class="form-control" placeholder="e.g. Ophthalmology" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Font Awesome Icon Class</label>
                        <input type="text" name="icon" class="form-control" value="fa-stethoscope" placeholder="e.g. fa-eye, fa-lungs, fa-heart">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Department Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Briefly describe the clinical focus..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold">Create Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
