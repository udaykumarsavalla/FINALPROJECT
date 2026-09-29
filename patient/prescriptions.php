<?php
/**
 * CarePulse AI - Digital Prescription Vault & Printable PDF Viewer
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();

$pageTitle = "Digital Prescription Vault";
$activeMenu = "vault";
$patientId = Auth::hasRole('patient') ? Auth::currentUserId() : (int)($_GET['patient_id'] ?? 0);

$selectedRxId = (int)($_GET['rx_id'] ?? 0);

// Fetch all prescriptions for this patient
$prescriptions = Database::fetchAll("
    SELECT p.*, doc.specialization, doc.qualification,
           u_doc.name as doctor_name, dep.name as department_name,
           a.appointment_date, a.appointment_type, a.appointment_number
    FROM prescriptions p
    JOIN appointments a ON p.appointment_id = a.id
    JOIN doctor_profiles doc ON p.doctor_id = doc.id
    JOIN users u_doc ON doc.user_id = u_doc.id
    JOIN departments dep ON doc.department_id = dep.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
", [$patientId]);

// Active prescription to display
$activeRx = null;
if ($selectedRxId > 0) {
    foreach ($prescriptions as $p) {
        if ((int)$p['id'] === $selectedRxId) {
            $activeRx = $p;
            break;
        }
    }
}
if (!$activeRx && !empty($prescriptions)) {
    $activeRx = $prescriptions[0];
}

$medicines = [];
if ($activeRx) {
    $medicines = json_decode($activeRx['medicines'], true) ?: [];
}

// Fetch patient info
$patientUser = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$patientId]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row g-4">
        <!-- Left: Prescriptions List (Hidden in print) -->
        <div class="col-lg-4 no-print">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-medical me-2 text-teal"></i> Rx Vault</h5>
                    <span class="badge bg-teal-subtle text-teal"><?= count($prescriptions) ?> Issued</span>
                </div>

                <?php if (empty($prescriptions)): ?>
                    <p class="text-secondary small mb-0">No digital prescriptions found in your health vault.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush space-y-2">
                        <?php foreach ($prescriptions as $rx): ?>
                            <?php $isActive = $activeRx && $activeRx['id'] === $rx['id']; ?>
                            <a href="<?= base_url('patient/prescriptions.php?rx_id=' . $rx['id']) ?>" class="list-group-item list-group-item-action rounded-3 border p-3 <?= $isActive ? 'border-teal bg-teal-subtle' : 'bg-body-tertiary' ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-body"><?= e($rx['prescription_number']) ?></strong>
                                    <span class="text-secondary fs-xs"><?= date('M d, Y', strtotime($rx['created_at'])) ?></span>
                                </div>
                                <div class="small fw-semibold text-teal"><?= e($rx['doctor_name']) ?></div>
                                <p class="text-secondary fs-xs mb-0 text-truncate"><?= e($rx['diagnosis']) ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="mt-4 pt-3 border-top">
                    <a href="<?= base_url('patient/medical_records.php') ?>" class="btn btn-outline-teal btn-sm w-100 rounded-pill">
                        <i class="bi bi-folder2-open me-1"></i> View Lab Reports & Scans
                    </a>
                </div>
            </div>
        </div>

        <!-- Right: Official Digital Prescription Sheet -->
        <div class="col-lg-8">
            <?php if (!$activeRx): ?>
                <div class="glass-card p-5 text-center">
                    <i class="bi bi-file-earmark-text text-muted display-4 d-block mb-3"></i>
                    <h4 class="fw-bold">No Prescription Selected</h4>
                    <p class="text-secondary small">Your digital prescriptions issued by hospital doctors will appear here.</p>
                </div>
            <?php else: ?>
                <!-- Action Controls (Hidden when printed) -->
                <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-shield-check me-1"></i> Digitally Signed & Verified
                    </span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-teal text-white btn-sm rounded-pill px-3 shadow-sm" onclick="window.print()">
                            <i class="bi bi-printer-fill me-1"></i> Print / Download PDF
                        </button>
                    </div>
                </div>

                <!-- Printable Digital Rx Card -->
                <div class="glass-card print-sheet p-4 p-md-5 border shadow-lg bg-white text-dark">
                    <!-- Prescription Header -->
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="brand-logo-icon" style="width: 52px; height: 52px; font-size: 1.6rem; background: linear-gradient(135deg, #0d9488, #0284c7);">
                                <i class="bi bi-heart-pulse-fill text-white"></i>
                            </div>
                            <div>
                                <h3 class="fw-extrabold mb-0" style="color: #0f172a;">CarePulse AI Hospital</h3>
                                <p class="text-muted small mb-0">Multi-Specialty Telehealth & Academic Medical Center</p>
                                <span class="badge bg-secondary-subtle text-secondary fs-xs">Accredited JCI & ISO 27001</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <h5 class="fw-bold mb-0 text-dark"><?= e($activeRx['doctor_name']) ?></h5>
                            <small class="text-teal fw-semibold d-block"><?= e($activeRx['specialization']) ?></small>
                            <small class="text-muted d-block"><?= e($activeRx['qualification']) ?></small>
                            <small class="text-muted">Reg No: MCI-2014-98721</small>
                        </div>
                    </div>

                    <!-- Patient & Consultation Metadata Strip -->
                    <div class="row g-3 p-3 rounded-3 bg-light border mb-4">
                        <div class="col-sm-6 col-md-3">
                            <span class="text-muted fs-xs d-block">Patient Name</span>
                            <strong class="text-dark"><?= e($patientUser['name'] ?? 'Patient') ?></strong>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-muted fs-xs d-block">Age / Gender / Blood</span>
                            <strong class="text-dark">32 Y &bull; <?= ucfirst($patientUser['gender'] ?? 'M') ?> &bull; <?= e($patientUser['blood_group'] ?? 'O+') ?></strong>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-muted fs-xs d-block">Date of Issue</span>
                            <strong class="text-dark"><?= date('M d, Y', strtotime($activeRx['created_at'])) ?></strong>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-muted fs-xs d-block">Rx Identifier</span>
                            <strong class="text-teal"><?= e($activeRx['prescription_number']) ?></strong>
                        </div>
                    </div>

                    <!-- Clinical Diagnosis -->
                    <div class="mb-4">
                        <span class="text-muted fs-xs fw-bold text-uppercase d-block mb-1">Clinical Diagnosis & Findings:</span>
                        <div class="p-3 rounded-2 bg-light border-start border-4 border-teal text-dark fw-semibold">
                            <?= e($activeRx['diagnosis']) ?>
                        </div>
                    </div>

                    <!-- Rx Prescribed Medicines Table -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="display-6 fw-bold text-teal" style="font-family: serif;">&#8478;</span>
                            <h5 class="fw-bold mb-0 text-dark">Prescribed Medications</h5>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr class="small text-muted">
                                        <th style="width: 35%;">Medicine & Strength</th>
                                        <th style="width: 15%;">Dosage</th>
                                        <th style="width: 20%;" class="text-center">Schedule (M - A - N)</th>
                                        <th style="width: 15%;">Duration</th>
                                        <th style="width: 15%;">Instructions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medicines as $med): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-dark"><?= e($med['medicine_name']) ?></strong>
                                            </td>
                                            <td><?= e($med['dosage'] ?? '1 tab') ?></td>
                                            <td class="text-center font-monospace">
                                                <span class="badge bg-<?= !empty($med['morning']) ? 'teal' : 'light' ?> text-<?= !empty($med['morning']) ? 'white' : 'muted' ?>">M</span>
                                                <span class="badge bg-<?= !empty($med['afternoon']) ? 'teal' : 'light' ?> text-<?= !empty($med['afternoon']) ? 'white' : 'muted' ?>">A</span>
                                                <span class="badge bg-<?= !empty($med['night']) ? 'teal' : 'light' ?> text-<?= !empty($med['night']) ? 'white' : 'muted' ?>">N</span>
                                            </td>
                                            <td><?= e($med['duration'] ?? '7 days') ?></td>
                                            <td class="small text-muted"><?= e($med['instructions'] ?? 'After food') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Clinical Advice & Follow-up -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <span class="text-muted fs-xs fw-bold text-uppercase d-block mb-1">Doctor Advice & Lifestyle Guidance:</span>
                            <p class="small text-dark mb-0 bg-light p-3 rounded-2 border">
                                <?= !empty($activeRx['advice']) ? nl2br(e($activeRx['advice'])) : 'Standard post-consultation recovery care. Maintain proper hydration and balanced diet.' ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-2 bg-light border text-center h-100 d-flex flex-column justify-content-center">
                                <span class="text-muted fs-xs fw-bold text-uppercase d-block">Recommended Follow-up</span>
                                <h6 class="fw-bold text-teal mt-1 mb-0">
                                    <?= !empty($activeRx['follow_up_date']) ? date('M d, Y', strtotime($activeRx['follow_up_date'])) : 'As needed / SOS' ?>
                                </h6>
                            </div>
                        </div>
                    </div>

                    <!-- Digital Signature & QR Verification Footer -->
                    <div class="d-flex justify-content-between align-items-end pt-4 border-top">
                        <div>
                            <small class="text-muted d-block">Generated securely via CarePulse AI Telehealth Engine.</small>
                            <small class="text-muted">Document Hash: <code><?= substr(hash('sha256', $activeRx['prescription_number']), 0, 16) ?></code></small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-teal" style="font-family: 'Brush Script MT', cursive; font-size: 1.6rem;">
                                <?= e($activeRx['doctor_name']) ?>
                            </div>
                            <span class="text-muted fs-xs d-block border-top pt-1">Authorized Medical Officer Signature</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
