<?php
/**
 * MediQueue AJAX - Doctor Queue Progression: Call Next Patient
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Verify authentication (only doctor or admin can call next patient)
if (!is_logged_in() || !in_array($_SESSION['user_role'], ['doctor', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized operation.']);
    exit;
}

$conn = get_db_connection();

// Determine doctor ID
$doctorId = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
if (!$doctorId && $_SESSION['user_role'] === 'doctor') {
    $doctorId = (int)($_SESSION['role_specific_id'] ?? 0);
}

if (!$doctorId) {
    echo json_encode(['success' => false, 'message' => 'Missing valid Doctor ID.']);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. Move any currently "called" or "in_consultation" patient to "completed"
    $activeStmt = $conn->prepare("
        SELECT id, appointment_id, patient_id FROM queues 
        WHERE doctor_id = ? 
          AND status IN ('called', 'in_consultation')
          AND DATE(joined_at) = CURDATE()
    ");
    $activeStmt->execute([$doctorId]);
    $activePatients = $activeStmt->fetchAll();

    foreach ($activePatients as $ap) {
        $upd = $conn->prepare("UPDATE queues SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $upd->execute([$ap['id']]);

        $updApp = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
        $updApp->execute([$ap['appointment_id']]);
    }

    // 2. Select the next patient with status 'waiting'
    $nextStmt = $conn->prepare("
        SELECT q.*, p.user_id as patient_user_id, u.name as patient_name,
               doc_u.name as doctor_name, d.room_number
        FROM queues q
        JOIN patients p ON q.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN doctors d ON q.doctor_id = d.id
        JOIN users doc_u ON d.user_id = doc_u.id
        WHERE q.doctor_id = ? 
          AND q.status = 'waiting'
          AND DATE(q.joined_at) = CURDATE()
        ORDER BY q.id ASC
        LIMIT 1
    ");
    $nextStmt->execute([$doctorId]);
    $nextQueue = $nextStmt->fetch();

    if (!$nextQueue) {
        $conn->commit();
        echo json_encode([
            'success' => false, 
            'empty'   => true, 
            'message' => 'No patients are currently waiting in the queue.'
        ]);
        exit;
    }

    // 3. Mark next patient as CALLED
    $callUpd = $conn->prepare("
        UPDATE queues 
        SET status = 'called', called_at = NOW(), queue_position = 1, estimated_waiting_time = 0 
        WHERE id = ?
    ");
    $callUpd->execute([$nextQueue['id']]);

    // 4. Create patient notification
    $title = "Your Turn Has Been Called!";
    $room = $nextQueue['room_number'] ?: 'Consultation Room';
    $msg = "Dr. {$nextQueue['doctor_name']} is ready to see you. Please proceed immediately to {$room}. (Queue #{$nextQueue['queue_number']})";
    create_notification($conn, $nextQueue['patient_user_id'], $title, $msg, 'success');

    // 5. Recalculate positions & wait times for remaining waiting patients
    recalculate_doctor_queue_times($conn, $doctorId);

    $conn->commit();

    echo json_encode([
        'success'      => true,
        'message'      => "Patient {$nextQueue['patient_name']} (#{$nextQueue['queue_number']}) has been called!",
        'called_queue' => $nextQueue
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
