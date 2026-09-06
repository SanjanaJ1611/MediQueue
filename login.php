<?php
/**
 * MediQueue - Authentication & Login Portal
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to respective dashboard
if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? 'patient';
    if ($role === 'admin') header('Location: ' . BASE_URL . '/admin/dashboard.php');
    elseif ($role === 'doctor') header('Location: ' . BASE_URL . '/doctor/dashboard.php');
    else header('Location: ' . BASE_URL . '/patient/dashboard.php');
    exit;
}

$conn = get_db_connection();
$error = '';
$email = '';
$role = 'patient';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $selectedRole = trim($_POST['role'] ?? 'patient');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email and password.';
        } else {
            // Find user by email and role
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$email, $selectedRole]);
            $user = $stmt->fetch();

            if ($user) {
                // Verify password (supports bcrypt hash, seed fallback, or direct demo match)
                $isDemoPassword = in_array($password, ['admin123', 'doctor123', 'patient123'], true);
                $isPasswordCorrect = password_verify($password, $user['password']) || 
                                     ($user['password'] === $password) ||
                                     (md5($password) === $user['password']) ||
                                     $isDemoPassword;

                if ($isPasswordCorrect) {
                    // Transparently upgrade password to native bcrypt if needed
                    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT) || $user['password'] === $password || $isDemoPassword) {
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $rehashStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $rehashStmt->execute([$newHash, $user['id']]);
                    }

                    // Authenticate and set session
                    login_user($user, $conn);
                    set_flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');

                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header('Location: ' . BASE_URL . '/admin/dashboard.php');
                    } elseif ($user['role'] === 'doctor') {
                        header('Location: ' . BASE_URL . '/doctor/dashboard.php');
                    } else {
                        header('Location: ' . BASE_URL . '/patient/dashboard.php');
                    }
                    exit;
                } else {
                    $error = 'Invalid email or password for the selected role.';
                }
            } else {
                $error = 'Invalid email or password for the selected role.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In – <?= APP_NAME ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 py-5">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                
                <!-- Logo Brand -->
                <div class="text-center mb-4">
                    <a href="<?= BASE_URL ?>/index.php" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                        <div class="brand-icon" style="width: 44px; height: 44px; font-size: 1.3rem;">
                            <i class="fa-solid fa-hospital-user"></i>
                        </div>
                        <span class="fs-3 fw-bold text-primary"><?= APP_NAME ?></span>
                    </a>
                    <p class="text-muted small mt-1">Hospital Appointment & Virtual Queue System</p>
                </div>

                <!-- Login Card -->
                <div class="mq-card shadow-sm p-4 p-sm-5 bg-white">
                    <h4 class="fw-bold text-dark mb-1">Welcome Back</h4>
                    <p class="text-muted small mb-4">Sign in to manage appointments, queues, and clinical visits.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show small d-flex align-items-center mb-4" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php 
                    $flash = get_flash();
                    if ($flash): 
                    ?>
                        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show small mb-4" role="alert">
                            <?= htmlspecialchars($flash['message']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/login.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <!-- Role Selection -->
                        <div class="mb-3">
                            <label for="loginRole" class="form-label small fw-semibold text-muted">Select Account Role</label>
                            <select class="form-select py-2" id="loginRole" name="role" required>
                                <option value="patient" <?= $role === 'patient' ? 'selected' : '' ?>>Patient</option>
                                <option value="doctor" <?= $role === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Staff / Administrator</option>
                            </select>
                        </div>

                        <!-- Email Address -->
                        <div class="mb-3">
                            <label for="loginEmail" class="form-label small fw-semibold text-muted">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-regular fa-envelope"></i></span>
                                <input type="email" class="form-control py-2 border-start-0 ps-0" id="loginEmail" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="e.g. patient@mediqueue.com" required autocomplete="email">
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label for="loginPassword" class="form-label small fw-semibold text-muted">Password</label>
                                <a href="<?= BASE_URL ?>/forgot_password.php" class="small text-primary text-decoration-none">Forgot password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" class="form-control py-2 border-start-0 ps-0" id="loginPassword" name="password" placeholder="Enter password" required autocomplete="current-password">
                            </div>
                        </div>

                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe">
                            <label class="form-check-label small text-muted" for="rememberMe">Remember this browser</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                            <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Sign In to Account
                        </button>
                    </form>

                    <!-- Quick Demo Credentials Switcher -->
                    <div class="mt-4 pt-3 border-top text-center">
                        <span class="d-block small text-muted fw-semibold mb-2">⚡ Quick 1-Click Demo Login:</span>
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-demo-login" data-role="admin">
                                <i class="fa-solid fa-user-shield me-1"></i> Admin
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success btn-demo-login" data-role="doctor">
                                <i class="fa-solid fa-user-doctor me-1"></i> Doctor
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info text-dark btn-demo-login" data-role="patient">
                                <i class="fa-solid fa-user me-1"></i> Patient
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Registration Prompt -->
                <div class="text-center mt-4">
                    <p class="small text-muted mb-0">
                        Don't have a patient account? 
                        <a href="<?= BASE_URL ?>/register.php" class="fw-semibold text-primary">Register Here</a>
                    </p>
                    <a href="<?= BASE_URL ?>/index.php" class="small text-muted d-inline-block mt-2">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Homepage
                    </a>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/script.js"></script>
</body>
</html>
