<?php
/**
 * MediQueue - Top Navigation Bar
 */
$currentUser = current_user();
$conn = get_db_connection();
$unreadNotifsCount = $currentUser ? get_unread_notification_count($conn, $currentUser['id']) : 0;

// Get latest 4 notifications for dropdown
$recentNotifs = [];
if ($currentUser) {
    $nStmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 4");
    $nStmt->execute([$currentUser['id']]);
    $recentNotifs = $nStmt->fetchAll();
}

$roleRedirects = [
    'patient' => BASE_URL . '/patient/profile.php',
    'doctor'  => BASE_URL . '/doctor/profile.php',
    'admin'   => BASE_URL . '/admin/settings.php'
];
$profileUrl = $roleRedirects[$currentUser['role']] ?? BASE_URL . '/index.php';
?>
<header class="app-navbar">
    <!-- Left: Mobile Menu Toggle & Page Context -->
    <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-light d-lg-none p-2 border" id="sidebarToggle" aria-label="Toggle Sidebar">
            <i class="fa-solid fa-bars fs-5 text-muted"></i>
        </button>
        <div class="d-none d-sm-block">
            <span class="text-muted small"><i class="fa-regular fa-calendar me-1"></i> <?= date('l, F j, Y') ?></span>
        </div>
    </div>

    <!-- Right: Notifications & User Profile -->
    <div class="d-flex align-items-center gap-3">
        <!-- Live Notifications Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light position-relative p-2 rounded-circle border" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="fa-regular fa-bell text-muted fs-5"></i>
                <?php if ($unreadNotifsCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light">
                        <?= $unreadNotifsCount ?>
                        <span class="visually-hidden">unread notifications</span>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="width: 320px;">
                <li class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light rounded-top">
                    <span class="fw-bold small text-dark"><i class="fa-solid fa-bell me-1 text-primary"></i> Notifications</span>
                    <?php if ($unreadNotifsCount > 0): ?>
                        <span class="badge bg-primary-subtle text-primary"><?= $unreadNotifsCount ?> New</span>
                    <?php endif; ?>
                </li>
                
                <div style="max-height: 280px; overflow-y: auto;">
                    <?php if (empty($recentNotifs)): ?>
                        <li class="p-3 text-center text-muted small">No notifications</li>
                    <?php else: ?>
                        <?php foreach ($recentNotifs as $notif): ?>
                            <li class="p-3 border-bottom <?= $notif['is_read'] ? 'bg-white' : 'bg-light' ?>">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <strong class="small text-dark"><?= htmlspecialchars($notif['title']) ?></strong>
                                    <span class="text-muted" style="font-size: 0.7rem;"><?= time_ago($notif['created_at']) ?></span>
                                </div>
                                <p class="text-muted small mb-0" style="font-size: 0.8rem;"><?= htmlspecialchars($notif['message']) ?></p>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <li class="p-2 text-center bg-light rounded-bottom">
                    <?php 
                        $allNotifUrl = ($currentUser['role'] === 'patient') ? BASE_URL . '/patient/notifications.php' : '#';
                    ?>
                    <a href="<?= $allNotifUrl ?>" class="small fw-semibold text-primary">View all notifications</a>
                </li>
            </ul>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <div class="navbar-user-badge" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar">
                    <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="d-none d-md-block text-start">
                    <div class="fw-semibold small text-dark lh-sm"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></div>
                    <div class="text-muted text-capitalize" style="font-size: 0.725rem;"><?= htmlspecialchars($currentUser['role']) ?></div>
                </div>
                <i class="fa-solid fa-chevron-down text-muted small ms-1"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                <li class="px-3 py-2 border-bottom">
                    <div class="small fw-bold text-dark"><?= htmlspecialchars($currentUser['name']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($currentUser['email']) ?></div>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="<?= $profileUrl ?>">
                        <i class="fa-regular fa-user me-2 text-muted"></i> My Profile
                    </a>
                </li>
                <?php if ($currentUser['role'] === 'patient'): ?>
                <li>
                    <a class="dropdown-item py-2" href="<?= BASE_URL ?>/patient/appointments.php">
                        <i class="fa-regular fa-calendar me-2 text-muted"></i> Appointments
                    </a>
                </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/logout.php">
                        <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Sign Out
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
