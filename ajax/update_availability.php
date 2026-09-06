<?php
/**
 * MediQueue AJAX - Doctor Availability Status Switcher
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in() || !in_array($_SESSION['user_role'], ['doctor', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized operation.']);
    exit;
}

$conn = get_db_connection();

$doctorId = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
if (!$doctorId && $_SESSION['user_role'] === 'doctor') {
    $doctorId = (int)($_SESSION['role_specific_id'] ?? 0);
}

$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$valid = ['available', 'busy', 'unavailable'];

if (!$doctorId || !in_array($status, $valid, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

// Check if availability record exists
$stmt = $conn->prepare("SELECT id FROM doctor_availability WHERE doctor_id = ? LIMIT 1");
$stmt->execute([$doctorId]);
$availId = $stmt->fetchColumn();

if ($availId) {
    $upd = $conn->prepare("UPDATE doctor_availability SET status = ? WHERE id = ?");
    $upd->execute([$status, $availId]);
} else {
    $ins = $conn->prepare("INSERT INTO doctor_availability (doctor_id, status) VALUES (?, ?)");
    $ins->execute([$doctorId, $status]);
}

echo json_encode([
    'success'    => true,
    'status'     => $status,
    'badge_html' => get_status_badge($status),
    'message'    => 'Doctor availability updated to ' . ucfirst($status)
]);
