<?php
/**
 * MediQueue AJAX - Real-time Queue Status Poller
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$queueId = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : 0;

if (!$queueId) {
    echo json_encode(['success' => false, 'message' => 'Valid Queue ID is required.']);
    exit;
}

$conn = get_db_connection();

// Fetch queue and doctor details
$stmt = $conn->prepare("
    SELECT q.*, 
           u.name as doctor_name, 
           d.room_number,
           dept.department_name,
           a.appointment_number,
           a.appointment_time
    FROM queues q
    JOIN doctors d ON q.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    JOIN appointments a ON q.appointment_id = a.id
    WHERE q.id = ?
    LIMIT 1
");
$stmt->execute([$queueId]);
$queue = $stmt->fetch();

if (!$queue) {
    echo json_encode(['success' => false, 'message' => 'Queue record not found.']);
    exit;
}

// Calculate live position
$posStmt = $conn->prepare("
    SELECT COUNT(*) as patients_ahead 
    FROM queues 
    WHERE doctor_id = ? 
      AND status IN ('waiting', 'called', 'in_consultation')
      AND id < ?
      AND DATE(joined_at) = CURDATE()
");
$posStmt->execute([$queue['doctor_id'], $queue['id']]);
$ahead = (int)($posStmt->fetch()['patients_ahead'] ?? 0);

$currentPos = $ahead + 1;
$estimatedWait = $queue['status'] === 'waiting' ? ($ahead * 15) : 0;

$queue['patients_ahead'] = $ahead;
$queue['queue_position'] = ($queue['status'] === 'waiting') ? $currentPos : (($queue['status'] === 'called') ? 1 : 0);
$queue['estimated_waiting_time'] = $estimatedWait;
$queue['badge_html'] = get_status_badge($queue['status']);

echo json_encode([
    'success' => true,
    'queue'   => $queue
]);
