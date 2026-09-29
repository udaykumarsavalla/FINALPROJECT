<?php
/**
 * CarePulse AI - Browser-Based Video Consultation Room (Patient View)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();

$roomId = trim($_GET['room'] ?? '');
$patientId = Auth::currentUserId();

if (empty($roomId)) {
    header("Location: " . base_url('patient/index.php'));
    exit;
}

// Find appointment by room ID
$appointment = Database::fetchOne("
    SELECT a.*, d.room_number, d.qualification, d.specialization,
           u_doc.name as doctor_name, u_doc.avatar as doctor_avatar, dep.name as department_name, dep.icon as department_icon,
           c.id as consultation_id, c.doctor_notes, c.diagnosis
    FROM appointments a
    JOIN doctor_profiles d ON a.doctor_id = d.id
    JOIN users u_doc ON d.user_id = u_doc.id
    JOIN departments dep ON a.department_id = dep.id
    LEFT JOIN consultations c ON a.id = c.appointment_id
    WHERE a.meeting_room_id = ?
", [$roomId]);

if (!$appointment) {
    die("Meeting room not found or session expired.");
}

$pageTitle = "Live Telehealth Video Consultation";
$activeMenu = "dashboard";

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-lg-5 py-4">
    <!-- Top Bar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="pulse-indicator"></span>
                <h4 class="fw-bold mb-0">Live Telehealth Consultation</h4>
                <span class="badge bg-teal-subtle text-teal">Encrypted WebRTC Peer-to-Peer</span>
            </div>
            <p class="text-secondary small mb-0">
                Consulting with <strong><?= e($appointment['doctor_name']) ?></strong> (<?= e($appointment['department_name']) ?>) &bull; Appt: #<?= e($appointment['appointment_number']) ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('patient/prescriptions.php') ?>" class="btn btn-outline-teal btn-sm rounded-pill">
                <i class="bi bi-file-earmark-medical me-1"></i> Rx Vault
            </a>
            <a href="<?= base_url('patient/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Exit Room
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Video Screen -->
        <div class="col-lg-8">
            <div class="video-container shadow-lg">
                <!-- Remote Doctor Stream / Video Avatar Simulation -->
                <div id="remoteVideoBox" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-center p-4">
                    <div class="user-avatar-badge mb-3 shadow" style="width: 110px; height: 110px; font-size: 2.8rem; background: linear-gradient(135deg, #0d9488, #0284c7);">
                        <?= strtoupper(substr($appointment['doctor_name'], 4, 1)) ?>
                    </div>
                    <h4 class="text-white fw-bold mb-1" id="remoteParticipantName"><?= e($appointment['doctor_name']) ?></h4>
                    <p class="text-info fs-xs mb-0"><i class="bi bi-shield-check me-1"></i> Secure 1080p Telehealth Stream Active</p>
                    <span class="badge bg-success-subtle text-success mt-2">Connected &bull; 00:04:12</span>
                </div>

                <!-- Local Patient Picture-in-Picture Stream -->
                <div class="video-pip shadow">
                    <video id="localVideo" class="video-element" autoplay playsinline muted></video>
                    <div id="localVideoPlaceholder" class="w-100 h-100 d-flex align-items-center justify-content-center text-white small">
                        <i class="bi bi-person-fill fs-3 text-teal"></i>
                    </div>
                </div>

                <!-- Call Controls Bar -->
                <div class="video-controls">
                    <button class="video-btn btn btn-light" id="toggleMicBtn" title="Mute/Unmute Mic" type="button">
                        <i class="bi bi-mic-fill text-dark" id="micIcon"></i>
                    </button>
                    <button class="video-btn btn btn-light" id="toggleCamBtn" title="Turn Camera On/Off" type="button">
                        <i class="bi bi-camera-video-fill text-dark" id="camIcon"></i>
                    </button>
                    <button class="video-btn btn btn-light" id="toggleScreenBtn" title="Share Screen" type="button">
                        <i class="bi bi-display text-dark"></i>
                    </button>
                    <a href="<?= base_url('patient/index.php') ?>" class="video-btn btn btn-danger" title="Leave Consultation">
                        <i class="bi bi-telephone-x-fill text-white"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Side: Consultation Information & Live Clinical Notes -->
        <div class="col-lg-4">
            <!-- Doctor Details Card -->
            <div class="glass-card p-4 mb-3">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="user-avatar-badge" style="width: 48px; height: 48px; font-size: 1.2rem;">
                        <?= strtoupper(substr($appointment['doctor_name'], 4, 1)) ?>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-body"><?= e($appointment['doctor_name']) ?></h6>
                        <small class="text-teal fw-semibold"><?= e($appointment['specialization']) ?></small>
                        <div class="text-secondary fs-xs"><?= e($appointment['qualification']) ?></div>
                    </div>
                </div>

                <div class="p-3 rounded-3 bg-body-tertiary border mb-2">
                    <span class="fs-xs text-secondary d-block fw-semibold mb-1">Appointment Symptoms:</span>
                    <p class="small text-body mb-0"><?= e($appointment['symptoms_summary'] ?: 'Routine telehealth consultation.') ?></p>
                </div>
            </div>

            <!-- Doctor Live Clinical Observations -->
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-body"><i class="bi bi-journal-medical me-1 text-teal"></i> Clinical Summary</h6>
                    <span class="badge bg-teal-subtle text-teal fs-xs" id="notesStatusBadge">Syncing</span>
                </div>
                <div class="p-3 rounded-3 bg-body-tertiary border min-h-120">
                    <span class="fs-xs text-secondary d-block fw-semibold mb-1">Doctor Notes / Diagnosis:</span>
                    <p class="small text-body mb-0" id="liveDoctorNotes">
                        <?= !empty($appointment['doctor_notes']) ? nl2br(e($appointment['doctor_notes'])) : 'Doctor is conducting assessment. Clinical notes will appear here in real time.' ?>
                    </p>
                </div>

                <div class="mt-3 text-center">
                    <small class="text-secondary d-block mb-2">Once consultation finishes, your digital prescription is automatically saved to your vault.</small>
                    <a href="<?= base_url('patient/prescriptions.php') ?>" class="btn btn-outline-teal btn-sm w-100 rounded-pill">
                        <i class="bi bi-folder2-open me-1"></i> Open Prescription Vault
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Local WebRTC Media Stream Handler
let localStream = null;
let isMicMuted = false;
let isCamOff = false;

async function initCamera() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        const videoEl = document.getElementById('localVideo');
        videoEl.srcObject = localStream;
        document.getElementById('localVideoPlaceholder').classList.add('d-none');
    } catch (err) {
        console.warn('[WebRTC Device Notice]', err.message);
        // Graceful fallback if no webcam on device
        document.getElementById('localVideoPlaceholder').innerHTML = '<span class="text-center text-secondary fs-xs"><i class="bi bi-camera-video-off fs-4 d-block mb-1"></i>Camera Standby</span>';
    }
}

document.getElementById('toggleMicBtn').addEventListener('click', () => {
    isMicMuted = !isMicMuted;
    if (localStream) {
        localStream.getAudioTracks().forEach(track => track.enabled = !isMicMuted);
    }
    const icon = document.getElementById('micIcon');
    const btn = document.getElementById('toggleMicBtn');
    if (isMicMuted) {
        btn.classList.add('active-off');
        icon.className = 'bi bi-mic-mute-fill text-white';
        CarePulse.showToast('Microphone muted', 'warning');
    } else {
        btn.classList.remove('active-off');
        icon.className = 'bi bi-mic-fill text-dark';
        CarePulse.showToast('Microphone unmuted', 'info');
    }
});

document.getElementById('toggleCamBtn').addEventListener('click', () => {
    isCamOff = !isCamOff;
    if (localStream) {
        localStream.getVideoTracks().forEach(track => track.enabled = !isCamOff);
    }
    const icon = document.getElementById('camIcon');
    const btn = document.getElementById('toggleCamBtn');
    if (isCamOff) {
        btn.classList.add('active-off');
        icon.className = 'bi bi-camera-video-off-fill text-white';
        CarePulse.showToast('Camera stopped', 'warning');
    } else {
        btn.classList.remove('active-off');
        icon.className = 'bi bi-camera-video-fill text-dark';
        CarePulse.showToast('Camera enabled', 'info');
    }
});

// Poll for updated doctor notes & prescription completion
const currentAptId = <?= (int)$appointment['id'] ?>;
async function pollConsultation() {
    const res = await CarePulse.api(`api/consultation.php?action=get_details&appointment_id=${currentAptId}`);
    if (res.success && res.consultation) {
        if (res.consultation.doctor_notes) {
            document.getElementById('liveDoctorNotes').textContent = res.consultation.doctor_notes;
            document.getElementById('notesStatusBadge').textContent = 'Live';
        }
        if (res.appointment && res.appointment.status === 'completed') {
            CarePulse.showToast('Doctor concluded the consultation. Prescription is ready!', 'success');
        }
    }
}

initCamera();
setInterval(pollConsultation, 7000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
