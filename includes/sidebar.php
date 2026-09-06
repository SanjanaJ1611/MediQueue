<?php
/**
 * MediQueue - Role-Based Sidebar Navigation
 */
$currentUser = current_user();
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$role = $currentUser['role'] ?? 'patient';

function is_nav_active($path) {
    global $currentUri;
    return strpos($currentUri, $path) !== false ? 'active' : '';
}
?>
<aside class="app-sidebar">
    <!-- Sidebar Brand -->
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>/index.php" class="sidebar-brand">
            <div class="brand-icon">
                <i class="fa-solid fa-hospital-user"></i>
            </div>
            <span><?= APP_NAME ?></span>
        </a>
    </div>

    <!-- Navigation Menus -->
    <ul class="sidebar-menu">
        <?php if ($role === 'patient'): ?>
            <li class="sidebar-heading">Patient Portal</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/patient/dashboard.php" class="sidebar-link <?= is_nav_active('patient/dashboard.php') ?>">
                    <i class="fa-solid fa-gauge"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/patient/doctors.php" class="sidebar-link <?= is_nav_active('patient/doctors.php') ?>">
                    <i class="fa-solid fa-user-doctor"></i>
                    <span>Find Doctors</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/patient/book_appointment.php" class="sidebar-link <?= is_nav_active('patient/book_appointment.php') ?>">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <span>Book Appointment</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/patient/appointments.php" class="sidebar-link <?= is_nav_active('patient/appointments.php') ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>My Appointments</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/patient/queue.php" class="sidebar-link <?= is_nav_active('patient/queue.php') ?>">
                    <i class="fa-solid fa-list-ol"></i>
                    <span>Virtual Queue</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/patient/visitors.php" class="sidebar-link <?= is_nav_active('patient/visitors.php') ?>">
                    <i class="fa-solid fa-id-card"></i>
                    <span>Visitor Passes</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/patient/notifications.php" class="sidebar-link <?= is_nav_active('patient/notifications.php') ?>">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/patient/profile.php" class="sidebar-link <?= is_nav_active('patient/profile.php') ?>">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>My Profile</span>
                </a>
            </li>

        <?php elseif ($role === 'doctor'): ?>
            <li class="sidebar-heading">Doctor Console</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/doctor/dashboard.php" class="sidebar-link <?= is_nav_active('doctor/dashboard.php') ?>">
                    <i class="fa-solid fa-gauge"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/doctor/queue.php" class="sidebar-link <?= is_nav_active('doctor/queue.php') ?>">
                    <i class="fa-solid fa-list-ol"></i>
                    <span>Patient Queue</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/doctor/appointments.php" class="sidebar-link <?= is_nav_active('doctor/appointments.php') ?>">
                    <i class="fa-solid fa-calendar-day"></i>
                    <span>Appointments</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/doctor/patients.php" class="sidebar-link <?= is_nav_active('doctor/patients.php') ?>">
                    <i class="fa-solid fa-users"></i>
                    <span>Assigned Patients</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/doctor/availability.php" class="sidebar-link <?= is_nav_active('doctor/availability.php') ?>">
                    <i class="fa-solid fa-clock"></i>
                    <span>My Availability</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/doctor/profile.php" class="sidebar-link <?= is_nav_active('doctor/profile.php') ?>">
                    <i class="fa-solid fa-user-doctor"></i>
                    <span>Doctor Profile</span>
                </a>
            </li>

        <?php elseif ($role === 'admin'): ?>
            <li class="sidebar-heading">Hospital Administration</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link <?= is_nav_active('admin/dashboard.php') ?>">
                    <i class="fa-solid fa-gauge"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/admin/appointments.php" class="sidebar-link <?= is_nav_active('admin/appointments.php') ?>">
                    <i class="fa-solid fa-calendar"></i>
                    <span>Appointments</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/admin/queues.php" class="sidebar-link <?= is_nav_active('admin/queues.php') ?>">
                    <i class="fa-solid fa-list-ol"></i>
                    <span>Virtual Queues</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/admin/doctors.php" class="sidebar-link <?= is_nav_active('admin/doctors.php') ?>">
                    <i class="fa-solid fa-user-doctor"></i>
                    <span>Doctors</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/admin/patients.php" class="sidebar-link <?= is_nav_active('admin/patients.php') ?>">
                    <i class="fa-solid fa-users"></i>
                    <span>Patients</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/admin/departments.php" class="sidebar-link <?= is_nav_active('admin/departments.php') ?>">
                    <i class="fa-solid fa-hospital"></i>
                    <span>Departments</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/admin/visitors.php" class="sidebar-link <?= is_nav_active('admin/visitors.php') ?>">
                    <i class="fa-solid fa-id-card"></i>
                    <span>Visitors</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/admin/reports.php" class="sidebar-link <?= is_nav_active('admin/reports.php') ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Hospital Reports</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>/admin/settings.php" class="sidebar-link <?= is_nav_active('admin/settings.php') ?>">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Settings</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-2 py-2">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span class="fw-semibold">Sign Out</span>
        </a>
    </div>
</aside>
