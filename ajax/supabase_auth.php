<?php
/**
 * MediQueue - Supabase OAuth Token Verification & User Provisioning Endpoint
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Receive payload from JSON or POST form data
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

$accessToken = $inputData['access_token'] ?? $_POST['access_token'] ?? '';

if (empty($accessToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing access token.']);
    exit;
}

if (!is_supabase_configured()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Supabase is not configured on this server.']);
    exit;
}

// Verify access token with Supabase Auth API
$verifyUrl = SUPABASE_URL . '/auth/v1/user';
$headers = [
    'Authorization: Bearer ' . $accessToken,
    'apikey: ' . SUPABASE_ANON_KEY,
    'Content-Type: application/json'
];

$userData = null;

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $userData = json_decode($response, true);
    }
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 10,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    $response = @file_get_contents($verifyUrl, false, $context);
    if ($response) {
        $parsed = json_decode($response, true);
        if (!empty($parsed['email'])) {
            $userData = $parsed;
        }
    }
}

if (!$userData || empty($userData['email'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unable to verify Supabase authentication token.'
    ]);
    exit;
}

$email = trim(strtolower($userData['email']));
$userMetadata = $userData['user_metadata'] ?? [];
$name = trim($userMetadata['full_name'] ?? $userMetadata['name'] ?? explode('@', $email)[0]);
$avatar = $userMetadata['avatar_url'] ?? $userMetadata['picture'] ?? null;

$conn = get_db_connection();

try {
    // Check if user already exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['status'] !== 'active') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Your account is deactivated. Please contact administration.'
            ]);
            exit;
        }

        // Update avatar if not already set
        if (!empty($avatar) && empty($user['avatar'])) {
            $upStmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $upStmt->execute([$avatar, $user['id']]);
            $user['avatar'] = $avatar;
        }

        login_user($user, $conn);
        set_flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');

        $redirect = BASE_URL . '/patient/dashboard.php';
        if ($user['role'] === 'admin') {
            $redirect = BASE_URL . '/admin/dashboard.php';
        } elseif ($user['role'] === 'doctor') {
            $redirect = BASE_URL . '/doctor/dashboard.php';
        }

        echo json_encode([
            'success' => true,
            'redirect' => $redirect,
            'message' => 'Authenticated successfully.'
        ]);
        exit;
    } else {
        // Auto-provision new patient account
        $conn->beginTransaction();

        $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $insertUserStmt = $conn->prepare("
            INSERT INTO users (name, email, password, role, status, avatar)
            VALUES (?, ?, ?, 'patient', 'active', ?)
        ");
        $insertUserStmt->execute([$name, $email, $randomPassword, $avatar]);
        $newUserId = (int)$conn->lastInsertId();

        $insertPatientStmt = $conn->prepare("
            INSERT INTO patients (user_id, gender, blood_group)
            VALUES (?, 'Other', 'O+')
        ");
        $insertPatientStmt->execute([$newUserId]);

        $conn->commit();

        // Fetch newly created record
        $fetchStmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $fetchStmt->execute([$newUserId]);
        $newUser = $fetchStmt->fetch();

        login_user($newUser, $conn);
        set_flash('success', 'Welcome to MediQueue, ' . htmlspecialchars($newUser['name']) . '! Your account has been registered.');

        echo json_encode([
            'success' => true,
            'redirect' => BASE_URL . '/patient/dashboard.php',
            'message' => 'Account registered and logged in successfully.'
        ]);
        exit;
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error during authentication: ' . $e->getMessage()
    ]);
    exit;
}
