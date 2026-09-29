<?php
/**
 * CarePulse AI - Patient Portal Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/queue_service.php';

Auth::requireAuth(['patient']);

$pageTitle = "Patient Health Portal";
$activeMenu = "dashboard";
$patientId = Auth::currentUserId();

// 1. Fetch upcoming appointments
$upcomingAppointments = Database::fetchAll("
    SELECT a.*, d.consultation_fee, d.room_number, d.avg_consult_time_mins,
           u.name as doctor_name, u.avatar as doctor_avatar, dep.name as department_name, dep.icon as department_icon
    FROM appointments a
    JOIN doctor_profiles d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON a.department_id = dep.id
    WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status NOT IN ('cancelled', 'completed')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
", [$patientId]);

// 2. Fetch active medication reminders
$activeReminders = Database::fetchAll("
    SELECT * FROM medication_reminders 
    WHERE patient_id = ? AND status = 'active'
    ORDER BY id DESC LIMIT 5
", [$patientId]);

// 3. Fetch recent digital prescriptions
$recentPrescriptions = Database::fetchAll("
    SELECT p.*, u.name as doctor_name, dep.name as department_name
    FROM prescriptions p
    JOIN doctor_profiles d ON p.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON d.department_id = dep.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC LIMIT 3
", [$patientId]);

// 4. Check if patient has an appointment today for live queue card
$todayAppointment = null;
foreach ($upcomingAppointments as $apt) {
    if ($apt['appointment_date'] === date('Y-m-d') && $apt['status'] !== 'pending_payment') {
        $todayAppointment = $apt;
        break;
    }
}

$todayQueue = null;
if ($todayAppointment) {
    $todayQueue = QueueService::calculateEstimatedWaitTime((int)$todayAppointment['id']);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-lg-5 py-4">
    <!-- Welcome Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                <i class="bi bi-person-heart me-1"></i> Patient Portal
            </span>
            <h2 class="display-6 fw-bold mb-0">Good day, <?= e($currentUser['name']) ?></h2>
            <p class="text-secondary small mb-0">Here is your live health schedule, active medications, and queue status.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('patient/symptom_checker.php') ?>" class="btn btn-outline-teal rounded-pill px-3 py-2 fw-semibold">
                <i class="bi bi-robot me-1"></i> AI Symptom Checker
            </a>
            <a href="<?= base_url('patient/book_appointment.php') ?>" class="btn btn-teal text-white rounded-pill px-4 py-2 fw-semibold shadow-sm">
                <i class="bi bi-calendar-plus me-1"></i> Book Appointment
            </a>
        </div>
    </div>

    <!-- Live Queue Banner if patient has an appointment today -->
    <?php if ($todayAppointment && $todayQueue): ?>
        <div class="glass-card p-4 mb-4 border border-teal shadow-lg bg-teal-subtle text-body">
            <div class="row align-items-center g-3">
                <div class="col-md-2 text-center border-end-md">
                    <div class="queue-token-circle">
                        <span class="token-lbl">Your Token</span>
                        <span class="token-num">#<?= $todayAppointment['queue_number'] ?></span>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="pulse-indicator"></span>
                        <h5 class="fw-bold mb-0">Today's Appointment with <?= e($todayAppointment['doctor_name']) ?></h5>
                        <span class="badge badge-<?= e($todayAppointment['status']) ?> text-capitalize ms-2"><?= str_replace('_', ' ', $todayAppointment['status']) ?></span>
                    </div>
                    <p class="text-secondary small mb-2">
                        <?= e($todayAppointment['department_name']) ?> &bull; 
                        <?= date('h:i A', strtotime($todayAppointment['appointment_time'])) ?> &bull; 
                        Mode: <span class="fw-bold text-teal"><?= ucwords(str_replace('_', ' ', $todayAppointment['appointment_type'])) ?></span>
                    </p>
                    <div class="d-flex align-items-center gap-4 text-secondary small">
                        <span>Currently Serving: <strong>Token #<?= $todayQueue['current_serving_token'] ?></strong></span>
                        <span>Patients Ahead: <strong><?= $todayQueue['patients_ahead'] ?></strong></span>
                        <span>Estimated Wait: <strong class="text-teal fs-6"><?= $todayQueue['estimated_wait_minutes'] ?> mins</strong></span>
                    </div>
                </div>
                <div class="col-md-3 text-md-end">
                    <?php if ($todayAppointment['appointment_type'] === 'online_consultation'): ?>
                        <a href="<?= base_url('patient/video_consultation.php?room=' . urlencode($todayAppointment['meeting_room_id'])) ?>" class="btn btn-teal text-white rounded-pill px-4 py-2 fw-bold w-100 mb-2 shadow-sm">
                            <i class="bi bi-camera-video me-1"></i> Enter Video Room
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('patient/queue_tracker.php?appointment_id=' . $todayAppointment['id']) ?>" class="btn btn-outline-teal rounded-pill px-3 py-2 fw-semibold w-100">
                        <i class="bi bi-hourglass-split me-1"></i> Live Queue Tracker
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
                        <span class="text-secondary small fw-semibold">Upcoming Visits</span>
                        <div class="metric-number text-teal"><?= count($upcomingAppointments) ?></div>
                    </div>
                    <div class="icon-box bg-teal-subtle text-teal">
                        <i class="bi bi-calendar2-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Active Med Reminders</span>
                        <div class="metric-number text-success"><?= count($activeReminders) ?></div>
                    </div>
                    <div class="icon-box bg-success-subtle text-success">
                        <i class="bi bi-capsule-pill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Digital Prescriptions</span>
                        <div class="metric-number text-primary"><?= count($recentPrescriptions) ?></div>
                    </div>
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-file-earmark-medical-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Health Vault Records</span>
                        <?php
                        $recordsCount = (int)Database::fetchOne("SELECT COUNT(*) as c FROM medical_records WHERE patient_id = ?", [$patientId])['c'];
                        ?>
                        <div class="metric-number text-warning"><?= $recordsCount ?></div>
                    </div>
                    <div class="icon-box bg-warning-subtle text-warning">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row g-4">
        <!-- Upcoming Appointments Table -->
        <div class="col-lg-8">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-calendar3 me-2 text-teal"></i> Scheduled Appointments</h5>
                    <a href="<?= base_url('patient/book_appointment.php') ?>" class="btn btn-sm btn-outline-teal rounded-pill">
                        + New Booking
                    </a>
                </div>

                <?php if (empty($upcomingAppointments)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                        <p class="mb-2">No upcoming consultations scheduled.</p>
                        <a href="<?= base_url('patient/book_appointment.php') ?>" class="btn btn-teal text-white btn-sm rounded-pill px-3">
                            Book Your First Visit
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small">
                                    <th>Doctor & Specialty</th>
                                    <th>Date & Slot</th>
                                    <th>Type</th>
                                    <th>Queue / Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingAppointments as $apt): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="user-avatar-badge" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                    <?= strtoupper(substr($apt['doctor_name'], 4, 1)) ?>
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-body d-block"><?= e($apt['doctor_name']) ?></span>
                                                    <small class="text-secondary"><?= e($apt['department_name']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="d-block fw-semibold"><?= date('M d, Y', strtotime($apt['appointment_date'])) ?></span>
                                            <small class="text-secondary"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($apt['appointment_type'] === 'online_consultation'): ?>
                                                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-camera-video me-1"></i> Video OPD</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-hospital me-1"></i> In-Hospital</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge bg-teal-subtle text-teal fw-bold">#<?= $apt['queue_number'] ?></span>
                                                <span class="badge badge-<?= e($apt['status']) ?>"><?= e(str_replace('_', ' ', $apt['status'])) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($apt['status'] === 'pending_payment'): ?>
                                                <a href="<?= base_url('payments/checkout.php?appointment_id=' . $apt['id']) ?>" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold">
                                                    Pay ₹<?= number_format($apt['consultation_fee'], 0) ?>
                                                </a>
                                            <?php elseif ($apt['appointment_type'] === 'online_consultation'): ?>
                                                <a href="<?= base_url('patient/video_consultation.php?room=' . urlencode($apt['meeting_room_id'])) ?>" class="btn btn-sm btn-teal text-white rounded-pill px-3">
                                                    Join Call
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= base_url('patient/queue_tracker.php?appointment_id=' . $apt['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                    Track Queue
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

        <!-- Right Side: Medication Reminders & Health Vault Quick-Access -->
        <div class="col-lg-4">
            <!-- Active Medication Reminders -->
            <div class="glass-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-alarm me-2 text-success"></i> Med Schedule</h5>
                    <a href="<?= base_url('patient/medication_reminders.php') ?>" class="text-teal small text-decoration-none fw-semibold">View All</a>
                </div>

                <?php if (empty($activeReminders)): ?>
                    <p class="text-secondary small mb-0">No active medication schedules.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($activeReminders as $med): ?>
                            <div class="p-3 rounded-3 bg-body-tertiary border mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="fw-bold mb-1 text-body"><?= e($med['medicine_name']) ?></h6>
                                    <span class="badge bg-success-subtle text-success fs-xs"><?= e($med['dosage']) ?></span>
                                </div>
                                <div class="d-flex gap-2 text-secondary fs-xs mt-1">
                                    <?php if ($med['schedule_morning']): ?><span class="badge bg-body-secondary text-secondary"><i class="bi bi-sun"></i> Morning</span><?php endif; ?>
                                    <?php if ($med['schedule_afternoon']): ?><span class="badge bg-body-secondary text-secondary"><i class="bi bi-brightness-high"></i> Noon</span><?php endif; ?>
                                    <?php if ($med['schedule_night']): ?><span class="badge bg-body-secondary text-secondary"><i class="bi bi-moon"></i> Night</span><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Prescriptions -->
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-medical me-2 text-primary"></i> Recent Prescriptions</h5>
                    <a href="<?= base_url('patient/prescriptions.php') ?>" class="text-teal small text-decoration-none fw-semibold">Vault</a>
                </div>

                <?php if (empty($recentPrescriptions)): ?>
                    <p class="text-secondary small mb-0">No digital prescriptions issued yet.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentPrescriptions as $rx): ?>
                            <a href="<?= base_url('patient/prescriptions.php?rx_id=' . $rx['id']) ?>" class="list-group-item list-group-item-action bg-transparent px-0 py-2 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <strong class="small text-body"><?= e($rx['prescription_number']) ?></strong>
                                    <small class="text-secondary"><?= date('M d', strtotime($rx['created_at'])) ?></small>
                                </div>
                                <p class="text-secondary fs-xs mb-0 text-truncate"><?= e($rx['diagnosis']) ?> (<?= e($rx['doctor_name']) ?>)</p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
