<?php
/**
 * MediQueue AJAX - Update Queue Status (In Consultation, Complete, No-Show, Cancel)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in() || !in_array($_SESSION['user_role'], ['doctor', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized operation.']);
    exit;
}

$queueId = isset($_POST['queue_id']) ? (int)$_POST['queue_id'] : 0;
$newStatus = isset($_POST['status']) ? trim($_POST['status']) : '';

$validStatuses = ['in_consultation', 'completed', 'no_show', 'cancelled', 'waiting', 'called'];
if (!$queueId || !in_array($newStatus, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid queue ID or status provided.']);
    exit;
}

$conn = get_db_connection();

try {
    $conn->beginTransaction();

    // Fetch queue details
    $qStmt = $conn->prepare("
        SELECT q.*, p.user_id as patient_user_id, u.name as patient_name
        FROM queues q
        JOIN patients p ON q.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE q.id = ?
    ");
    $qStmt->execute([$queueId]);
    $queue = $qStmt->fetch();

    if (!$queue) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Queue entry not found.']);
        exit;
    }

    // Update queue record
    $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : $queue['completed_at'];
    $calledAt = ($newStatus === 'called' && empty($queue['called_at'])) ? date('Y-m-d H:i:s') : $queue['called_at'];

    $upd = $conn->prepare("
        UPDATE queues 
        SET status = ?, called_at = ?, completed_at = ? 
        WHERE id = ?
    ");
    $upd->execute([$newStatus, $calledAt, $completedAt, $queueId]);

    // Update corresponding appointment
    $appStatus = $newStatus;
    if ($newStatus === 'called' || $newStatus === 'waiting') {
        $appStatus = 'in_queue';
    }
    $appUpd = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $appUpd->execute([$appStatus, $queue['appointment_id']]);

    // Recalculate remaining waiting patients
    recalculate_doctor_queue_times($conn, $queue['doctor_id']);

    // Send friendly notification to patient
    if ($newStatus === 'completed') {
        create_notification($conn, $queue['patient_user_id'], 'Consultation Completed', 'Your medical consultation has been concluded. Thank you for visiting MediQueue.', 'success');
    } elseif ($newStatus === 'in_consultation') {
        create_notification($conn, $queue['patient_user_id'], 'Consultation Started', 'Your doctor has commenced your consultation session.', 'info');
    }

    $conn->commit();

    echo json_encode([
        'success'    => true,
        'new_status' => $newStatus,
        'badge'      => get_status_badge($newStatus),
        'message'    => "Queue status updated to " . ucfirst(str_replace('_', ' ', $newStatus))
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
