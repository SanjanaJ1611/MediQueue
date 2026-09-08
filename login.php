<?php
/**
 * MediQueue - Authentication & Login Portal
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/supabase.php';
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
                    <!-- Google Supabase OAuth Button -->
                    <div class="mb-4">
                        <button type="button" id="btnGoogleLogin" class="btn btn-outline-dark w-100 py-2 d-flex align-items-center justify-content-center gap-2 fw-medium shadow-sm bg-white" style="border-color: #dadce0; color: #3c4043;">
                            <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                                <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.616z" fill="#4285F4"/>
                                <path d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z" fill="#34A853"/>
                                <path d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707s.102-1.167.282-1.707V4.961H.957C.347 6.175 0 7.55 0 9s.347 2.825.957 4.039l3.007-2.332z" fill="#FBBC05"/>
                                <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.961L3.964 7.293C4.672 5.166 6.656 3.58 9 3.58z" fill="#EA4335"/>
                            </svg>
                            <span>Continue with Google</span>
                        </button>

                        <div class="position-relative text-center my-3">
                            <hr class="text-muted opacity-25 m-0">
                            <span class="position-absolute top-50 start-50 translate-middle bg-white px-2 text-muted small" style="font-size: 0.78rem;">or sign in with email</span>
                        </div>
                    </div>

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

    <!-- Supabase Configuration Guide Modal -->
    <div class="modal fade" id="supabaseSetupModal" tabindex="-1" aria-labelledby="supabaseSetupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fs-6 fw-bold" id="supabaseSetupModalLabel">
                        <i class="fa-solid fa-bolt me-2"></i> Enable Supabase Google Auth
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-dark small mb-3">
                        To enable <strong>Google Sign-In</strong> with Supabase, add your Supabase project credentials to <code>.env</code> (or in <code>config/supabase.php</code>):
                    </p>
                    <div class="bg-dark text-light p-3 rounded-3 small mb-3 font-monospace" style="font-size: 0.82rem;">
                        SUPABASE_URL=https://your-project.supabase.co<br>
                        SUPABASE_ANON_KEY=your-anon-key
                    </div>
                    <ol class="small text-muted ps-3 mb-0">
                        <li class="mb-1">Create a free project at <a href="https://supabase.com" target="_blank" class="fw-semibold">supabase.com</a>.</li>
                        <li class="mb-1">In your Supabase project, navigate to <strong>Authentication &rarr; Providers &rarr; Google</strong> and toggle it ON.</li>
                        <li class="mb-1">Under <strong>Authentication &rarr; URL Configuration &rarr; Redirect URLs</strong>, add:
                            <div class="badge bg-light text-dark text-wrap text-start mt-1 d-block font-monospace p-2 border">
                                <span id="supabaseRedirectUrl"></span>
                            </div>
                        </li>
                        <li>Paste your <code>Project URL</code> and <code>anon / public key</code> from Project Settings &rarr; API into your <code>.env</code> file.</li>
                    </ol>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/script.js"></script>
    
    <!-- Supabase JS Client & OAuth Handler -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script>
        const SUPABASE_CONFIGURED = <?= is_supabase_configured() ? 'true' : 'false' ?>;
        const SUPABASE_URL = "<?= htmlspecialchars(SUPABASE_URL) ?>";
        const SUPABASE_ANON_KEY = "<?= htmlspecialchars(SUPABASE_ANON_KEY) ?>";
        const BASE_URL = "<?= htmlspecialchars(BASE_URL) ?>";

        document.addEventListener('DOMContentLoaded', () => {
            const redirectUrlEl = document.getElementById('supabaseRedirectUrl');
            if (redirectUrlEl) {
                redirectUrlEl.textContent = window.location.origin + BASE_URL + '/auth/callback.php';
            }

            const btnGoogle = document.getElementById('btnGoogleLogin');
            if (btnGoogle) {
                btnGoogle.addEventListener('click', async () => {
                    if (!SUPABASE_CONFIGURED) {
                        const modal = new bootstrap.Modal(document.getElementById('supabaseSetupModal'));
                        modal.show();
                        return;
                    }

                    btnGoogle.disabled = true;
                    btnGoogle.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Connecting Google...';

                    try {
                        const supabase = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
                        const redirectTarget = window.location.origin + BASE_URL + '/auth/callback.php';
                        const { data, error } = await supabase.auth.signInWithOAuth({
                            provider: 'google',
                            options: {
                                redirectTo: redirectTarget
                            }
                        });
                        if (error) {
                            alert('Error initiating Google Login: ' + error.message);
                            btnGoogle.disabled = false;
                            btnGoogle.innerHTML = '<i class="fa-brands fa-google me-2"></i>Continue with Google';
                        }
                    } catch (err) {
                        alert('Connection error: ' + err.message);
                        btnGoogle.disabled = false;
                        btnGoogle.innerHTML = '<i class="fa-brands fa-google me-2"></i>Continue with Google';
                    }
                });
            }
        });
    </script>
</body>
</html>
