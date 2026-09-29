<?php
/**
 * CarePulse AI - Common Navigation Header & Theme Controller
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';

$currentUser = Auth::currentUser();
$userRole = Auth::currentUserRole();
$csrfToken = Security::getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' . APP_NAME : APP_NAME . ' – ' . APP_TAGLINE ?></title>
    
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- CarePulse AI Custom Healthcare Theme CSS -->
    <link rel="stylesheet" href="<?= base_url('assets/css/main.css') ?>">
</head>
<body class="bg-body-tertiary">

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-glass sticky-top py-3">
    <div class="container-fluid px-lg-5">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= base_url('index.php') ?>">
            <div class="brand-logo-icon">
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <div>
                <span class="brand-title">CarePulse<span class="brand-ai">AI</span></span>
                <span class="badge bg-teal-subtle text-teal ms-1 fw-bold fs-xs">SMART HEALTH</span>
            </div>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <i class="bi bi-list fs-2"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4 gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link <?= !isset($activeMenu) || $activeMenu === 'home' ? 'active' : '' ?>" href="<?= base_url('index.php') ?>">
                        <i class="bi bi-house-door me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($activeMenu ?? '') === 'symptom_checker' ? 'active' : '' ?>" href="<?= base_url('patient/symptom_checker.php') ?>">
                        <i class="bi bi-robot text-teal me-1"></i> AI Symptom Checker
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($activeMenu ?? '') === 'book' ? 'active' : '' ?>" href="<?= base_url('patient/book_appointment.php') ?>">
                        <i class="bi bi-calendar2-check me-1"></i> Book Appointment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($activeMenu ?? '') === 'telemedicine' ? 'active' : '' ?>" href="<?= base_url('telemedicine/index.php') ?>">
                        <i class="bi bi-camera-video text-primary me-1"></i> Online Consultation
                    </a>
                </li>

                <?php if ($userRole === 'patient'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= base_url('patient/index.php') ?>">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'book' ? 'active' : '' ?>" href="<?= base_url('patient/book_appointment.php') ?>">
                            <i class="bi bi-calendar-plus me-1"></i> Book Appointment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'queue' ? 'active' : '' ?>" href="<?= base_url('patient/queue_tracker.php') ?>">
                            <i class="bi bi-hourglass-split me-1"></i> Queue Tracker
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'vault' ? 'active' : '' ?>" href="<?= base_url('patient/prescriptions.php') ?>">
                            <i class="bi bi-file-earmark-medical me-1"></i> Rx Vault
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'reminders' ? 'active' : '' ?>" href="<?= base_url('patient/medication_reminders.php') ?>">
                            <i class="bi bi-alarm me-1"></i> Reminders
                        </a>
                    </li>
                <?php elseif ($userRole === 'doctor'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'doc_dashboard' ? 'active' : '' ?>" href="<?= base_url('doctor/index.php') ?>">
                            <i class="bi bi-grid me-1"></i> Doctor Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'doc_appointments' ? 'active' : '' ?>" href="<?= base_url('doctor/appointments.php') ?>">
                            <i class="bi bi-people me-1"></i> Today's Queue
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'doc_rx' ? 'active' : '' ?>" href="<?= base_url('doctor/create_prescription.php') ?>">
                            <i class="bi bi-prescription2 me-1"></i> Write Prescription
                        </a>
                    </li>
                <?php elseif ($userRole === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'admin_dashboard' ? 'active' : '' ?>" href="<?= base_url('admin/index.php') ?>">
                            <i class="bi bi-speedometer2 me-1"></i> Admin Portal
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'admin_analytics' ? 'active' : '' ?>" href="<?= base_url('admin/analytics.php') ?>">
                            <i class="bi bi-bar-chart-line me-1"></i> Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'admin_opd' ? 'active' : '' ?>" href="<?= base_url('admin/opd_prediction.php') ?>">
                            <i class="bi bi-cpu-fill text-warning me-1"></i> AI OPD Inflow
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeMenu ?? '') === 'admin_doctors' ? 'active' : '' ?>" href="<?= base_url('admin/doctors.php') ?>">
                            <i class="bi bi-hospital me-1"></i> Doctors
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Right Controls: Dark Mode Toggle + User Profile / Login -->
            <div class="d-flex align-items-center gap-3">
                <!-- Dark / Light Mode Switcher -->
                <button class="btn btn-icon theme-toggle-btn rounded-circle" id="themeToggleBtn" title="Toggle Dark/Light Mode" type="button">
                    <i class="bi bi-moon-stars-fill theme-icon-dark"></i>
                    <i class="bi bi-sun-fill theme-icon-light d-none"></i>
                </button>

                <?php if ($currentUser): ?>
                    <div class="dropdown">
                        <button class="btn user-profile-btn dropdown-toggle d-flex align-items-center gap-2 border-0" type="button" data-bs-toggle="dropdown">
                            <div class="user-avatar-badge">
                                <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                            </div>
                            <div class="text-start d-none d-sm-block">
                                <span class="d-block fw-semibold text-truncate" style="max-width: 140px;"><?= e($currentUser['name']) ?></span>
                                <span class="badge role-badge role-<?= e($userRole) ?> text-uppercase"><?= e($userRole) ?></span>
                            </div>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 p-2 mt-2">
                            <li><h6 class="dropdown-header text-muted">Signed in as <strong><?= e($currentUser['email']) ?></strong></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 rounded-2" href="<?= base_url(Auth::getDashboardUrl()) ?>">
                                    <i class="bi bi-speedometer2 me-2 text-primary"></i> My Portal Dashboard
                                </a>
                            </li>
                            <?php if ($userRole === 'patient'): ?>
                                <li>
                                    <a class="dropdown-item py-2 rounded-2" href="<?= base_url('patient/medical_records.php') ?>">
                                        <i class="bi bi-folder2-open me-2 text-teal"></i> Health Record Vault
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 rounded-2 text-danger" href="<?= base_url('logout.php') ?>">
                                    <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= base_url('login.php') ?>" class="btn btn-outline-teal px-3 py-2 fw-semibold rounded-pill">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </a>
                        <a href="<?= base_url('register.php') ?>" class="btn btn-teal px-3 py-2 fw-semibold rounded-pill text-white shadow-sm">
                            <i class="bi bi-person-plus me-1"></i> Register
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- System Alert Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="carepulseToast" class="toast align-items-center border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2" id="toastMessage">
                <i class="bi bi-info-circle-fill text-primary fs-5" id="toastIcon"></i>
                <span id="toastText">Notification message</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
