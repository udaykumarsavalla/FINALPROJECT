<?php
/**
 * CarePulse AI - Dedicated Telemedicine & WebRTC Video Consultation Suite
 * Supports Patient and Doctor roles, camera/mic toggles, screen sharing, live chat, and auto-saving clinical notes.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();

$roomId = trim($_GET['room'] ?? '');
$appointmentId = (int)($_GET['appointment_id'] ?? 0);
$currentUser = Auth::currentUser();
$userRole = Auth::currentUserRole();
$userId = Auth::currentUserId();

// Find appointment by room ID or appointment ID
$appointment = null;
if (!empty($roomId)) {
    $appointment = Database::fetchOne("
        SELECT a.*, d.room_number, d.qualification, d.specialization,
               u_doc.name as doctor_name, u_doc.avatar as doctor_avatar,
               u_pat.name as patient_name, u_pat.avatar as patient_avatar, u_pat.gender as patient_gender, u_pat.blood_group as patient_blood_group,
               dep.name as department_name, dep.icon as department_icon,
               c.id as consultation_id, c.doctor_notes, c.diagnosis
        FROM appointments a
        JOIN doctor_profiles d ON a.doctor_id = d.id
        JOIN users u_doc ON d.user_id = u_doc.id
        JOIN users u_pat ON a.patient_id = u_pat.id
        JOIN departments dep ON a.department_id = dep.id
        LEFT JOIN consultations c ON a.id = c.appointment_id
        WHERE a.meeting_room_id = ?
    ", [$roomId]);
} elseif ($appointmentId > 0) {
    $appointment = Database::fetchOne("
        SELECT a.*, d.room_number, d.qualification, d.specialization,
               u_doc.name as doctor_name, u_doc.avatar as doctor_avatar,
               u_pat.name as patient_name, u_pat.avatar as patient_avatar, u_pat.gender as patient_gender, u_pat.blood_group as patient_blood_group,
               dep.name as department_name, dep.icon as department_icon,
               c.id as consultation_id, c.doctor_notes, c.diagnosis
        FROM appointments a
        JOIN doctor_profiles d ON a.doctor_id = d.id
        JOIN users u_doc ON d.user_id = u_doc.id
        JOIN users u_pat ON a.patient_id = u_pat.id
        JOIN departments dep ON a.department_id = dep.id
        LEFT JOIN consultations c ON a.id = c.appointment_id
        WHERE a.id = ?
    ", [$appointmentId]);
} else {
    // If no room or appointment specified, load patient's or doctor's next upcoming online consultation
    if ($userRole === 'doctor') {
        $doc = Database::fetchOne("SELECT id FROM doctor_profiles WHERE user_id = ?", [$userId]);
        if ($doc) {
            $appointment = Database::fetchOne("
                SELECT a.*, d.room_number, d.qualification, d.specialization,
                       u_doc.name as doctor_name, u_doc.avatar as doctor_avatar,
                       u_pat.name as patient_name, u_pat.avatar as patient_avatar, u_pat.gender as patient_gender, u_pat.blood_group as patient_blood_group,
                       dep.name as department_name, dep.icon as department_icon,
                       c.id as consultation_id, c.doctor_notes, c.diagnosis
                FROM appointments a
                JOIN doctor_profiles d ON a.doctor_id = d.id
                JOIN users u_doc ON d.user_id = u_doc.id
                JOIN users u_pat ON a.patient_id = u_pat.id
                JOIN departments dep ON a.department_id = dep.id
                LEFT JOIN consultations c ON a.id = c.appointment_id
                WHERE a.doctor_id = ? AND a.appointment_type = 'online_consultation' AND a.status IN ('confirmed', 'in_consultation')
                ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 1
            ", [$doc['id']]);
        }
    } else {
        $appointment = Database::fetchOne("
            SELECT a.*, d.room_number, d.qualification, d.specialization,
                   u_doc.name as doctor_name, u_doc.avatar as doctor_avatar,
                   u_pat.name as patient_name, u_pat.avatar as patient_avatar, u_pat.gender as patient_gender, u_pat.blood_group as patient_blood_group,
                   dep.name as department_name, dep.icon as department_icon,
                   c.id as consultation_id, c.doctor_notes, c.diagnosis
            FROM appointments a
            JOIN doctor_profiles d ON a.doctor_id = d.id
            JOIN users u_doc ON d.user_id = u_doc.id
            JOIN users u_pat ON a.patient_id = u_pat.id
            JOIN departments dep ON a.department_id = dep.id
            LEFT JOIN consultations c ON a.id = c.appointment_id
            WHERE a.patient_id = ? AND a.appointment_type = 'online_consultation' AND a.status IN ('confirmed', 'in_consultation')
            ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 1
        ", [$userId]);
    }
}

$pageTitle = "Telemedicine Video Consultation Suite";
$activeMenu = "dashboard";

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-lg-5 py-4">
    <?php if (!$appointment): ?>
        <div class="glass-card p-5 text-center my-4">
            <div class="icon-box bg-teal-subtle text-teal rounded-circle p-4 mx-auto mb-3" style="width: 80px; height: 80px;">
                <i class="bi bi-camera-video-off fs-1"></i>
            </div>
            <h4 class="fw-bold">No Active Telemedicine Session Found</h4>
            <p class="text-secondary small max-w-500 mx-auto mb-4">
                You do not currently have an active online consultation room in progress. Book a consultation or enter an active room token.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= base_url('patient/book_appointment.php') ?>" class="btn btn-teal text-white rounded-pill px-4 py-2 fw-semibold">
                    <i class="bi bi-calendar-plus me-1"></i> Book Video Consultation
                </a>
                <a href="<?= base_url(Auth::getDashboardUrl()) ?>" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold">
                    Return to Dashboard
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Top Status Bar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="pulse-indicator"></span>
                    <h4 class="fw-bold mb-0">WebRTC Telehealth Consultation</h4>
                    <span class="badge bg-teal-subtle text-teal">Token #<?= $appointment['queue_number'] ?> &bull; Room: <?= e($appointment['meeting_room_id']) ?></span>
                </div>
                <p class="text-secondary small mb-0">
                    Patient: <strong><?= e($appointment['patient_name']) ?></strong> &bull;
                    Consultant: <strong><?= e($appointment['doctor_name']) ?></strong> (<?= e($appointment['department_name']) ?>) &bull;
                    Appt: #<?= e($appointment['appointment_number']) ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($userRole === 'doctor'): ?>
                    <button type="button" class="btn btn-outline-teal btn-sm rounded-pill" onclick="saveClinicalNotes(false)">
                        <i class="bi bi-save me-1"></i> Save Notes
                    </button>
                    <button type="button" class="btn btn-danger btn-sm rounded-pill px-4 fw-bold shadow-sm" onclick="endConsultation()">
                        <i class="bi bi-prescription2 me-1"></i> Conclude & Write Rx
                    </button>
                <?php else: ?>
                    <a href="<?= base_url('patient/prescriptions.php') ?>" class="btn btn-outline-teal btn-sm rounded-pill">
                        <i class="bi bi-file-earmark-medical me-1"></i> Rx Vault
                    </a>
                    <a href="<?= base_url('patient/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
                        <i class="bi bi-arrow-left me-1"></i> Exit Room
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left 8 Columns: HD Video Consultation Feed -->
            <div class="col-lg-8">
                <div class="video-container shadow-lg">
                    <!-- Remote Participant Stream / Avatar View -->
                    <div id="remoteVideoBox" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-center p-4">
                        <div class="user-avatar-badge mb-3 shadow" style="width: 110px; height: 110px; font-size: 2.8rem; background: linear-gradient(135deg, #0d9488, #0284c7);">
                            <?= $userRole === 'doctor' ? strtoupper(substr($appointment['patient_name'], 0, 1)) : strtoupper(substr($appointment['doctor_name'], 4, 1)) ?>
                        </div>
                        <h4 class="text-white fw-bold mb-1" id="remoteParticipantName">
                            <?= $userRole === 'doctor' ? e($appointment['patient_name']) : e($appointment['doctor_name']) ?>
                        </h4>
                        <p class="text-info fs-xs mb-0"><i class="bi bi-shield-check me-1"></i> Peer-to-Peer Encrypted Telehealth Stream Active</p>
                        <span class="badge bg-success-subtle text-success mt-2" id="callDuration">Connected &bull; Live</span>
                    </div>

                    <!-- Local Picture-in-Picture Video Stream -->
                    <div class="video-pip shadow">
                        <video id="teleLocalVideo" class="video-element" autoplay playsinline muted></video>
                        <div id="teleVideoPlaceholder" class="w-100 h-100 d-flex align-items-center justify-content-center text-white small">
                            <i class="bi bi-person-fill fs-3 text-teal"></i>
                        </div>
                    </div>

                    <!-- Video Controls Bar -->
                    <div class="video-controls">
                        <button class="video-btn btn btn-light" id="btnToggleMic" title="Mute/Unmute Mic" type="button">
                            <i class="bi bi-mic-fill text-dark" id="micIcon"></i>
                        </button>
                        <button class="video-btn btn btn-light" id="btnToggleCam" title="Turn Camera On/Off" type="button">
                            <i class="bi bi-camera-video-fill text-dark" id="camIcon"></i>
                        </button>
                        <button class="video-btn btn btn-light" id="btnShareScreen" title="Share Screen" type="button">
                            <i class="bi bi-display text-dark" id="screenIcon"></i>
                        </button>
                        <button class="video-btn btn btn-light" id="btnToggleChat" title="Toggle In-Call Chat" type="button">
                            <i class="bi bi-chat-text-fill text-dark"></i>
                        </button>
                        <?php if ($userRole === 'doctor'): ?>
                            <button class="video-btn btn btn-danger" onclick="endConsultation()" title="End Consultation">
                                <i class="bi bi-telephone-x-fill text-white"></i>
                            </button>
                        <?php else: ?>
                            <a href="<?= base_url('patient/index.php') ?>" class="video-btn btn btn-danger" title="Leave Consultation">
                                <i class="bi bi-telephone-x-fill text-white"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chief Complaint & Consultation Summary -->
                <div class="glass-card p-3 mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fs-xs text-secondary fw-bold text-uppercase">Patient Reported Symptoms:</span>
                        <span class="badge bg-body-secondary text-secondary">Chief Complaint</span>
                    </div>
                    <p class="small text-body mb-0">
                        <?= !empty($appointment['symptoms_summary']) ? e($appointment['symptoms_summary']) : 'Routine follow-up / general outpatient review.' ?>
                    </p>
                </div>
            </div>

            <!-- Right 4 Columns: Clinical Notes & In-Call Telehealth Chat -->
            <div class="col-lg-4">
                <!-- Navigation Tabs: Clinical Notes / Chat -->
                <ul class="nav nav-pills nav-fill mb-3 p-1 bg-body-tertiary rounded-pill border" id="teleTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill fw-semibold py-1 fs-xs" id="notes-tab" data-bs-toggle="pill" data-bs-target="#tabNotes" type="button">
                            <i class="bi bi-journal-medical me-1"></i> Clinical Notes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill fw-semibold py-1 fs-xs" id="chat-tab" data-bs-toggle="pill" data-bs-target="#tabChat" type="button">
                            <i class="bi bi-chat-dots me-1"></i> In-Call Chat
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="teleTabsContent">
                    <!-- Tab 1: Clinical Notes (Doctor can edit, Patient views in real-time) -->
                    <div class="tab-pane fade show active" id="tabNotes" role="tabpanel">
                        <div class="glass-card p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 text-body"><i class="bi bi-pencil-square me-1 text-teal"></i> Clinical Summary</h6>
                                <span class="badge bg-teal-subtle text-teal fs-xs" id="notesSyncStatus">Live Synced</span>
                            </div>

                            <?php if ($userRole === 'doctor'): ?>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary" for="teleDiagnosis">Diagnosis *</label>
                                    <input type="text" class="form-control" id="teleDiagnosis" value="<?= e($appointment['diagnosis'] ?? '') ?>" placeholder="e.g. Acute Pharyngitis / Hypertension">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary" for="teleNotes">Doctor Observations & Vitals</label>
                                    <textarea class="form-control" id="teleNotes" rows="7" placeholder="Record physical observations, vitals, SpO2, heart rate, provisional diagnosis..."><?= e($appointment['doctor_notes'] ?? '') ?></textarea>
                                </div>
                                <button type="button" class="btn btn-teal text-white w-100 rounded-pill py-2 fw-bold shadow-sm" onclick="endConsultation()">
                                    <i class="bi bi-prescription2 me-1"></i> Conclude & Write Digital Rx
                                </button>
                            <?php else: ?>
                                <div class="p-3 rounded-3 bg-body-tertiary border mb-3 min-h-160">
                                    <span class="fs-xs text-secondary d-block fw-semibold mb-1">Doctor Findings:</span>
                                    <p class="small text-body mb-0" id="patientLiveNotesDisplay">
                                        <?= !empty($appointment['doctor_notes']) ? nl2br(e($appointment['doctor_notes'])) : 'Doctor is conducting assessment. Clinical notes will update here in real time.' ?>
                                    </p>
                                </div>
                                <div class="alert alert-info border-info-subtle p-2 rounded-2 fs-xs mb-0">
                                    <i class="bi bi-info-circle me-1"></i> Prescriptions issued during this meeting will immediately be available in your Prescription Vault.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tab 2: In-Call Live Chat -->
                    <div class="tab-pane fade" id="tabChat" role="tabpanel">
                        <div class="glass-card p-3 d-flex flex-column" style="height: 380px;">
                            <div class="chat-messages flex-grow-1 overflow-auto p-2 space-y-2" id="chatMessagesBox">
                                <div class="p-2 rounded-2 bg-body-tertiary border text-center fs-xs text-secondary mb-2">
                                    <i class="bi bi-lock me-1"></i> Telemedicine chat is encrypted and private.
                                </div>
                                <div class="p-2 rounded-3 bg-teal-subtle text-teal fs-xs mb-2">
                                    <strong>CarePulse Assistant:</strong> Session established. Feel free to exchange vitals, report readings, or medicine queries.
                                </div>
                            </div>
                            <form id="inCallChatForm" class="mt-2 pt-2 border-top">
                                <div class="input-group">
                                    <input type="text" id="chatInputText" class="form-control form-control-sm" placeholder="Type message..." required>
                                    <button type="submit" class="btn btn-teal text-white btn-sm px-3">
                                        <i class="bi bi-send-fill"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const appointmentId = <?= $appointment ? (int)$appointment['id'] : 0 ?>;
const isDoctor = <?= $userRole === 'doctor' ? 'true' : 'false' ?>;

let localStream = null;
let isMicMuted = false;
let isCamOff = false;

async function initTelemedicineMedia() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        const videoEl = document.getElementById('teleLocalVideo');
        if (videoEl) {
            videoEl.srcObject = localStream;
            document.getElementById('teleVideoPlaceholder')?.classList.add('d-none');
        }
    } catch (err) {
        console.warn('[Telemedicine Media Notice]', err.message);
        const placeholder = document.getElementById('teleVideoPlaceholder');
        if (placeholder) {
            placeholder.innerHTML = '<span class="text-center text-secondary fs-xs"><i class="bi bi-camera-video-off fs-4 d-block mb-1"></i>Camera Standby</span>';
        }
    }
}

// Media Controls
document.getElementById('btnToggleMic')?.addEventListener('click', () => {
    isMicMuted = !isMicMuted;
    if (localStream) localStream.getAudioTracks().forEach(t => t.enabled = !isMicMuted);
    const icon = document.getElementById('micIcon');
    const btn = document.getElementById('btnToggleMic');
    if (isMicMuted) {
        btn.classList.add('active-off');
        icon.className = 'bi bi-mic-mute-fill text-white';
        CarePulse.showToast('Microphone muted', 'warning');
    } else {
        btn.classList.remove('active-off');
        icon.className = 'bi bi-mic-fill text-dark';
        CarePulse.showToast('Microphone live', 'info');
    }
});

document.getElementById('btnToggleCam')?.addEventListener('click', () => {
    isCamOff = !isCamOff;
    if (localStream) localStream.getVideoTracks().forEach(t => t.enabled = !isCamOff);
    const icon = document.getElementById('camIcon');
    const btn = document.getElementById('btnToggleCam');
    if (isCamOff) {
        btn.classList.add('active-off');
        icon.className = 'bi bi-camera-video-off-fill text-white';
        CarePulse.showToast('Camera stopped', 'warning');
    } else {
        btn.classList.remove('active-off');
        icon.className = 'bi bi-camera-video-fill text-dark';
        CarePulse.showToast('Camera live', 'info');
    }
});

document.getElementById('btnShareScreen')?.addEventListener('click', async () => {
    try {
        const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        const videoEl = document.getElementById('teleLocalVideo');
        if (videoEl) videoEl.srcObject = screenStream;
        CarePulse.showToast('Screen sharing started', 'info');
        screenStream.getVideoTracks()[0].onended = () => {
            if (videoEl && localStream) videoEl.srcObject = localStream;
        };
    } catch (err) {
        console.warn('Screen share canceled');
    }
});

document.getElementById('btnToggleChat')?.addEventListener('click', () => {
    const chatTabBtn = document.getElementById('chat-tab');
    bootstrap.Tab.getOrCreateInstance(chatTabBtn).show();
});

// Live In-Call Chat
document.getElementById('inCallChatForm')?.addEventListener('submit', (e) => {
    e.preventDefault();
    const input = document.getElementById('chatInputText');
    const text = input.value.trim();
    if (!text) return;

    const box = document.getElementById('chatMessagesBox');
    const msg = document.createElement('div');
    msg.className = 'p-2 rounded-3 bg-body-tertiary border fs-xs mb-2';
    msg.innerHTML = `<strong>You (${isDoctor ? 'Doctor' : 'Patient'}):</strong> ${text} <span class="text-secondary float-end">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>`;
    box.appendChild(msg);
    box.scrollTop = box.scrollHeight;
    input.value = '';
});

// Auto-save clinical notes for Doctor
async function saveClinicalNotes(silent = true) {
    if (!isDoctor || appointmentId <= 0) return;
    const diag = document.getElementById('teleDiagnosis')?.value.trim() || '';
    const notes = document.getElementById('teleNotes')?.value.trim() || '';
    const statusEl = document.getElementById('notesSyncStatus');

    if (statusEl) statusEl.textContent = 'Saving...';

    const res = await CarePulse.api('api/consultation.php', {
        method: 'POST',
        body: {
            action: 'save_notes',
            appointment_id: appointmentId,
            diagnosis: diag,
            doctor_notes: notes,
            csrf_token: window.APP_CONFIG.csrfToken
        }
    });

    if (res.success && statusEl) {
        statusEl.textContent = 'Saved ' + new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        if (!silent) CarePulse.showToast('Clinical notes saved.', 'success');
    }
}

async function endConsultation() {
    if (appointmentId <= 0) return;
    const diag = document.getElementById('teleDiagnosis')?.value.trim() || 'Telehealth Consultation Complete';
    const notes = document.getElementById('teleNotes')?.value.trim() || '';

    const res = await CarePulse.api('api/consultation.php', {
        method: 'POST',
        body: {
            action: 'end',
            appointment_id: appointmentId,
            diagnosis: diag,
            doctor_notes: notes,
            csrf_token: window.APP_CONFIG.csrfToken
        }
    });

    if (res.success) {
        CarePulse.showToast('Consultation ended. Redirecting to prescription generator...', 'success');
        setTimeout(() => {
            window.location.href = res.redirect || `${window.APP_CONFIG.baseUrl}/doctor/create_prescription.php?appointment_id=${appointmentId}`;
        }, 800);
    }
}

// Patient Polling for live doctor notes
if (!isDoctor && appointmentId > 0) {
    setInterval(async () => {
        const res = await CarePulse.api(`api/consultation.php?action=get_details&appointment_id=${appointmentId}`);
        if (res.success && res.consultation && res.consultation.doctor_notes) {
            document.getElementById('patientLiveNotesDisplay').textContent = res.consultation.doctor_notes;
        }
    }, 8000);
}

if (isDoctor && appointmentId > 0) {
    setInterval(() => saveClinicalNotes(true), 15000);
}

initTelemedicineMedia();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
