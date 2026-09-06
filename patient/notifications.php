<?php
/**
 * MediQueue - Patient Notification Center
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('patient');

$page_title = "Notification Center";
$conn = get_db_connection();
$userId = $_SESSION['user_id'];

// Handle Mark All as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        set_flash('success', 'All notifications marked as read.');
        header('Location: ' . BASE_URL . '/patient/notifications.php');
        exit;
    }
}

// Fetch all notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Notification Center</h3>
        <p class="text-muted mb-0">Live updates, doctor queue announcements, and appointment confirmations.</p>
    </div>
    <?php if (!empty($notifications)): ?>
        <form action="<?= BASE_URL ?>/patient/notifications.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-outline-primary fw-semibold btn-sm">
                <i class="fa-solid fa-check-double me-1"></i> Mark All as Read
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <?php if (empty($notifications)): ?>
            <div class="mq-card text-center py-5">
                <div class="mq-stat-icon primary mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                    <i class="fa-regular fa-bell"></i>
                </div>
                <h5 class="fw-bold">No Notifications</h5>
                <p class="text-muted small">You're completely caught up! Turn calls and booking alerts will appear here.</p>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($notifications as $n): ?>
                    <?php 
                        $iconClass = 'fa-info';
                        $bgClass = 'primary';
                        if ($n['type'] === 'success') { $iconClass = 'fa-circle-check'; $bgClass = 'success'; }
                        elseif ($n['type'] === 'warning') { $iconClass = 'fa-triangle-exclamation'; $bgClass = 'warning'; }
                        elseif ($n['type'] === 'danger') { $iconClass = 'fa-circle-xmark'; $bgClass = 'danger'; }
                    ?>
                    <div class="mq-card p-3 p-md-4 <?= $n['is_read'] ? 'bg-white' : 'border-primary bg-primary-subtle bg-opacity-10' ?>">
                        <div class="d-flex align-items-start gap-3">
                            <div class="mq-stat-icon <?= $bgClass ?>" style="width: 44px; height: 44px; font-size: 1.1rem;">
                                <i class="fa-solid <?= $iconClass ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($n['title']) ?></h6>
                                    <span class="text-muted small"><?= time_ago($n['created_at']) ?></span>
                                </div>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($n['message']) ?></p>
                            </div>
                            <?php if (!$n['is_read']): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">New</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
