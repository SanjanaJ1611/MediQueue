<?php
/**
 * MediQueue - Forgot Password UI
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$conn = get_db_connection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } else {
        // Look up email
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $message = "A password recovery instruction link has been simulated for {$email}. Please check your inbox or contact the hospital IT desk.";
        } else {
            // For security, do not expose whether email exists or not
            $message = "If an account matches {$email}, password recovery instructions have been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – <?= APP_NAME ?></title>
    
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
            <div class="col-md-7 col-lg-5">
                
                <div class="text-center mb-4">
                    <a href="<?= BASE_URL ?>/index.php" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                        <div class="brand-icon" style="width: 44px; height: 44px; font-size: 1.3rem;">
                            <i class="fa-solid fa-hospital-user"></i>
                        </div>
                        <span class="fs-3 fw-bold text-primary"><?= APP_NAME ?></span>
                    </a>
                    <p class="text-muted small mt-1">Password Recovery Center</p>
                </div>

                <div class="mq-card shadow-sm p-4 p-sm-5 bg-white">
                    <h4 class="fw-bold text-dark mb-1">Forgot Password</h4>
                    <p class="text-muted small mb-4">Enter your registered email and we'll help you regain access.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger small mb-4"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($message): ?>
                        <div class="alert alert-success small mb-4">
                            <i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/forgot_password.php" method="POST">
                        <div class="mb-4">
                            <label for="resetEmail" class="form-label small fw-semibold text-muted">Registered Email</label>
                            <input type="email" class="form-control py-2" id="resetEmail" name="email" placeholder="e.g. yourname@domain.com" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="fa-regular fa-paper-plane me-1"></i> Send Recovery Instructions
                        </button>
                    </form>
                </div>

                <div class="text-center mt-4">
                    <a href="<?= BASE_URL ?>/login.php" class="small text-muted">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Sign In
                    </a>
                </div>

            </div>
        </div>
    </div>

</body>
</html>
