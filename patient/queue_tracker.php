<?php
/**
 * CarePulse AI - Smart Live Queue & Dynamic Wait-Time Tracker
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/queue_service.php';

Auth::requireAuth(['patient']);

$pageTitle = "Smart Queue Tracker";
$activeMenu = "queue";
$patientId = Auth::currentUserId();

$appointmentId = (int)($_GET['appointment_id'] ?? 0);

// If no appointmentId passed, find patient's most immediate active appointment
if ($appointmentId <= 0) {
    $activeApt = Database::fetchOne("
        SELECT id FROM appointments 
        WHERE patient_id = ? AND appointment_date >= CURDATE() AND status NOT IN ('cancelled', 'completed', 'pending_payment')
        ORDER BY appointment_date ASC, appointment_time ASC LIMIT 1
    ", [$patientId]);

    if ($activeApt) {
        $appointmentId = (int)$activeApt['id'];
    }
}

$appointment = null;
$queueInfo = null;

if ($appointmentId > 0) {
    $appointment = Database::fetchOne("
        SELECT a.*, d.room_number, d.avg_consult_time_mins,
               u.name as doctor_name, u.avatar as doctor_avatar, dep.name as department_name, dep.icon as department_icon
        FROM appointments a
        JOIN doctor_profiles d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        JOIN departments dep ON a.department_id = dep.id
        WHERE a.id = ? AND a.patient_id = ?
    ", [$appointmentId, $patientId]);

    if ($appointment) {
        $queueInfo = QueueService::calculateEstimatedWaitTime($appointmentId);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                        <span class="pulse-indicator me-1"></span> Live Dynamic Telehealth Telemetry
                    </span>
                    <h2 class="display-6 fw-bold mb-1">Smart Queue Tracker</h2>
                    <p class="text-secondary small mb-0">Dynamic wait times calculated in real time using doctor consultation pace and patient queue depth.</p>
                </div>
                <button type="button" class="btn btn-outline-teal btn-sm rounded-pill" onclick="refreshQueue(true)">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>

            <?php if (!$appointment): ?>
                <div class="glass-card p-5 text-center">
                    <i class="bi bi-calendar-x text-muted display-4 d-block mb-3"></i>
                    <h4 class="fw-bold">No Active Queue Session Found</h4>
                    <p class="text-secondary small mb-4">You do not have a confirmed appointment in the hospital queue today.</p>
                    <a href="<?= base_url('patient/book_appointment.php') ?>" class="btn btn-teal text-white rounded-pill px-4 py-2 fw-semibold">
                        <i class="bi bi-calendar-plus me-1"></i> Book an Appointment
                    </a>
                </div>
            <?php else: ?>
                <!-- Live Queue Card -->
                <div class="glass-card p-4 p-md-5 mb-4 border border-teal shadow-lg">
                    <div class="row align-items-center g-4 text-center text-md-start">
                        <!-- Patient Token Circle -->
                        <div class="col-md-3 text-center border-end-md">
                            <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-2">Your OPD Token</span>
                            <div class="queue-token-circle mx-auto" style="width: 100px; height: 100px;">
                                <span class="token-lbl">TOKEN</span>
                                <span class="token-num" style="font-size: 2.2rem;">#<?= $appointment['queue_number'] ?></span>
                            </div>
                            <span class="badge badge-<?= e($appointment['status']) ?> mt-3 text-uppercase">
                                <?= e(str_replace('_', ' ', $appointment['status'])) ?>
                            </span>
                        </div>

                        <!-- Queue Status Metrics -->
                        <div class="col-md-5">
                            <h4 class="fw-bold mb-1 text-body" id="docNameDisplay"><?= e($appointment['doctor_name']) ?></h4>
                            <p class="text-teal small fw-semibold mb-3">
                                <i class="bi <?= e($appointment['department_icon']) ?> me-1"></i> <?= e($appointment['department_name']) ?> &bull; <?= e($appointment['room_number']) ?>
                            </p>

                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="p-3 rounded-3 bg-body-tertiary border text-center">
                                        <span class="fs-xs text-secondary d-block">Currently Serving</span>
                                        <span class="fs-3 fw-extrabold text-teal" id="liveServingToken">#<?= $queueInfo['current_serving_token'] ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 rounded-3 bg-body-tertiary border text-center">
                                        <span class="fs-xs text-secondary d-block">Patients Ahead</span>
                                        <span class="fs-3 fw-extrabold text-body" id="livePatientsAhead"><?= $queueInfo['patients_ahead'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Wait Time Countdown -->
                        <div class="col-md-4 text-center border-start-md">
                            <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-1">Estimated Wait Time</span>
                            <div class="display-4 fw-extrabold text-teal mb-1" id="liveWaitTimeDisplay">
                                <?= $queueInfo['estimated_wait_minutes'] ?><span class="fs-5 fw-normal text-secondary"> mins</span>
                            </div>
                            <small class="text-secondary d-block mb-3" id="livePaceNote">Based on ~<?= $appointment['avg_consult_time_mins'] ?> mins/consult</small>

                            <?php if ($appointment['appointment_type'] === 'online_consultation'): ?>
                                <a href="<?= base_url('patient/video_consultation.php?room=' . urlencode($appointment['meeting_room_id'])) ?>" class="btn btn-teal text-white w-100 rounded-pill py-2 fw-bold shadow-sm" id="videoRoomBtn">
                                    <i class="bi bi-camera-video me-1"></i> Join Video Room
                                </a>
                            <?php else: ?>
                                <div class="p-2 rounded-2 bg-body-secondary text-secondary fs-xs text-center">
                                    <i class="bi bi-geo-alt-fill text-teal"></i> Please wait near <?= e($appointment['room_number']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Live Progress Bar -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between text-secondary fs-xs mb-1">
                            <span>Token Progress</span>
                            <span id="queueProgressText">Updating live...</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-teal" id="queueProgressBar" style="width: 50%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Guidance Tips -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-body-tertiary border h-100">
                            <i class="bi bi-bell text-teal fs-4"></i>
                            <h6 class="fw-bold mt-2 mb-1">Automated Call Alert</h6>
                            <p class="text-secondary fs-xs mb-0">When your token is 1 position away, an alert chime will sound in your browser.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-body-tertiary border h-100">
                            <i class="bi bi-camera-video text-primary fs-4"></i>
                            <h6 class="fw-bold mt-2 mb-1">Online Video Ready</h6>
                            <p class="text-secondary fs-xs mb-0">For teleconsultations, keep your camera and mic permissions enabled.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-body-tertiary border h-100">
                            <i class="bi bi-file-earmark-medical text-success fs-4"></i>
                            <h6 class="fw-bold mt-2 mb-1">Rx Vault Sync</h6>
                            <p class="text-secondary fs-xs mb-0">Your doctor will push digital prescriptions directly to your vault upon call completion.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const currentAptId = <?= $appointmentId ?>;

async function refreshQueue(manual = false) {
    if (currentAptId <= 0) return;

    const res = await CarePulse.api(`api/queue.php?appointment_id=${currentAptId}`);
    if (res.success && res.queue) {
        const q = res.queue;
        document.getElementById('liveServingToken').textContent = `#${q.current_serving_token}`;
        document.getElementById('livePatientsAhead').textContent = q.patients_ahead;
        document.getElementById('liveWaitTimeDisplay').innerHTML = `${q.estimated_wait_minutes}<span class="fs-5 fw-normal text-secondary"> mins</span>`;

        // Calculate progress percentage
        const myNum = q.queue_number;
        const currentServing = q.current_serving_token;
        const percent = Math.min(100, Math.max(10, Math.round((currentServing / myNum) * 100)));
        document.getElementById('queueProgressBar').style.width = `${percent}%`;
        document.getElementById('queueProgressText').textContent = `${percent}% towards consultation`;

        if (q.status === 'in_consultation') {
            document.getElementById('liveWaitTimeDisplay').innerHTML = `<span class="text-success fs-2">Now Serving!</span>`;
            CarePulse.showToast('Your turn! Doctor is waiting for you in the consultation room.', 'success');
        }

        if (manual) {
            CarePulse.showToast('Queue status updated.', 'info');
        }
    }
}

// Live polling every 8 seconds
if (currentAptId > 0) {
    refreshQueue();
    setInterval(refreshQueue, 8000);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
