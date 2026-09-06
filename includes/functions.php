<?php
/**
 * MediQueue - Helper Functions & Queue Utilities
 */

// Input Sanitization
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

// Date and Time Formatting
function format_date($dateStr) {
    if (empty($dateStr) || $dateStr === '0000-00-00') return 'N/A';
    $timestamp = strtotime($dateStr);
    return date('M d, Y', $timestamp);
}

function format_time($timeStr) {
    if (empty($timeStr)) return 'N/A';
    $timestamp = strtotime($timeStr);
    return date('h:i A', $timestamp);
}

function format_datetime($datetimeStr) {
    if (empty($datetimeStr)) return 'N/A';
    $timestamp = strtotime($datetimeStr);
    return date('M d, Y h:i A', $timestamp);
}

function time_ago($datetime) {
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    if ($difference < 60) return 'Just now';
    if ($difference < 3600) return round($difference / 60) . ' mins ago';
    if ($difference < 86400) return round($difference / 3600) . ' hours ago';
    return date('M d', $timestamp);
}

// Status Badges
function get_status_badge($status) {
    $status = strtolower(trim((string)$status));
    $badges = [
        'available'       => '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i> Available</span>',
        'busy'            => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="fa-solid fa-clock me-1"></i> Busy</span>',
        'unavailable'     => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-circle-xmark me-1"></i> Unavailable</span>',
        
        'waiting'         => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="fa-solid fa-hourglass-half me-1"></i> Waiting</span>',
        'called'          => '<span class="badge bg-primary text-white pulse-badge"><i class="fa-solid fa-bullhorn me-1"></i> Called</span>',
        'in_consultation' => '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle"><i class="fa-solid fa-user-doctor me-1"></i> In Consultation</span>',
        'completed'       => '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-check-double me-1"></i> Completed</span>',
        'cancelled'       => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-ban me-1"></i> Cancelled</span>',
        'no_show'         => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="fa-solid fa-user-slash me-1"></i> No Show</span>',
        
        'pending'         => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="fa-solid fa-clock me-1"></i> Pending</span>',
        'confirmed'       => '<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fa-solid fa-calendar-check me-1"></i> Confirmed</span>',
        'in_queue'        => '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle"><i class="fa-solid fa-users me-1"></i> In Queue</span>',

        'expected'        => '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle"><i class="fa-solid fa-clipboard-user me-1"></i> Expected</span>',
        'checked_in'      => '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-door-open me-1"></i> Checked In</span>',
        'checked_out'     => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="fa-solid fa-door-closed me-1"></i> Checked Out</span>',
    ];

    return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst($status)) . '</span>';
}

// Generate Unique Codes
function generate_appointment_number() {
    return 'MQ-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

function generate_queue_number($deptName, $sequence = null) {
    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $deptName), 0, 3));
    if (empty($prefix)) $prefix = 'MED';
    $num = $sequence ?: rand(101, 999);
    return $prefix . '-' . $num;
}

// Virtual Queue Calculations
function calculate_queue_position($pdo, $doctor_id, $queue_id) {
    // Count how many people are in 'waiting' or 'called' or 'in_consultation' who joined before this queue entry
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as patients_ahead 
        FROM queues 
        WHERE doctor_id = ? 
          AND status IN ('waiting', 'called', 'in_consultation')
          AND id < ?
          AND DATE(joined_at) = CURDATE()
    ");
    $stmt->execute([$doctor_id, $queue_id]);
    $ahead = (int)($stmt->fetch()['patients_ahead'] ?? 0);
    return $ahead + 1; // 1-indexed position
}

function recalculate_doctor_queue_times($pdo, $doctor_id, $avgMinutesPerPatient = 15) {
    // Fetch all currently waiting queues for today for this doctor
    $stmt = $pdo->prepare("
        SELECT id FROM queues 
        WHERE doctor_id = ? 
          AND status = 'waiting'
          AND DATE(joined_at) = CURDATE()
        ORDER BY id ASC
    ");
    $stmt->execute([$doctor_id]);
    $waitingQueues = $stmt->fetchAll();

    $pos = 1;
    // Check if someone is currently called/in_consultation
    $activeStmt = $pdo->prepare("
        SELECT COUNT(*) as active_count FROM queues 
        WHERE doctor_id = ? AND status IN ('called', 'in_consultation') AND DATE(joined_at) = CURDATE()
    ");
    $activeStmt->execute([$doctor_id]);
    $hasActive = (int)$activeStmt->fetch()['active_count'] > 0;

    foreach ($waitingQueues as $q) {
        $ahead = $hasActive ? $pos : ($pos - 1);
        $waitTime = $ahead * $avgMinutesPerPatient;
        $update = $pdo->prepare("UPDATE queues SET queue_position = ?, estimated_waiting_time = ? WHERE id = ?");
        $update->execute([$ahead + 1, $waitTime, $q['id']]);
        $pos++;
    }
}

// Notification Helpers
function create_notification($pdo, $user_id, $title, $message, $type = 'info') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        return $stmt->execute([$user_id, $title, $message, $type]);
    } catch (Exception $e) {
        return false;
    }
}

function get_unread_notification_count($pdo, $user_id) {
    if (!$user_id) return 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)($stmt->fetch()['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}
