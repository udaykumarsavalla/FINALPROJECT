<?php
/**
 * CarePulse AI - Common Footer Component
 */
?>
<footer class="footer-custom mt-auto py-5 border-top">
    <div class="container-fluid px-lg-5">
        <div class="row g-4 justify-content-between">
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="brand-logo-icon">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                    <span class="brand-title">CarePulse<span class="brand-ai">AI</span></span>
                </div>
                <p class="text-secondary small mb-3">
                    Next-generation intelligent telehealth and smart hospital platform powered by machine learning, real-time consultation, automated queue prediction, and secure prescription vaults.
                </p>
                <div class="d-flex gap-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-shield-check me-1"></i> HIPAA & ISO Compliant Architecture
                    </span>
                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                        <i class="bi bi-cpu me-1"></i> Scikit-Learn AI v1.9
                    </span>
                </div>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <h6 class="fw-bold mb-3">Patient Portal</h6>
                <ul class="list-unstyled text-secondary small space-y-2">
                    <li class="mb-2"><a href="<?= base_url('patient/symptom_checker.php') ?>" class="text-decoration-none text-secondary">AI Symptom Checker</a></li>
                    <li class="mb-2"><a href="<?= base_url('patient/book_appointment.php') ?>" class="text-decoration-none text-secondary">Smart Booking</a></li>
                    <li class="mb-2"><a href="<?= base_url('patient/queue_tracker.php') ?>" class="text-decoration-none text-secondary">Live Queue Tracker</a></li>
                    <li class="mb-2"><a href="<?= base_url('patient/prescriptions.php') ?>" class="text-decoration-none text-secondary">Prescription Vault</a></li>
                    <li class="mb-2"><a href="<?= base_url('patient/medication_reminders.php') ?>" class="text-decoration-none text-secondary">Medication Alerts</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <h6 class="fw-bold mb-3">Clinical & Admin</h6>
                <ul class="list-unstyled text-secondary small">
                    <li class="mb-2"><a href="<?= base_url('doctor/index.php') ?>" class="text-decoration-none text-secondary">Doctor OPD Desk</a></li>
                    <li class="mb-2"><a href="<?= base_url('doctor/appointments.php') ?>" class="text-decoration-none text-secondary">Patient Queue Workload</a></li>
                    <li class="mb-2"><a href="<?= base_url('admin/index.php') ?>" class="text-decoration-none text-secondary">Hospital Executive Portal</a></li>
                    <li class="mb-2"><a href="<?= base_url('admin/opd_prediction.php') ?>" class="text-decoration-none text-secondary">ML OPD Inflow Predictor</a></li>
                    <li class="mb-2"><a href="<?= base_url('admin/analytics.php') ?>" class="text-decoration-none text-secondary">Department Analytics</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold mb-3">Emergency & Telehealth Support</h6>
                <p class="text-secondary small mb-2">24x7 Ambulance & Hospital Triage Helpline:</p>
                <h5 class="fw-bold text-teal mb-3"><i class="bi bi-telephone-inbound-fill me-2"></i> 1800-CARE-PULSE</h5>
                <p class="text-secondary fs-xs mb-0">CarePulse AI Platform &copy; <?= date('Y') ?>. Built with PHP 8, MySQL 8, Bootstrap 5, Scikit-learn, and WebRTC.</p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js 4.4 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- Global App JS -->
<script>
    window.APP_CONFIG = {
        baseUrl: "<?= rtrim(base_url(), '/') ?>",
        csrfToken: "<?= e(Security::getCsrfToken()) ?>",
        currentUser: <?= json_encode($currentUser) ?>
    };
</script>
<script src="<?= base_url('assets/js/main.js') ?>"></script>
</body>
</html>
