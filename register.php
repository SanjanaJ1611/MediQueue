<?php
/**
 * MediQueue - Patient Registration
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/patient/dashboard.php');
    exit;
}

$conn = get_db_connection();
$error = '';
$form = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'dob' => '',
    'gender' => 'Male',
    'blood_group' => 'O+',
    'address' => '',
    'emergency_contact' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $form['name'] = trim($_POST['name'] ?? '');
        $form['email'] = trim($_POST['email'] ?? '');
        $form['phone'] = trim($_POST['phone'] ?? '');
        $form['dob'] = trim($_POST['dob'] ?? '');
        $form['gender'] = trim($_POST['gender'] ?? 'Male');
        $form['blood_group'] = trim($_POST['blood_group'] ?? 'O+');
        $form['address'] = trim($_POST['address'] ?? '');
        $form['emergency_contact'] = trim($_POST['emergency_contact'] ?? '');
        
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($form['name']) || empty($form['email']) || empty($password)) {
            $error = 'Please fill in all mandatory fields.';
        } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match. Please verify.';
        } else {
            // Check if email already registered
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->execute([$form['email']]);
            if ($checkStmt->fetch()) {
                $error = 'An account with this email address is already registered.';
            } else {
                try {
                    $conn->beginTransaction();

                    // 1. Insert into users
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $uStmt = $conn->prepare("
                        INSERT INTO users (name, email, password, role, phone, status, created_at)
                        VALUES (?, ?, ?, 'patient', ?, 'active', NOW())
                    ");
                    $uStmt->execute([$form['name'], $form['email'], $hashedPassword, $form['phone']]);
                    $userId = $conn->lastInsertId();

                    // 2. Insert into patients
                    $pStmt = $conn->prepare("
                        INSERT INTO patients (user_id, date_of_birth, gender, blood_group, address, emergency_contact, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $pStmt->execute([
                        $userId,
                        !empty($form['dob']) ? $form['dob'] : null,
                        $form['gender'],
                        $form['blood_group'],
                        $form['address'],
                        $form['emergency_contact']
                    ]);

                    // 3. Welcome notification
                    create_notification(
                        $conn,
                        $userId,
                        'Welcome to MediQueue!',
                        'Your patient account was successfully created. You can now book appointments and join virtual queues.',
                        'success'
                    );

                    $conn->commit();

                    // Log in immediately
                    $newUser = [
                        'id'    => $userId,
                        'name'  => $form['name'],
                        'email' => $form['email'],
                        'role'  => 'patient',
                        'phone' => $form['phone']
                    ];
                    login_user($newUser, $conn);
                    set_flash('success', 'Registration successful! Welcome to MediQueue.');
                    header('Location: ' . BASE_URL . '/patient/dashboard.php');
                    exit;

                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = 'Registration failed due to a database error. Please try again.';
                }
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
    <title>Patient Registration – <?= APP_NAME ?></title>
    
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
<body class="bg-light py-5">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                
                <!-- Logo Brand -->
                <div class="text-center mb-4">
                    <a href="<?= BASE_URL ?>/index.php" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                        <div class="brand-icon" style="width: 44px; height: 44px; font-size: 1.3rem;">
                            <i class="fa-solid fa-hospital-user"></i>
                        </div>
                        <span class="fs-3 fw-bold text-primary"><?= APP_NAME ?></span>
                    </a>
                    <p class="text-muted small mt-1">Create your Patient Account</p>
                </div>

                <!-- Registration Card -->
                <div class="mq-card shadow-sm p-4 p-md-5 bg-white">
                    <h4 class="fw-bold text-dark mb-1">New Patient Registration</h4>
                    <p class="text-muted small mb-4">Join MediQueue to book appointments and track your turn in virtual queues.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show small d-flex align-items-center mb-4" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Google Supabase OAuth Button -->
                    <div class="mb-4">
                        <button type="button" id="btnGoogleRegister" class="btn btn-outline-dark w-100 py-2 d-flex align-items-center justify-content-center gap-2 fw-medium shadow-sm bg-white" style="border-color: #dadce0; color: #3c4043;">
                            <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                                <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.616z" fill="#4285F4"/>
                                <path d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z" fill="#34A853"/>
                                <path d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707s.102-1.167.282-1.707V4.961H.957C.347 6.175 0 7.55 0 9s.347 2.825.957 4.039l3.007-2.332z" fill="#FBBC05"/>
                                <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.961L3.964 7.293C4.672 5.166 6.656 3.58 9 3.58z" fill="#EA4335"/>
                            </svg>
                            <span>Sign up with Google (Fast 1-Click)</span>
                        </button>

                        <div class="position-relative text-center my-3">
                            <hr class="text-muted opacity-25 m-0">
                            <span class="position-absolute top-50 start-50 translate-middle bg-white px-2 text-muted small" style="font-size: 0.78rem;">or register with manual details</span>
                        </div>
                    </div>

                    <form action="<?= BASE_URL ?>/register.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label for="regName" class="form-label small fw-semibold text-muted">Full Legal Name *</label>
                                <input type="text" class="form-control" id="regName" name="name" value="<?= htmlspecialchars($form['name']) ?>" placeholder="e.g. Eleanor Vance" required>
                            </div>

                            <div class="col-md-6">
                                <label for="regEmail" class="form-label small fw-semibold text-muted">Email Address *</label>
                                <input type="email" class="form-control" id="regEmail" name="email" value="<?= htmlspecialchars($form['email']) ?>" placeholder="name@domain.com" required>
                            </div>

                            <div class="col-md-6">
                                <label for="regPhone" class="form-label small fw-semibold text-muted">Mobile Phone *</label>
                                <input type="tel" class="form-control" id="regPhone" name="phone" value="<?= htmlspecialchars($form['phone']) ?>" placeholder="+1 (555) 000-0000" required>
                            </div>

                            <div class="col-md-4">
                                <label for="regDob" class="form-label small fw-semibold text-muted">Date of Birth</label>
                                <input type="date" class="form-control" id="regDob" name="dob" value="<?= htmlspecialchars($form['dob']) ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="regGender" class="form-label small fw-semibold text-muted">Gender</label>
                                <select class="form-select" id="regGender" name="gender">
                                    <option value="Male" <?= $form['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $form['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= $form['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="regBlood" class="form-label small fw-semibold text-muted">Blood Group</label>
                                <select class="form-select" id="regBlood" name="blood_group">
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                        <option value="<?= $bg ?>" <?= $form['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label for="regAddress" class="form-label small fw-semibold text-muted">Residential Address</label>
                                <textarea class="form-control" id="regAddress" name="address" rows="2" placeholder="Street, City, State, ZIP code"><?= htmlspecialchars($form['address']) ?></textarea>
                            </div>

                            <div class="col-md-12">
                                <label for="regEmergency" class="form-label small fw-semibold text-muted">Emergency Contact Number</label>
                                <input type="tel" class="form-control" id="regEmergency" name="emergency_contact" value="<?= htmlspecialchars($form['emergency_contact']) ?>" placeholder="Family member or guardian phone">
                            </div>

                            <div class="col-md-6">
                                <label for="regPassword" class="form-label small fw-semibold text-muted">Account Password *</label>
                                <input type="password" class="form-control" id="regPassword" name="password" placeholder="At least 6 characters" required>
                            </div>

                            <div class="col-md-6">
                                <label for="regConfirmPassword" class="form-label small fw-semibold text-muted">Confirm Password *</label>
                                <input type="password" class="form-control" id="regConfirmPassword" name="confirm_password" placeholder="Re-type password" required>
                            </div>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="termsCheck" required>
                            <label class="form-check-label small text-muted" for="termsCheck">
                                I agree to the MediQueue hospital service terms and privacy guidelines.
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                            <i class="fa-solid fa-user-plus me-1"></i> Complete Registration
                        </button>
                    </form>
                </div>

                <!-- Footer Sign In Link -->
                <div class="text-center mt-4">
                    <p class="small text-muted mb-0">
                        Already have an account? 
                        <a href="<?= BASE_URL ?>/login.php" class="fw-semibold text-primary">Sign In Here</a>
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
                        To enable <strong>Google Sign-Up</strong> with Supabase, add your Supabase project credentials to <code>.env</code> (or in <code>config/supabase.php</code>):
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

            const btnGoogle = document.getElementById('btnGoogleRegister');
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
                            alert('Error initiating Google Registration: ' + error.message);
                            btnGoogle.disabled = false;
                            btnGoogle.innerHTML = '<i class="fa-brands fa-google me-2"></i>Sign up with Google (Fast 1-Click)';
                        }
                    } catch (err) {
                        alert('Connection error: ' + err.message);
                        btnGoogle.disabled = false;
                        btnGoogle.innerHTML = '<i class="fa-brands fa-google me-2"></i>Sign up with Google (Fast 1-Click)';
                    }
                });
            }
        });
    </script>
</body>
</html>
