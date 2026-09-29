<?php
/**
 * CarePulse AI - Doctor Clinical Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['doctor']);

$pageTitle = "Doctor OPD Workbench";
$activeMenu = "doc_dashboard";
$userId = Auth::currentUserId();

$docProfile = Database::fetchOne("
    SELECT d.*, u.name, u.email, u.phone, u.avatar, dep.name as department_name, dep.icon as department_icon
    FROM doctor_profiles d
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON d.department_id = dep.id
    WHERE d.user_id = ?
", [$userId]);

if (!$docProfile) {
    die("Doctor clinical profile not configured. Please contact hospital administrator.");
}

$doctorId = (int)$docProfile['id'];
$today = date('Y-m-d');

// 1. Fetch Today's Queue
$todayQueue = Database::fetchAll("
    SELECT a.*, u.name as patient_name, u.gender as patient_gender, u.dob as patient_dob, u.phone as patient_phone
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status NOT IN ('cancelled', 'pending_payment')
    ORDER BY a.queue_number ASC
", [$doctorId, $today]);

// Counts
$totalToday = count($todayQueue);
$completedCount = 0;
$waitingCount = 0;
$inConsultCount = 0;
$activePatient = null;
$nextPatient = null;

foreach ($todayQueue as $apt) {
    if ($apt['status'] === 'completed') {
        $completedCount++;
    } elseif ($apt['status'] === 'in_consultation') {
        $inConsultCount++;
        $activePatient = $apt;
    } elseif ($apt['status'] === 'confirmed') {
        $waitingCount++;
        if (!$nextPatient && !$activePatient) {
            $nextPatient = $apt;
        } elseif (!$nextPatient && $activePatient) {
            $nextPatient = $apt;
        }
    }
}

// 2. Fetch Recent Prescriptions Issued
$recentRx = Database::fetchAll("
    SELECT p.*, u.name as patient_name
    FROM prescriptions p
    JOIN users u ON p.patient_id = u.id
    WHERE p.doctor_id = ?
    ORDER BY p.created_at DESC LIMIT 5
", [$doctorId]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-lg-5 py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                <i class="bi bi-heart-pulse-fill me-1"></i> Doctor Clinical Desk &bull; <?= e($docProfile['department_name']) ?>
            </span>
            <h2 class="display-6 fw-bold mb-0"><?= e($docProfile['name']) ?></h2>
            <p class="text-secondary small mb-0"><?= e($docProfile['specialization']) ?> &bull; <?= e($docProfile['room_number']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('doctor/appointments.php') ?>" class="btn btn-outline-teal rounded-pill px-3 py-2 fw-semibold">
                <i class="bi bi-people me-1"></i> OPD Queue Schedule
            </a>
            <a href="<?= base_url('doctor/create_prescription.php') ?>" class="btn btn-teal text-white rounded-pill px-4 py-2 fw-semibold shadow-sm">
                <i class="bi bi-prescription2 me-1"></i> Write Rx
            </a>
        </div>
    </div>

    <!-- Active Call Banner if patient currently in consultation -->
    <?php if ($activePatient): ?>
        <div class="glass-card p-4 mb-4 border border-primary shadow-lg bg-primary-subtle text-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="queue-token-circle" style="width: 68px; height: 68px; background: linear-gradient(135deg, #0284c7, #0d9488);">
                        <span class="token-lbl">TOKEN</span>
                        <span class="token-num" style="font-size: 1.5rem;">#<?= $activePatient['queue_number'] ?></span>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="pulse-indicator"></span>
                            <h5 class="fw-bold mb-0">Consultation In Progress: <?= e($activePatient['patient_name']) ?></h5>
                        </div>
                        <p class="text-secondary small mb-0 mt-1">
                            Mode: <strong><?= ucwords(str_replace('_', ' ', $activePatient['appointment_type'])) ?></strong> &bull;
                            Symptoms: <?= e($activePatient['symptoms_summary'] ?: 'None reported') ?>
                        </p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= base_url('doctor/consultation.php?appointment_id=' . $activePatient['id']) ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold text-white shadow-sm">
                        <i class="bi bi-camera-video-fill me-1"></i> Open Consultation Desk
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Today's Patients</span>
                        <div class="metric-number text-teal"><?= $totalToday ?></div>
                    </div>
                    <div class="icon-box bg-teal-subtle text-teal">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Waiting in Queue</span>
                        <div class="metric-number text-warning"><?= $waitingCount ?></div>
                    </div>
                    <div class="icon-box bg-warning-subtle text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Completed Consults</span>
                        <div class="metric-number text-success"><?= $completedCount ?></div>
                    </div>
                    <div class="icon-box bg-success-subtle text-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Doctor Rating</span>
                        <div class="metric-number text-primary"><?= number_format($docProfile['rating'], 2) ?></div>
                    </div>
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-star-fill text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content: Today's Queue Table & Next Patient -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-list-ol me-2 text-teal"></i> Today's Patient Queue (<?= date('M d, Y') ?>)</h5>
                    <button type="button" class="btn btn-sm btn-outline-teal rounded-pill" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>

                <?php if (empty($todayQueue)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-calendar2-check fs-1 d-block mb-2"></i>
                        <p class="mb-0">No patient appointments scheduled for today yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small">
                                    <th>Token</th>
                                    <th>Patient</th>
                                    <th>Time</th>
                                    <th>Mode</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todayQueue as $apt): ?>
                                    <tr class="<?= $apt['status'] === 'in_consultation' ? 'table-primary' : '' ?>">
                                        <td>
                                            <span class="badge bg-teal-subtle text-teal fw-bold fs-6">#<?= $apt['queue_number'] ?></span>
                                        </td>
                                        <td>
                                            <strong class="text-body d-block"><?= e($apt['patient_name']) ?></strong>
                                            <small class="text-secondary"><?= e($apt['patient_phone']) ?></small>
                                        </td>
                                        <td><?= date('h:i A', strtotime($apt['appointment_time'])) ?></td>
                                        <td>
                                            <?php if ($apt['appointment_type'] === 'online_consultation'): ?>
                                                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-camera-video me-1"></i> Video</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-hospital me-1"></i> In-Chamber</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= e($apt['status']) ?>"><?= e(str_replace('_', ' ', $apt['status'])) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($apt['status'] === 'confirmed'): ?>
                                                <a href="<?= base_url('doctor/consultation.php?appointment_id=' . $apt['id']) ?>" class="btn btn-sm btn-teal text-white rounded-pill px-3">
                                                    Call Patient
                                                </a>
                                            <?php elseif ($apt['status'] === 'in_consultation'): ?>
                                                <a href="<?= base_url('doctor/consultation.php?appointment_id=' . $apt['id']) ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                                    Resume Desk
                                                </a>
                                            <?php elseif ($apt['status'] === 'completed'): ?>
                                                <a href="<?= base_url('doctor/create_prescription.php?appointment_id=' . $apt['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                    View Rx
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Next Up & Recent Prescriptions -->
        <div class="col-lg-4">
            <!-- Next Up Patient Card -->
            <?php if ($nextPatient): ?>
                <div class="glass-card p-4 mb-4 border border-teal-subtle">
                    <span class="badge bg-warning-subtle text-warning fw-bold fs-xs text-uppercase mb-2">Next Patient in Line</span>
                    <h5 class="fw-bold mb-1 text-body"><?= e($nextPatient['patient_name']) ?></h5>
                    <p class="text-secondary small mb-2">Token #<?= $nextPatient['queue_number'] ?> &bull; <?= date('h:i A', strtotime($nextPatient['appointment_time'])) ?></p>
                    
                    <div class="p-2 rounded-2 bg-body-tertiary border mb-3 fs-xs text-secondary">
                        <strong>Symptoms:</strong> <?= e($nextPatient['symptoms_summary'] ?: 'General checkup') ?>
                    </div>

                    <a href="<?= base_url('doctor/consultation.php?appointment_id=' . $nextPatient['id']) ?>" class="btn btn-teal text-white w-100 rounded-pill py-2 fw-bold shadow-sm">
                        <i class="bi bi-telephone-inbound-fill me-1"></i> Call Into Consultation
                    </a>
                </div>
            <?php endif; ?>

            <!-- Recent Prescriptions Issued -->
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-prescription me-2 text-primary"></i> Recently Issued Prescriptions</h5>
                <?php if (empty($recentRx)): ?>
                    <p class="text-secondary small mb-0">No prescriptions written yet today.</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($recentRx as $rx): ?>
                            <div class="p-2 rounded-2 bg-body-tertiary border mb-2 fs-xs">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-body"><?= e($rx['patient_name']) ?></strong>
                                    <span class="text-secondary"><?= date('M d', strtotime($rx['created_at'])) ?></span>
                                </div>
                                <div class="text-teal fw-semibold"><?= e($rx['prescription_number']) ?></div>
                                <div class="text-muted text-truncate"><?= e($rx['diagnosis']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
