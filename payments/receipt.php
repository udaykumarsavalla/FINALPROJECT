<?php
/**
 * CarePulse AI - Payment Receipt & Appointment Confirmation Invoice
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();

$paymentId = (int)($_GET['id'] ?? 0);
$patientId = Auth::currentUserId();

if ($paymentId <= 0) {
    header("Location: " . base_url('patient/index.php'));
    exit;
}

$payment = Database::fetchOne("
    SELECT p.*, a.appointment_number, a.appointment_date, a.appointment_time,
           a.appointment_type, a.queue_number, a.meeting_room_id, a.status as apt_status,
           u_doc.name as doctor_name, dep.name as department_name, d.room_number,
           u_pat.name as patient_name, u_pat.email as patient_email, u_pat.phone as patient_phone
    FROM payments p
    JOIN appointments a ON p.appointment_id = a.id
    JOIN doctor_profiles d ON a.doctor_id = d.id
    JOIN users u_doc ON d.user_id = u_doc.id
    JOIN departments dep ON a.department_id = dep.id
    JOIN users u_pat ON p.patient_id = u_pat.id
    WHERE p.id = ?
", [$paymentId]);

if (!$payment) {
    die("Payment transaction record not found.");
}

$pageTitle = "Official Payment Receipt #" . $payment['receipt_number'];
$activeMenu = "dashboard";

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Action bar (Hidden in print) -->
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <a href="<?= base_url('patient/index.php') ?>" class="btn btn-outline-secondary rounded-pill btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Patient Dashboard
                </a>
                <div class="d-flex gap-2">
                    <?php if ($payment['appointment_type'] === 'online_consultation'): ?>
                        <a href="<?= base_url('patient/video_consultation.php?room=' . urlencode($payment['meeting_room_id'])) ?>" class="btn btn-teal text-white rounded-pill btn-sm fw-bold">
                            <i class="bi bi-camera-video me-1"></i> Enter Video Room
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-dark rounded-pill btn-sm" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print Receipt
                    </button>
                </div>
            </div>

            <!-- Printable Receipt Card -->
            <div class="glass-card print-sheet p-4 p-md-5 border shadow-lg bg-white text-dark">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="brand-logo-icon" style="width: 52px; height: 52px; font-size: 1.6rem; background: linear-gradient(135deg, #0d9488, #0284c7);">
                            <i class="bi bi-heart-pulse-fill text-white"></i>
                        </div>
                        <div>
                            <h3 class="fw-extrabold mb-0 text-dark">CarePulse AI Hospital</h3>
                            <p class="text-muted small mb-0">Telehealth & Outpatient Billing Department</p>
                            <span class="badge bg-success-subtle text-success fs-xs">Payment Verified & Settled</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <h4 class="fw-bold mb-1 text-teal">RECEIPT</h4>
                        <div class="small text-muted">Receipt: <strong><?= e($payment['receipt_number']) ?></strong></div>
                        <div class="small text-muted">Date: <?= date('M d, Y h:i A', strtotime($payment['created_at'])) ?></div>
                    </div>
                </div>

                <!-- Transaction Details Strip -->
                <div class="row g-3 p-3 rounded-3 bg-light border mb-4">
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted fs-xs d-block">Transaction ID</span>
                        <strong class="text-dark font-monospace"><?= e($payment['transaction_id']) ?></strong>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted fs-xs d-block">Payment Mode</span>
                        <strong class="text-dark text-uppercase"><?= e($payment['payment_method']) ?></strong>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted fs-xs d-block">Appointment Number</span>
                        <strong class="text-teal"><?= e($payment['appointment_number']) ?></strong>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted fs-xs d-block">Queue Token</span>
                        <strong class="text-dark fs-5">#<?= e($payment['queue_number']) ?></strong>
                    </div>
                </div>

                <!-- Billed To & Service Details -->
                <div class="row g-4 mb-4">
                    <div class="col-sm-6">
                        <h6 class="text-muted fs-xs fw-bold text-uppercase mb-2">Billed To (Patient):</h6>
                        <strong class="text-dark d-block"><?= e($payment['patient_name']) ?></strong>
                        <div class="small text-muted"><?= e($payment['patient_email']) ?></div>
                        <div class="small text-muted"><?= e($payment['patient_phone']) ?></div>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <h6 class="text-muted fs-xs fw-bold text-uppercase mb-2">Consultation Scheduled With:</h6>
                        <strong class="text-dark d-block"><?= e($payment['doctor_name']) ?></strong>
                        <div class="small text-muted"><?= e($payment['department_name']) ?></div>
                        <div class="small text-muted">Date: <?= date('M d, Y', strtotime($payment['appointment_date'])) ?> at <?= date('h:i A', strtotime($payment['appointment_time'])) ?></div>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr class="small text-muted">
                                <th>Description</th>
                                <th>Mode</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong class="text-dark">Specialist Outpatient Clinical Consultation Fee</strong>
                                    <div class="small text-muted"><?= e($payment['doctor_name']) ?> &bull; <?= e($payment['department_name']) ?></div>
                                </td>
                                <td><?= ucwords(str_replace('_', ' ', $payment['appointment_type'])) ?></td>
                                <td class="text-end fw-bold">₹<?= number_format($payment['amount'], 2) ?></td>
                            </tr>
                            <tr>
                                <td colspan="2" class="text-end text-muted small">Subtotal:</td>
                                <td class="text-end">₹<?= number_format($payment['amount'], 2) ?></td>
                            </tr>
                            <tr>
                                <td colspan="2" class="text-end text-muted small">Applicable Taxes (GST 0% on Health Services):</td>
                                <td class="text-end">₹0.00</td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="2" class="text-end fw-bold">Total Paid:</td>
                                <td class="text-end fs-5 fw-extrabold text-teal">₹<?= number_format($payment['amount'], 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Notes & Digital Seal -->
                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <div>
                        <p class="text-muted fs-xs mb-0">This is a system generated computer receipt. No physical signature required.</p>
                        <p class="text-muted fs-xs mb-0">Thank you for choosing CarePulse AI Intelligent Healthcare Network.</p>
                    </div>
                    <div class="text-center p-2 rounded-2 border bg-light">
                        <span class="fs-xs fw-bold text-success text-uppercase d-block"><i class="bi bi-check-circle-fill"></i> PAID IN FULL</span>
                        <small class="text-muted font-monospace fs-xs"><?= date('Y-m-d') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
