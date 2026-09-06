<?php
/**
 * MediQueue AJAX - Dynamic Time Slot Generator & Double Booking Prevention
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : '';

if (!$doctorId || empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Doctor ID and Date are required.']);
    exit;
}

// Ensure date is not in the past
if (strtotime($date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Appointments cannot be booked for past dates.']);
    exit;
}

$conn = get_db_connection();

// 1. Fetch Doctor Availability schedule
$availStmt = $conn->prepare("
    SELECT * FROM doctor_availability 
    WHERE doctor_id = ? 
    ORDER BY id DESC LIMIT 1
");
$availStmt->execute([$doctorId]);
$avail = $availStmt->fetch();

$startTime = $avail ? $avail['start_time'] : '09:00:00';
$endTime   = $avail ? $avail['end_time'] : '17:00:00';
$duration  = $avail ? (int)$avail['slot_duration'] : 30;
$availStatus = $avail ? $avail['status'] : 'available';

if ($availStatus === 'unavailable') {
    echo json_encode(['success' => false, 'message' => 'The selected doctor is currently unavailable for bookings on this schedule.']);
    exit;
}

// 2. Fetch existing booked appointments for this doctor on this date
$bookedStmt = $conn->prepare("
    SELECT appointment_time 
    FROM appointments 
    WHERE doctor_id = ? 
      AND appointment_date = ? 
      AND status NOT IN ('cancelled', 'no_show')
");
$bookedStmt->execute([$doctorId, $date]);
$bookedRows = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);

// Standardize booked times to 'H:i:s'
$bookedSlots = array_map(function($time) {
    return date('H:i:s', strtotime($time));
}, $bookedRows);

// 3. Generate slots between start and end time
$slots = [];
$startTs = strtotime($date . ' ' . $startTime);
$endTs   = strtotime($date . ' ' . $endTime);
$stepSec = $duration * 60;

$nowTs = time();

while ($startTs < $endTs) {
    $timeVal = date('H:i:s', $startTs);
    $formatted = date('h:i A', $startTs);
    
    // Check if slot has already passed today
    $isPast = ($date === date('Y-m-d') && $startTs < $nowTs);
    $isBooked = in_array($timeVal, $bookedSlots, true) || $isPast;

    $slots[] = [
        'time'      => $timeVal,
        'formatted' => $formatted,
        'is_booked' => $isBooked
    ];

    $startTs += $stepSec;
}

echo json_encode([
    'success' => true,
    'slots'   => $slots,
    'total'   => count($slots)
]);
