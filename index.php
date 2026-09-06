<?php
/**
 * MediQueue - Public Hospital Landing Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$conn = get_db_connection();

// Fetch Departments
$deptStmt = $conn->query("
    SELECT d.*, COUNT(doc.id) as doctor_count 
    FROM departments d 
    LEFT JOIN doctors doc ON d.id = doc.department_id 
    GROUP BY d.id 
    ORDER BY d.id ASC
");
$departments = $deptStmt->fetchAll();

// Fetch Featured Doctors with availability
$docStmt = $conn->query("
    SELECT d.*, u.name as doctor_name, dept.department_name, dept.icon as dept_icon,
           COALESCE(da.status, 'available') as avail_status,
           da.start_time, da.end_time
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN doctor_availability da ON d.id = da.doctor_id
    WHERE d.status = 'active'
    LIMIT 6
");
$featuredDoctors = $docStmt->fetchAll();

// Fetch overall hospital stats
$patientCount = $conn->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$doctorCount = $conn->query("SELECT COUNT(*) FROM doctors WHERE status = 'active'")->fetchColumn();
$todayQueueCount = $conn->query("SELECT COUNT(*) FROM queues WHERE DATE(joined_at) = CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> – <?= APP_TAGLINE ?></title>
    
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
<body class="bg-white">

    <!-- Top Announcement Bar -->
    <div class="bg-primary text-white py-2 px-3 small text-center fw-medium">
        <i class="fa-solid fa-bell me-2"></i> Live Virtual Queue System is active today! Avoid waiting room congestion.
    </div>

    <!-- Navigation Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-primary fs-4" href="<?= BASE_URL ?>/index.php">
                <div class="brand-icon">
                    <i class="fa-solid fa-hospital-user"></i>
                </div>
                <span><?= APP_NAME ?></span>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 fw-medium">
                    <li class="nav-item"><a class="nav-link active text-primary" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link" href="#departments">Departments</a></li>
                    <li class="nav-item"><a class="nav-link" href="#doctors">Doctors</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                </ul>
                
                <div class="d-flex align-items-center gap-2">
                    <?php if (is_logged_in()): ?>
                        <?php 
                            $dashUrl = BASE_URL . '/patient/dashboard.php';
                            if ($_SESSION['user_role'] === 'doctor') $dashUrl = BASE_URL . '/doctor/dashboard.php';
                            if ($_SESSION['user_role'] === 'admin') $dashUrl = BASE_URL . '/admin/dashboard.php';
                        ?>
                        <a href="<?= $dashUrl ?>" class="btn btn-primary fw-semibold px-4 py-2">
                            <i class="fa-solid fa-gauge me-1"></i> Go to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-primary fw-semibold px-3 py-2">Sign In</a>
                        <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary fw-semibold px-3 py-2">Book Appointment</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section" id="home">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-7">
                    <div class="hero-badge">
                        <i class="fa-solid fa-circle-check text-success"></i> Real-time Queue Tracking Active
                    </div>
                    <h1 class="hero-title mb-4">
                        Smart Hospital Appointments.<br>
                        <span class="text-primary">Less Waiting.</span> Better Care.
                    </h1>
                    <p class="hero-subtitle mb-4 pe-lg-4">
                        Book appointments, track your virtual queue in real time, and stay informed throughout your hospital visit — all from your phone or browser.
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-lg fw-semibold px-4 py-3 shadow-sm">
                            <i class="fa-regular fa-calendar-check me-2"></i> Book Appointment
                        </a>
                        <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-secondary btn-lg fw-semibold px-4 py-3">
                            <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Patient Portal
                        </a>
                    </div>
                    <!-- Quick Stats Pill -->
                    <div class="row g-3 pt-3 border-top">
                        <div class="col-auto">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-4 fw-bold text-dark"><?= $patientCount ?>+</span>
                                <span class="text-muted small">Registered<br>Patients</span>
                            </div>
                        </div>
                        <div class="col-auto border-start ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-4 fw-bold text-dark"><?= $doctorCount ?></span>
                                <span class="text-muted small">Specialist<br>Doctors</span>
                            </div>
                        </div>
                        <div class="col-auto border-start ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-4 fw-bold text-primary"><?= $todayQueueCount ?></span>
                                <span class="text-muted small">Active Virtual<br>Queues Today</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <!-- Interactive Visual Card -->
                    <div class="queue-hero-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-white text-primary fw-bold px-3 py-2">LIVE HOSPITAL QUEUE</span>
                            <span class="d-flex align-items-center gap-2 small">
                                <span class="queue-pulse-dot"></span> Real-Time Updates
                            </span>
                        </div>
                        <div class="text-center my-4">
                            <div class="small text-white-50 text-uppercase fw-semibold mb-1">Current Called Number</div>
                            <div class="queue-hero-number">CAR-101</div>
                            <div class="badge bg-success px-3 py-1 mt-2"><i class="fa-solid fa-bullhorn me-1"></i> Patient in Room 201-A</div>
                        </div>
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 mb-3 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Department:</span>
                                <strong>Cardiology</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Consultant:</span>
                                <strong>Dr. Sarah Jenkins</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Average Waiting:</span>
                                <strong>~15 Mins / Patient</strong>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>/login.php" class="btn btn-light w-100 fw-bold py-2 text-primary">
                            Track Your Live Queue Turn <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- How It Works Section -->
    <section class="py-5 bg-light" id="how-it-works">
        <div class="container py-4">
            <div class="text-center max-w-700 mx-auto mb-5">
                <span class="badge bg-primary-subtle text-primary mb-2 px-3 py-2">STREAMLINED WORKFLOW</span>
                <h2 class="fw-bold">How MediQueue Works</h2>
                <p class="text-muted">A modern 6-step virtual care experience designed to end waiting room crowding.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4 col-sm-6">
                    <div class="feature-step-card h-100">
                        <div class="step-circle">1</div>
                        <h5 class="fw-bold mb-2">Register Online</h5>
                        <p class="text-muted small mb-0">Create your secure patient account in less than a minute with your basic information.</p>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="feature-step-card h-100">
                        <div class="step-circle">2</div>
                        <h5 class="fw-bold mb-2">Choose Doctor</h5>
                        <p class="text-muted small mb-0">Browse specialist doctors, filter by medical department, and check availability badges.</p>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="feature-step-card h-100">
                        <div class="step-circle">3</div>
                        <h5 class="fw-bold mb-2">Book Appointment</h5>
                        <p class="text-muted small mb-0">Select your preferred date and available 30-minute time slot with zero double-booking.</p>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="feature-step-card h-100">
                        <div class="step-circle">4</div>
                        <h5 class="fw-bold mb-2">Join Virtual Queue</h5>
                        <p class="text-muted small mb-0">Receive your instant digital queue ticket (e.g., CAR-102) right upon arrival or online.</p>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="feature-step-card h-100">
                        <div class="step-circle">5</div>
                        <h5 class="fw-bold mb-2">Track Your Turn</h5>
                        <p class="text-muted small mb-0">Watch your queue position count down in real-time with estimated wait minutes.</p>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="feature-step-card h-100">
                        <div class="step-circle">6</div>
                        <h5 class="fw-bold mb-2">Meet Your Doctor</h5>
                        <p class="text-muted small mb-0">Receive a live chime and chime alert when your doctor calls your number into the room.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Medical Departments Section -->
    <section class="py-5" id="departments">
        <div class="container py-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-5">
                <div>
                    <span class="badge bg-primary-subtle text-primary mb-2 px-3 py-2">SPECIALIZED CARE</span>
                    <h2 class="fw-bold">Clinical Departments</h2>
                    <p class="text-muted mb-0">Explore our comprehensive range of specialized medical services.</p>
                </div>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-outline-primary fw-semibold mt-3 mt-md-0">
                    View All Services <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="row g-4">
                <?php foreach ($departments as $dept): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="mq-card h-100 p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="mq-stat-icon primary">
                                <i class="fa-solid <?= htmlspecialchars($dept['icon'] ?: 'fa-stethoscope') ?>"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0"><?= htmlspecialchars($dept['department_name']) ?></h5>
                                <span class="badge bg-light text-muted border"><?= $dept['doctor_count'] ?> Specialists</span>
                            </div>
                        </div>
                        <p class="text-muted small mb-4"><?= htmlspecialchars($dept['description']) ?></p>
                        <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-light border text-primary fw-semibold w-100">
                            Book in <?= htmlspecialchars($dept['department_name']) ?> <i class="fa-solid fa-chevron-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Doctors Showcase Section -->
    <section class="py-5 bg-light" id="doctors">
        <div class="container py-4">
            <div class="text-center max-w-700 mx-auto mb-5">
                <span class="badge bg-primary-subtle text-primary mb-2 px-3 py-2">OUR MEDICAL EXPERTS</span>
                <h2 class="fw-bold">Meet Our Specialist Doctors</h2>
                <p class="text-muted">Experienced, certified healthcare professionals ready for consultations.</p>
            </div>

            <div class="row g-4">
                <?php foreach ($featuredDoctors as $doc): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="mq-card h-100 p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="user-avatar" style="width: 52px; height: 52px; font-size: 1.25rem;">
                                <?= strtoupper(substr($doc['doctor_name'], 4, 1)) ?>
                            </div>
                            <div>
                                <?= get_status_badge($doc['avail_status']) ?>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($doc['doctor_name']) ?></h5>
                        <div class="text-primary small fw-semibold mb-2">
                            <i class="fa-solid fa-hospital-user me-1"></i> <?= htmlspecialchars($doc['department_name']) ?>
                        </div>
                        <p class="text-muted small mb-2"><?= htmlspecialchars($doc['specialization']) ?></p>
                        <div class="small text-secondary mb-3">
                            <i class="fa-solid fa-graduation-cap me-1"></i> <?= htmlspecialchars($doc['qualification']) ?>
                        </div>
                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small d-block">Consultation Fee</span>
                                <span class="fw-bold text-dark">$<?= number_format($doc['consultation_fee'], 2) ?></span>
                            </div>
                            <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-primary px-3 fw-semibold">
                                Book Visit
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Core Features Section -->
    <section class="py-5" id="features">
        <div class="container py-4">
            <div class="text-center max-w-700 mx-auto mb-5">
                <span class="badge bg-primary-subtle text-primary mb-2 px-3 py-2">PLATFORM CAPABILITIES</span>
                <h2 class="fw-bold">Why Choose MediQueue?</h2>
                <p class="text-muted">Designed to optimize healthcare delivery for patients, physicians, and administrative staff.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="mq-card h-100">
                        <div class="mq-stat-icon primary mb-3">
                            <i class="fa-solid fa-list-ol"></i>
                        </div>
                        <h5 class="fw-bold">Virtual Queue Management</h5>
                        <p class="text-muted small mb-0">Eliminate crowded waiting rooms. Patients join queues digitally and monitor wait times and positions from anywhere.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mq-card h-100">
                        <div class="mq-stat-icon success mb-3">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <h5 class="fw-bold">Real-Time Availability</h5>
                        <p class="text-muted small mb-0">Clear status indicators show whether doctors are Available, Busy, or Unavailable with dynamic 30-minute booking slots.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mq-card h-100">
                        <div class="mq-stat-icon warning mb-3">
                            <i class="fa-solid fa-id-card"></i>
                        </div>
                        <h5 class="fw-bold">Visitor Pass Tracking</h5>
                        <p class="text-muted small mb-0">Hospital security and staff can register, monitor, and check-in/out visitors accompanying patients seamlessly.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white pt-5 pb-4">
        <div class="container">
            <div class="row gy-4 pb-4 border-bottom border-secondary">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="brand-icon">
                            <i class="fa-solid fa-hospital-user"></i>
                        </div>
                        <span class="fs-4 fw-bold text-white"><?= APP_NAME ?></span>
                    </div>
                    <p class="text-white-50 small mb-3">
                        MediQueue is an enterprise-grade hospital appointment and virtual queue management system built to improve patient experiences and streamline clinic operations.
                    </p>
                    <div class="text-white-50 small">
                        <i class="fa-solid fa-location-dot me-2 text-primary"></i> 100 Medical Center Blvd, Suite 500<br>
                        <i class="fa-solid fa-phone me-2 text-primary"></i> +1 (555) 019-8000
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h6 class="text-white fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled small text-white-50 lh-lg">
                        <li><a href="#home" class="text-white-50 text-decoration-none">Home</a></li>
                        <li><a href="#how-it-works" class="text-white-50 text-decoration-none">How It Works</a></li>
                        <li><a href="#departments" class="text-white-50 text-decoration-none">Departments</a></li>
                        <li><a href="#doctors" class="text-white-50 text-decoration-none">Doctors</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h6 class="text-white fw-bold mb-3">Portals</h6>
                    <ul class="list-unstyled small text-white-50 lh-lg">
                        <li><a href="<?= BASE_URL ?>/login.php" class="text-white-50 text-decoration-none"><i class="fa-solid fa-arrow-right me-1"></i> Patient Portal</a></li>
                        <li><a href="<?= BASE_URL ?>/login.php" class="text-white-50 text-decoration-none"><i class="fa-solid fa-arrow-right me-1"></i> Doctor Console</a></li>
                        <li><a href="<?= BASE_URL ?>/login.php" class="text-white-50 text-decoration-none"><i class="fa-solid fa-arrow-right me-1"></i> Staff / Admin Center</a></li>
                        <li><a href="<?= BASE_URL ?>/register.php" class="text-white-50 text-decoration-none"><i class="fa-solid fa-arrow-right me-1"></i> New Registration</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h6 class="text-white fw-bold mb-3">Emergency Care</h6>
                    <div class="p-3 rounded bg-secondary bg-opacity-25 border border-secondary text-white-50 small">
                        <strong class="text-white d-block mb-1"><i class="fa-solid fa-truck-medical text-danger me-1"></i> 24/7 Emergency Helpline</strong>
                        Dial <strong>911</strong> or contact our hospital emergency desk at <strong>(555) 019-9911</strong>.
                    </div>
                </div>
            </div>
            <div class="pt-4 d-flex flex-column flex-sm-row justify-content-between align-items-center small text-white-50">
                <div>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Developed for professional hospital environments.</div>
                <div class="mt-2 mt-sm-0">Secure • Role-Based • Real-Time Queueing</div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/script.js"></script>
</body>
</html>
