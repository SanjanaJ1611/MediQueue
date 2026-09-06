<?php
/**
 * MediQueue - Authentication & Authorization Guards
 */

require_once __DIR__ . '/../config/database.php';

// Flash message helpers
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// CSRF Token Management
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Check if user is logged in
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

// Get logged in user details
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'         => $_SESSION['user_id'] ?? null,
        'name'       => $_SESSION['user_name'] ?? 'User',
        'email'      => $_SESSION['user_email'] ?? '',
        'role'       => $_SESSION['user_role'] ?? 'patient',
        'phone'      => $_SESSION['user_phone'] ?? '',
        'role_id'    => $_SESSION['role_specific_id'] ?? null, // patient_id or doctor_id
        'dept_name'  => $_SESSION['department_name'] ?? null,
    ];
}

// Guard: Require login
function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access this page.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// Guard: Require specific role(s)
function require_role($roles) {
    require_login();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    $userRole = $_SESSION['user_role'] ?? '';
    if (!in_array($userRole, $roles, true)) {
        set_flash('danger', 'Unauthorized access. You do not have permission to view this section.');
        // Redirect to appropriate dashboard based on their real role
        if ($userRole === 'admin') {
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
        } elseif ($userRole === 'doctor') {
            header('Location: ' . BASE_URL . '/doctor/dashboard.php');
        } elseif ($userRole === 'patient') {
            header('Location: ' . BASE_URL . '/patient/dashboard.php');
        } else {
            header('Location: ' . BASE_URL . '/index.php');
        }
        exit;
    }
}

// Populate session and fetch role-specific identifiers
function login_user($user, $pdo) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_phone'] = $user['phone'] ?? '';

    // Fetch role specific IDs
    if ($user['role'] === 'patient') {
        $stmt = $pdo->prepare("SELECT id, blood_group, emergency_contact FROM patients WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $patient = $stmt->fetch();
        if ($patient) {
            $_SESSION['role_specific_id'] = $patient['id'];
            $_SESSION['patient_id'] = $patient['id'];
        }
    } elseif ($user['role'] === 'doctor') {
        $stmt = $pdo->prepare("
            SELECT d.id, d.department_id, d.room_number, d.specialization, dept.department_name 
            FROM doctors d 
            JOIN departments dept ON d.department_id = dept.id 
            WHERE d.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $doctor = $stmt->fetch();
        if ($doctor) {
            $_SESSION['role_specific_id'] = $doctor['id'];
            $_SESSION['doctor_id'] = $doctor['id'];
            $_SESSION['department_name'] = $doctor['department_name'];
            $_SESSION['room_number'] = $doctor['room_number'];
        }
    } elseif ($user['role'] === 'admin') {
        $_SESSION['role_specific_id'] = $user['id'];
    }
}

// Log out user
function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
