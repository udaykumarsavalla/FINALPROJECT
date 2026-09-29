<?php
/**
 * CarePulse AI - Intelligent Telehealth & Smart Hospital Platform
 * Master Landing Page & Primary Navigation Hub
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Intelligent Telehealth & Smart Hospital Platform";
$activeMenu = "home";

// Fetch departments for showcase
$departments = Database::fetchAll("SELECT * FROM departments ORDER BY id ASC LIMIT 8");

// Fetch featured top rated doctors
$featuredDoctors = Database::fetchAll("
    SELECT d.*, u.name, u.avatar, dep.name as department_name, dep.icon as department_icon
    FROM doctor_profiles d
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON d.department_id = dep.id
    WHERE u.status = 'active'
    ORDER BY d.rating DESC LIMIT 4
");

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section with AI Symptom Query Banner -->
<section class="py-5 position-relative overflow-hidden">
    <div class="container px-lg-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-teal-subtle text-teal fw-bold fs-xs mb-3 border border-teal-subtle">
                    <span class="pulse-indicator"></span> Next-Generation Telemedicine Platform
                </div>
                <h1 class="display-4 fw-extrabold mb-3 lh-sm">
                    Intelligent Healthcare Powered by <span class="brand-ai">Predictive AI</span>
                </h1>
                <p class="lead text-secondary mb-4">
                    Experience seamless online video consultations, real-time wait queue tracking, automated medication schedules, and instant clinical department triage via Scikit-learn AI.
                </p>

                <!-- Quick AI Symptom Search Box -->
                <div class="glass-card p-3 p-md-4 mb-4 shadow-lg border border-teal-subtle">
                    <label class="form-label fw-bold text-teal d-flex align-items-center gap-2">
                        <i class="bi bi-robot fs-5"></i> Ask CarePulse AI Symptom Checker
                    </label>
                    <form action="<?= base_url('patient/symptom_checker.php') ?>" method="GET" class="d-flex flex-column flex-sm-row gap-2">
                        <div class="input-group">
                            <span class="input-group-text bg-body border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="symptoms" class="form-control border-start-0 py-3" placeholder="e.g., sharp chest pain, headache, dizzy, skin rash..." required>
                        </div>
                        <button type="submit" class="btn btn-teal px-4 py-3 fw-bold text-white d-flex align-items-center justify-content-center gap-2 text-nowrap">
                            <span>Analyze</span> <i class="bi bi-arrow-right"></i>
                        </button>
                    </form>
                    <div class="d-flex flex-wrap gap-2 mt-2 pt-1">
                        <span class="text-secondary fs-xs">Popular Checks:</span>
                        <a href="<?= base_url('patient/symptom_checker.php?symptoms=severe+headache+and+light+sensitivity') ?>" class="badge rounded-pill bg-body-secondary text-secondary text-decoration-none">Migraine</a>
                        <a href="<?= base_url('patient/symptom_checker.php?symptoms=chest+tightness+and+palpitations') ?>" class="badge rounded-pill bg-body-secondary text-secondary text-decoration-none">Chest Pressure</a>
                        <a href="<?= base_url('patient/symptom_checker.php?symptoms=knee+joint+pain+swelling') ?>" class="badge rounded-pill bg-body-secondary text-secondary text-decoration-none">Joint Pain</a>
                        <a href="<?= base_url('patient/symptom_checker.php?symptoms=red+itchy+skin+rash') ?>" class="badge rounded-pill bg-body-secondary text-secondary text-decoration-none">Skin Allergy</a>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3">
                    <?php if (Auth::isLoggedIn()): ?>
                        <a href="<?= base_url(Auth::getDashboardUrl()) ?>" class="btn btn-teal btn-lg px-4 py-3 fw-bold rounded-pill text-white shadow">
                            <i class="bi bi-speedometer2 me-2"></i> Open <?= ucfirst(Auth::currentUserRole()) ?> Portal
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('patient/book_appointment.php') ?>" class="btn btn-teal btn-lg px-4 py-3 fw-bold rounded-pill text-white shadow">
                            <i class="bi bi-calendar2-check me-2"></i> Book Appointment
                        </a>
                        <a href="<?= base_url('telemedicine/index.php') ?>" class="btn btn-outline-primary btn-lg px-4 py-3 fw-bold rounded-pill">
                            <i class="bi bi-camera-video me-2"></i> Online Consultation Hub
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Interactive Live Telemedicine Card -->
                <div class="glass-card p-4 shadow-lg position-relative border-0">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="pulse-indicator"></span>
                            <span class="fw-bold fs-sm">CarePulse Telehealth Hub</span>
                        </div>
                        <span class="badge bg-success-subtle text-success">Live Consultations</span>
                    </div>

                    <div class="p-3 rounded-3 bg-body-secondary mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-secondary small">Smart Queue Status</span>
                            <span class="badge bg-teal-subtle text-teal">Token #1 Serving</span>
                        </div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar bg-teal" style="width: 75%"></div>
                        </div>
                        <div class="d-flex justify-content-between text-secondary fs-xs">
                            <span>Estimated Wait Time: <strong>12 mins</strong></span>
                            <span>Doctor: <strong>Dr. Sharma</strong></span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-body-tertiary text-center border">
                                <i class="bi bi-camera-video-fill text-primary fs-3"></i>
                                <h6 class="fw-bold mt-2 mb-0">HD Video OPD</h6>
                                <p class="text-secondary fs-xs mb-0">Browser WebRTC</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-body-tertiary text-center border">
                                <i class="bi bi-file-earmark-medical-fill text-teal fs-3"></i>
                                <h6 class="fw-bold mt-2 mb-0">Digital Rx Vault</h6>
                                <p class="text-secondary fs-xs mb-0">Instant PDF Rx</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-body-tertiary text-center border">
                                <i class="bi bi-credit-card-2-front-fill text-warning fs-3"></i>
                                <h6 class="fw-bold mt-2 mb-0">Fast UPI / Card</h6>
                                <p class="text-secondary fs-xs mb-0">Verified Receipts</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-body-tertiary text-center border">
                                <i class="bi bi-alarm-fill text-success fs-3"></i>
                                <h6 class="fw-bold mt-2 mb-0">Med Reminders</h6>
                                <p class="text-secondary fs-xs mb-0">WhatsApp / SMS / Mail</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MASTER NAVIGATION TILES (6 Core Portal Destinations) -->
<section class="py-5 bg-body-secondary border-top border-bottom">
    <div class="container px-lg-5">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-2">Master Navigation</span>
            <h2 class="display-6 fw-bold">Platform Command Center</h2>
            <p class="text-secondary mb-0">Direct 1-click access to all six core portals and medical services.</p>
        </div>

        <div class="row g-4">
            <!-- 1. Patient Portal -->
            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100 d-flex flex-column border border-teal-subtle">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="icon-box bg-teal-subtle text-teal rounded-3 p-3">
                            <i class="bi bi-person-heart fs-3"></i>
                        </div>
                        <span class="badge bg-teal-subtle text-teal">Portal 1</span>
                    </div>
                    <h5 class="fw-bold mb-2">Patient Portal</h5>
                    <p class="text-secondary small mb-4 flex-grow-1">Access scheduled consultations, medical record vaults, active medication reminders, and token status.</p>
                    <a href="<?= base_url(Auth::isLoggedIn() && Auth::currentUserRole() === 'patient' ? 'patient/index.php' : 'login.php?quick=patient') ?>" class="btn btn-teal text-white w-100 rounded-pill fw-semibold py-2">
                        Open Patient Portal <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- 2. AI Symptom Checker -->
            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100 d-flex flex-column border border-warning-subtle">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="icon-box bg-warning-subtle text-warning rounded-3 p-3">
                            <i class="bi bi-robot fs-3"></i>
                        </div>
                        <span class="badge bg-warning-subtle text-dark">Portal 2</span>
                    </div>
                    <h5 class="fw-bold mb-2">AI Symptom Checker</h5>
                    <p class="text-secondary small mb-4 flex-grow-1">Enter clinical symptoms to obtain ML-based department routing, urgency scoring, and recommended doctors.</p>
                    <a href="<?= base_url('patient/symptom_checker.php') ?>" class="btn btn-warning text-dark w-100 rounded-pill fw-bold py-2">
                        Launch Symptom AI <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- 3. Book Appointment -->
            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100 d-flex flex-column border border-success-subtle">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="icon-box bg-success-subtle text-success rounded-3 p-3">
                            <i class="bi bi-calendar2-check-fill fs-3"></i>
                        </div>
                        <span class="badge bg-success-subtle text-success">Portal 3</span>
                    </div>
                    <h5 class="fw-bold mb-2">Book Appointment</h5>
                    <p class="text-secondary small mb-4 flex-grow-1">ACID transactional booking for in-hospital visits or online video calls with instant slot conflict locks.</p>
                    <a href="<?= base_url('patient/book_appointment.php') ?>" class="btn btn-success text-white w-100 rounded-pill fw-semibold py-2">
                        Book Appointment Now <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- 4. Online Consultation (Telemedicine Hub) -->
            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100 d-flex flex-column border border-primary-subtle">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="icon-box bg-primary-subtle text-primary rounded-3 p-3">
                            <i class="bi bi-camera-video-fill fs-3"></i>
                        </div>
                        <span class="badge bg-primary-subtle text-primary">Portal 4</span>
                    </div>
                    <h5 class="fw-bold mb-2">Online Consultation</h5>
                    <p class="text-secondary small mb-4 flex-grow-1">WebRTC video consultation suite with camera/microphone controls, clinical notes, and digital prescription issuance.</p>
                    <a href="<?= base_url('telemedicine/index.php') ?>" class="btn btn-primary text-white w-100 rounded-pill fw-semibold py-2">
                        Enter Telemedicine Suite <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- 5. Doctor Login -->
            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100 d-flex flex-column border border-info-subtle">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="icon-box bg-info-subtle text-info rounded-3 p-3">
                            <i class="bi bi-clipboard2-pulse-fill fs-3"></i>
                        </div>
                        <span class="badge bg-info-subtle text-info">Portal 5</span>
                    </div>
                    <h5 class="fw-bold mb-2">Doctor Login</h5>
                    <p class="text-secondary small mb-4 flex-grow-1">Physician clinical portal to manage queues, conduct teleconsultations, and issue signed digital prescriptions.</p>
                    <a href="<?= base_url(Auth::isLoggedIn() && Auth::currentUserRole() === 'doctor' ? 'doctor/index.php' : 'login.php?quick=doctor') ?>" class="btn btn-outline-info text-body w-100 rounded-pill fw-semibold py-2">
                        Doctor Sign-in <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- 6. Admin Dashboard -->
            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100 d-flex flex-column border border-dark-subtle">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="icon-box bg-dark-subtle text-dark rounded-3 p-3">
                            <i class="bi bi-speedometer2 fs-3"></i>
                        </div>
                        <span class="badge bg-dark-subtle text-dark">Portal 6</span>
                    </div>
                    <h5 class="fw-bold mb-2">Admin Dashboard</h5>
                    <p class="text-secondary small mb-4 flex-grow-1">Machine Learning OPD inflow forecasting, cross-departmental analytics, doctor workloads, and financial ledger.</p>
                    <a href="<?= base_url(Auth::isLoggedIn() && Auth::currentUserRole() === 'admin' ? 'admin/index.php' : 'login.php?quick=admin') ?>" class="btn btn-dark text-white w-100 rounded-pill fw-semibold py-2">
                        Admin Command Center <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Core Features Grid -->
<section class="py-5">
    <div class="container px-lg-5">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="text-teal fw-bold text-uppercase fs-xs">Enterprise Healthcare Features</span>
            <h2 class="display-6 fw-bold mt-1">Everything You Need for Modern Telehealth</h2>
            <p class="text-secondary">Designed for hospitals, physicians, and patients with bank-grade security and machine intelligence.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100">
                    <div class="icon-box bg-teal-subtle text-teal rounded-3 p-3 mb-3 d-inline-block">
                        <i class="bi bi-robot fs-3"></i>
                    </div>
                    <h5 class="fw-bold">AI Symptom Classifier</h5>
                    <p class="text-secondary small">Scikit-learn Natural Language Processing model trained on multi-specialty clinical symptoms for department routing and doctor matching.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100">
                    <div class="icon-box bg-primary-subtle text-primary rounded-3 p-3 mb-3 d-inline-block">
                        <i class="bi bi-camera-video fs-3"></i>
                    </div>
                    <h5 class="fw-bold">Browser Video Telehealth</h5>
                    <p class="text-secondary small">Zero-install WebRTC video consultation room with doctor live clinical notes pad and 1-click digital prescription generation.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100">
                    <div class="icon-box bg-warning-subtle text-warning rounded-3 p-3 mb-3 d-inline-block">
                        <i class="bi bi-hourglass-split fs-3"></i>
                    </div>
                    <h5 class="fw-bold">Smart Queue Prediction</h5>
                    <p class="text-secondary small">Dynamic wait time estimation algorithm based on doctor patient workload, token queue tracking, and live OPD flow updates.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100">
                    <div class="icon-box bg-success-subtle text-success rounded-3 p-3 mb-3 d-inline-block">
                        <i class="bi bi-wallet2 fs-3"></i>
                    </div>
                    <h5 class="fw-bold">Integrated Fast Payments</h5>
                    <p class="text-secondary small">Support for UPI (QR & VPA), Credit/Debit Cards, and Net Banking with instant automated PDF receipt generation.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100">
                    <div class="icon-box bg-info-subtle text-info rounded-3 p-3 mb-3 d-inline-block">
                        <i class="bi bi-file-earmark-medical fs-3"></i>
                    </div>
                    <h5 class="fw-bold">Digital Prescription Vault</h5>
                    <p class="text-secondary small">Secure medical records vault with MIME-validated PDF/JPG uploads, random cryptographic filenames, and download history.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="glass-card glass-card-hover p-4 h-100">
                    <div class="icon-box bg-danger-subtle text-danger rounded-3 p-3 mb-3 d-inline-block">
                        <i class="bi bi-cpu fs-3"></i>
                    </div>
                    <h5 class="fw-bold">ML OPD Inflow Predictor</h5>
                    <p class="text-secondary small">Random Forest ML regression predicting tomorrow's patient inflow, peak hours, and departmental capacity load for hospital leadership.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Departments Showcase -->
<section class="py-5 bg-body-secondary">
    <div class="container px-lg-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
            <div>
                <span class="text-teal fw-bold text-uppercase fs-xs">Medical Centers of Excellence</span>
                <h2 class="display-6 fw-bold mt-1">Specialized Care Departments</h2>
            </div>
            <a href="<?= base_url('patient/book_appointment.php') ?>" class="btn btn-outline-teal rounded-pill mt-3 mt-md-0">
                View All Specialties <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-3">
            <?php foreach ($departments as $dept): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <a href="<?= base_url('patient/book_appointment.php?dept=' . $dept['id']) ?>" class="text-decoration-none">
                        <div class="glass-card glass-card-hover p-3 h-100 d-flex align-items-center gap-3">
                            <div class="icon-box bg-teal-subtle text-teal rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="bi <?= e($dept['icon']) ?> fs-4"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-body mb-1"><?= e($dept['name']) ?></h6>
                                <span class="badge bg-secondary-subtle text-secondary fs-xs"><?= e($dept['code']) ?></span>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Doctors Section -->
<section class="py-5">
    <div class="container px-lg-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
            <div>
                <span class="text-teal fw-bold text-uppercase fs-xs">Leading Medical Practitioners</span>
                <h2 class="display-6 fw-bold mt-1">Top Rated Specialists</h2>
            </div>
            <a href="<?= base_url('patient/book_appointment.php') ?>" class="btn btn-teal text-white rounded-pill mt-3 mt-md-0">
                Book a Doctor <i class="bi bi-calendar-check ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($featuredDoctors as $doc): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="glass-card glass-card-hover p-4 h-100 d-flex flex-column text-center">
                        <div class="user-avatar-badge mx-auto mb-3" style="width: 72px; height: 72px; font-size: 1.8rem;">
                            <?= strtoupper(substr($doc['name'], 4, 1)) ?>
                        </div>
                        <h5 class="fw-bold mb-1"><?= e($doc['name']) ?></h5>
                        <p class="text-teal small fw-semibold mb-1"><?= e($doc['department_name']) ?></p>
                        <p class="text-secondary fs-xs mb-3"><?= e($doc['specialization']) ?></p>
                        
                        <div class="d-flex justify-content-center align-items-center gap-3 py-2 mb-3 bg-body-tertiary rounded-3">
                            <span class="fs-xs text-secondary"><i class="bi bi-star-fill text-warning"></i> <?= number_format($doc['rating'], 1) ?></span>
                            <span class="fs-xs text-secondary"><i class="bi bi-clock-history"></i> <?= $doc['experience_years'] ?> yrs</span>
                            <span class="fs-xs text-secondary fw-bold text-teal">₹<?= number_format($doc['consultation_fee'], 0) ?></span>
                        </div>

                        <a href="<?= base_url('patient/book_appointment.php?doctor_id=' . $doc['id']) ?>" class="btn btn-outline-teal w-100 mt-auto rounded-pill py-2">
                            Select Slot <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Demo Login Quick-Bar for Rapid Evaluation -->
<section class="py-4 bg-teal-subtle border-top border-teal-subtle">
    <div class="container px-lg-5">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-key-fill text-teal fs-4"></i>
                <div>
                    <h6 class="fw-bold mb-0 text-teal">Pre-Configured Demo Credentials</h6>
                    <small class="text-secondary">Password for all seed accounts: <code>Password@123</code></small>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= base_url('login.php?quick=admin') ?>" class="btn btn-sm btn-dark rounded-pill px-3">
                    <i class="bi bi-shield-lock-fill me-1"></i> Admin Portal (admin@carepulse.ai)
                </a>
                <a href="<?= base_url('login.php?quick=doctor') ?>" class="btn btn-sm btn-teal text-white rounded-pill px-3">
                    <i class="bi bi-heart-pulse-fill me-1"></i> Doctor Portal (doctor.sharma@carepulse.ai)
                </a>
                <a href="<?= base_url('login.php?quick=patient') ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                    <i class="bi bi-person-fill me-1"></i> Patient Portal (patient@carepulse.ai)
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
