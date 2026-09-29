<?php
/**
 * CarePulse AI - Doctor Video Consultation & Clinical Workbench
 * Real-time WebRTC video room, patient history drawer, live clinical notes auto-save, and prescription trigger.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['doctor']);

$appointmentId = (int)($_GET['appointment_id'] ?? 0);
$userId = Auth::currentUserId();

if ($appointmentId <= 0) {
    header("Location: " . base_url('doctor/appointments.php'));
    exit;
}

$docProfile = Database::fetchOne("SELECT id FROM doctor_profiles WHERE user_id = ?", [$userId]);
$doctorId = (int)$docProfile['id'];

// Fetch appointment and patient details
$appointment = Database::fetchOne("
    SELECT a.*, u.name as patient_name, u.email as patient_email, u.phone as patient_phone,
           u.gender as patient_gender, u.dob as patient_dob, u.blood_group as patient_blood_group,
           dep.name as department_name, dep.icon as department_icon
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    JOIN departments dep ON a.department_id = dep.id
    WHERE a.id = ? AND a.doctor_id = ?
", [$appointmentId, $doctorId]);

if (!$appointment) {
    die("Appointment record not found or unauthorized.");
}

// Fetch or create consultation record
$consultation = Database::fetchOne("SELECT * FROM consultations WHERE appointment_id = ?", [$appointmentId]);
if (!$consultation) {
    $consultId = Database::insert("
        INSERT INTO consultations (appointment_id, doctor_id, patient_id, meeting_room_id, started_at)
        VALUES (?, ?, ?, ?, NOW())
    ", [$appointmentId, $doctorId, $appointment['patient_id'], $appointment['meeting_room_id']]);
    $consultation = Database::fetchOne("SELECT * FROM consultations WHERE id = ?", [$consultId]);
    Database::execute("UPDATE appointments SET status = 'in_consultation' WHERE id = ?", [$appointmentId]);
} elseif ($appointment['status'] !== 'in_consultation' && $appointment['status'] !== 'completed') {
    Database::execute("UPDATE appointments SET status = 'in_consultation' WHERE id = ?", [$appointmentId]);
}

// Fetch past medical prescriptions and vault records of this patient
$patientHistoryRx = Database::fetchAll("
    SELECT p.*, u.name as doctor_name
    FROM prescriptions p
    JOIN doctor_profiles d ON p.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC LIMIT 4
", [$appointment['patient_id']]);

$patientRecords = Database::fetchAll("
    SELECT * FROM medical_records WHERE patient_id = ? ORDER BY created_at DESC LIMIT 5
", [$appointment['patient_id']]);

$pageTitle = "Clinical Consultation Desk - " . $appointment['patient_name'];
$activeMenu = "doc_appointments";

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-lg-5 py-4">
    <!-- Top Action Bar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="pulse-indicator"></span>
                <h4 class="fw-bold mb-0">Active Consultation Desk: <?= e($appointment['patient_name']) ?></h4>
                <span class="badge bg-teal-subtle text-teal">Token #<?= $appointment['queue_number'] ?></span>
            </div>
            <p class="text-secondary small mb-0">
                <?= ucfirst($appointment['patient_gender']) ?> &bull; Blood: <?= e($appointment['patient_blood_group'] ?? 'O+') ?> &bull; Phone: <?= e($appointment['patient_phone']) ?> &bull; Mode: <strong><?= ucwords(str_replace('_', ' ', $appointment['appointment_type'])) ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-teal btn-sm rounded-pill" onclick="saveClinicalNotes(false)">
                <i class="bi bi-save me-1"></i> Save Notes
            </button>
            <button type="button" class="btn btn-danger btn-sm rounded-pill px-4 fw-bold shadow-sm" onclick="concludeConsultation()">
                <i class="bi bi-check2-circle me-1"></i> End Call & Prescribe
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: HD WebRTC Video Call Screen -->
        <div class="col-lg-7">
            <div class="video-container shadow-lg mb-3">
                <!-- Patient Video Stream / Avatar Screen -->
                <div id="patientVideoStreamBox" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-center p-4">
                    <div class="user-avatar-badge mb-3 shadow" style="width: 100px; height: 100px; font-size: 2.5rem; background: linear-gradient(135deg, #0284c7, #0d9488);">
                        <?= strtoupper(substr($appointment['patient_name'], 0, 1)) ?>
                    </div>
                    <h4 class="text-white fw-bold mb-1"><?= e($appointment['patient_name']) ?></h4>
                    <p class="text-info fs-xs mb-0"><i class="bi bi-camera-video me-1"></i> Encrypted High-Definition Telemedicine Stream</p>
                    <span class="badge bg-success-subtle text-success mt-2">Patient Connected</span>
                </div>

                <!-- Doctor PIP Local Camera -->
                <div class="video-pip shadow">
                    <video id="doctorLocalVideo" class="video-element" autoplay playsinline muted></video>
                    <div id="doctorVideoPlaceholder" class="w-100 h-100 d-flex align-items-center justify-content-center text-white small">
                        <i class="bi bi-person-badge fs-3 text-teal"></i>
                    </div>
                </div>

                <!-- Call Controls -->
                <div class="video-controls">
                    <button class="video-btn btn btn-light" id="docMicBtn" title="Mute/Unmute Mic" type="button">
                        <i class="bi bi-mic-fill text-dark" id="docMicIcon"></i>
                    </button>
                    <button class="video-btn btn btn-light" id="docCamBtn" title="Camera On/Off" type="button">
                        <i class="bi bi-camera-video-fill text-dark" id="docCamIcon"></i>
                    </button>
                    <button class="video-btn btn btn-light" id="docScreenBtn" title="Share Screen" type="button">
                        <i class="bi bi-display text-dark"></i>
                    </button>
                    <button class="video-btn btn btn-danger" onclick="concludeConsultation()" title="Conclude Consultation">
                        <i class="bi bi-telephone-x-fill text-white"></i>
                    </button>
                </div>
            </div>

            <!-- Patient Reported Symptoms Box -->
            <div class="glass-card p-3">
                <span class="fs-xs text-secondary fw-bold text-uppercase d-block mb-1">Patient Chief Complaint / Symptoms:</span>
                <p class="small text-body mb-0">
                    <?= !empty($appointment['symptoms_summary']) ? e($appointment['symptoms_summary']) : 'Routine follow-up / general evaluation.' ?>
                </p>
            </div>
        </div>

        <!-- Right: Doctor Clinical Notepad & Medical History -->
        <div class="col-lg-5">
            <!-- Live Clinical Notes & Diagnosis Input -->
            <div class="glass-card p-4 mb-3 border border-teal-subtle">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-body"><i class="bi bi-journal-check me-2 text-teal"></i> Clinical Observations</h5>
                    <span class="badge bg-teal-subtle text-teal fs-xs" id="autoSaveIndicator">Auto-saving</span>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="clinicalDiagnosis">Clinical Diagnosis *</label>
                    <input type="text" class="form-control" id="clinicalDiagnosis" value="<?= e($consultation['diagnosis'] ?? '') ?>" placeholder="e.g. Acute Viral Bronchitis / Stage 1 Hypertension">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="clinicalNotes">Doctor Consultation Notes (Live Synced)</label>
                    <textarea class="form-control" id="clinicalNotes" rows="5" placeholder="Document physical examination findings, vitals (BP, SpO2, Heart Rate), assessment, and provisional plan..."><?= e($consultation['doctor_notes'] ?? '') ?></textarea>
                </div>

                <button type="button" class="btn btn-teal text-white w-100 rounded-pill py-2 fw-bold shadow-sm" onclick="concludeConsultation()">
                    <i class="bi bi-prescription2 me-1"></i> Conclude & Write Digital Rx
                </button>
            </div>

            <!-- Patient Previous History Accordion -->
            <div class="glass-card p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i> Patient Medical Vault History</h6>

                <!-- Past Prescriptions -->
                <div class="mb-3">
                    <span class="fs-xs text-secondary fw-bold text-uppercase d-block mb-2">Previous Prescriptions:</span>
                    <?php if (empty($patientHistoryRx)): ?>
                        <small class="text-secondary">No previous prescriptions recorded.</small>
                    <?php else: ?>
                        <div class="space-y-1">
                            <?php foreach ($patientHistoryRx as $prx): ?>
                                <div class="p-2 rounded-2 bg-body-tertiary border mb-1 fs-xs">
                                    <div class="d-flex justify-content-between">
                                        <strong class="text-body"><?= e($prx['diagnosis']) ?></strong>
                                        <span class="text-secondary"><?= date('M d, Y', strtotime($prx['created_at'])) ?></span>
                                    </div>
                                    <small class="text-teal">Dr. <?= e($prx['doctor_name']) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Uploaded Lab Reports / Scans -->
                <div>
                    <span class="fs-xs text-secondary fw-bold text-uppercase d-block mb-2">Uploaded Lab Reports & Scans:</span>
                    <?php if (empty($patientRecords)): ?>
                        <small class="text-secondary">No uploaded external records.</small>
                    <?php else: ?>
                        <div class="space-y-1">
                            <?php foreach ($patientRecords as $prec): ?>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded-2 bg-body-tertiary border mb-1 fs-xs">
                                    <span class="text-truncate" style="max-width: 200px;">
                                        <i class="bi bi-file-earmark-medical text-teal me-1"></i> <?= e($prec['title']) ?>
                                    </span>
                                    <a href="<?= base_url($prec['file_path']) ?>" target="_blank" class="btn btn-xs btn-outline-teal rounded-pill py-0 px-2">
                                        View
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const appointmentId = <?= $appointmentId ?>;
let docStream = null;
let isDocMicMuted = false;
let isDocCamOff = false;

async function initDoctorCamera() {
    try {
        docStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        document.getElementById('doctorLocalVideo').srcObject = docStream;
        document.getElementById('doctorVideoPlaceholder').classList.add('d-none');
    } catch (err) {
        console.warn('[Doctor Camera]', err.message);
        document.getElementById('doctorVideoPlaceholder').innerHTML = '<span class="text-center text-secondary fs-xs"><i class="bi bi-camera-video-off fs-4 d-block mb-1"></i>Standby</span>';
    }
}

document.getElementById('docMicBtn').addEventListener('click', () => {
    isDocMicMuted = !isDocMicMuted;
    if (docStream) docStream.getAudioTracks().forEach(t => t.enabled = !isDocMicMuted);
    const icon = document.getElementById('docMicIcon');
    const btn = document.getElementById('docMicBtn');
    if (isDocMicMuted) {
        btn.classList.add('active-off');
        icon.className = 'bi bi-mic-mute-fill text-white';
    } else {
        btn.classList.remove('active-off');
        icon.className = 'bi bi-mic-fill text-dark';
    }
});

document.getElementById('docCamBtn').addEventListener('click', () => {
    isDocCamOff = !isDocCamOff;
    if (docStream) docStream.getVideoTracks().forEach(t => t.enabled = !isDocCamOff);
    const icon = document.getElementById('docCamIcon');
    const btn = document.getElementById('docCamBtn');
    if (isDocCamOff) {
        btn.classList.add('active-off');
        icon.className = 'bi bi-camera-video-off-fill text-white';
    } else {
        btn.classList.remove('active-off');
        icon.className = 'bi bi-camera-video-fill text-dark';
    }
});

// Auto-save clinical notes every 15 seconds
async function saveClinicalNotes(silent = true) {
    const diag = document.getElementById('clinicalDiagnosis').value.trim();
    const notes = document.getElementById('clinicalNotes').value.trim();
    const indicator = document.getElementById('autoSaveIndicator');

    indicator.textContent = 'Saving...';

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

    if (res.success) {
        indicator.textContent = 'Saved ' + new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        if (!silent) CarePulse.showToast('Clinical observations saved.', 'success');
    }
}

let autoSaveTimer = setInterval(() => saveClinicalNotes(true), 15000);

async function concludeConsultation() {
    clearInterval(autoSaveTimer);
    const diag = document.getElementById('clinicalDiagnosis').value.trim();
    const notes = document.getElementById('clinicalNotes').value.trim();

    if (!diag) {
        CarePulse.showToast('Please enter a clinical diagnosis before concluding.', 'warning');
        document.getElementById('clinicalDiagnosis').focus();
        return;
    }

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
        CarePulse.showToast('Consultation ended. Redirecting to digital prescription writer...', 'success');
        setTimeout(() => {
            window.location.href = res.redirect;
        }, 800);
    } else {
        CarePulse.showToast(res.error || 'Failed to end consultation.', 'danger');
    }
}

initDoctorCamera();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
