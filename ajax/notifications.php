<?php
/**
 * MediQueue AJAX - Notification Operations
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$conn = get_db_connection();
$userId = $_SESSION['user_id'];
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($action === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read.']);
    exit;
}

$notifId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($notifId) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notifId, $userId]);
    echo json_encode(['success' => true, 'message' => 'Notification marked as read.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
