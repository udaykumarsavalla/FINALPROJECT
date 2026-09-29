<?php
/**
 * CarePulse AI - Doctor Digital Prescription Creator
 * Interactive medicine schedule builder with automated medication reminder generator.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['doctor']);

$pageTitle = "Issue Digital Prescription";
$activeMenu = "doc_rx";
$userId = Auth::currentUserId();

$docProfile = Database::fetchOne("SELECT id, specialization, qualification FROM doctor_profiles WHERE user_id = ?", [$userId]);
$doctorId = (int)$docProfile['id'];

$appointmentId = (int)($_GET['appointment_id'] ?? 0);

// If no appointment passed, find most recent completed/in_consultation appointment
if ($appointmentId <= 0) {
    $latestApt = Database::fetchOne("
        SELECT id FROM appointments 
        WHERE doctor_id = ? 
        ORDER BY id DESC LIMIT 1
    ", [$doctorId]);
    if ($latestApt) {
        $appointmentId = (int)$latestApt['id'];
    }
}

$appointment = Database::fetchOne("
    SELECT a.*, u.name as patient_name, u.email as patient_email, u.phone as patient_phone,
           u.gender as patient_gender, u.dob as patient_dob, u.blood_group as patient_blood_group,
           dep.name as department_name, c.diagnosis as consult_diag, c.doctor_notes
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    JOIN departments dep ON a.department_id = dep.id
    LEFT JOIN consultations c ON a.id = c.appointment_id
    WHERE a.id = ? AND a.doctor_id = ?
", [$appointmentId, $doctorId]);

if (!$appointment) {
    die("Appointment record not found or access denied.");
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                        <i class="bi bi-prescription2 me-1"></i> Digital Prescription Generator
                    </span>
                    <h2 class="display-6 fw-bold mb-0">Issue Prescription</h2>
                    <p class="text-secondary small mb-0">Patient: <strong><?= e($appointment['patient_name']) ?></strong> &bull; Token #<?= $appointment['queue_number'] ?> &bull; Appt: <?= e($appointment['appointment_number']) ?></p>
                </div>
                <a href="<?= base_url('doctor/appointments.php') ?>" class="btn btn-outline-secondary rounded-pill btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Roster
                </a>
            </div>

            <!-- Main Form Card -->
            <div class="glass-card p-4 p-md-5 shadow-lg border-0">
                <form id="rxForm">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" id="rxAppointmentId" value="<?= $appointmentId ?>">

                    <!-- Patient Summary Strip -->
                    <div class="row g-3 p-3 rounded-3 bg-body-tertiary border mb-4">
                        <div class="col-sm-6 col-md-3">
                            <span class="text-secondary fs-xs d-block">Patient Name</span>
                            <strong class="text-body"><?= e($appointment['patient_name']) ?></strong>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-secondary fs-xs d-block">Gender / Blood Group</span>
                            <strong class="text-body"><?= ucfirst($appointment['patient_gender']) ?> &bull; <?= e($appointment['patient_blood_group'] ?? 'O+') ?></strong>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-secondary fs-xs d-block">Department</span>
                            <strong class="text-teal"><?= e($appointment['department_name']) ?></strong>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-secondary fs-xs d-block">Consultation Date</span>
                            <strong class="text-body"><?= date('M d, Y', strtotime($appointment['appointment_date'])) ?></strong>
                        </div>
                    </div>

                    <!-- Clinical Diagnosis -->
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-teal" for="rxDiagnosis">Clinical Diagnosis & Findings *</label>
                        <input type="text" class="form-control form-control-lg fs-6 p-3" id="rxDiagnosis" value="<?= e($appointment['consult_diag'] ?? '') ?>" placeholder="e.g. Acute Pharyngitis with Mild Dehydration / Hypertension Stage 1" required>
                    </div>

                    <!-- Prescribed Medicines Dynamic Table -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-semibold text-teal mb-0">
                                <span class="fs-5 me-1">&#8478;</span> Prescribed Medications & Dosages *
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-teal rounded-pill" onclick="addMedicineRow()">
                                <i class="bi bi-plus-circle me-1"></i> Add Another Medicine
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle" id="medicinesTable">
                                <thead class="table-light text-secondary fs-xs text-uppercase">
                                    <tr>
                                        <th style="width: 32%;">Medicine Name & Strength</th>
                                        <th style="width: 16%;">Dosage</th>
                                        <th style="width: 18%;" class="text-center">Schedule (M - A - N)</th>
                                        <th style="width: 14%;">Duration</th>
                                        <th style="width: 16%;">Instructions</th>
                                        <th style="width: 4%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="medicinesBody">
                                    <!-- Initial Row -->
                                    <tr class="medicine-row">
                                        <td>
                                            <input type="text" class="form-control form-control-sm med-name" placeholder="e.g. Amoxicillin 500mg" value="Amoxicillin 500mg" required>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm med-dosage" placeholder="1 tablet" value="1 tablet" required>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-2">
                                                <div class="form-check form-check-inline m-0">
                                                    <input class="form-check-input med-morn" type="checkbox" title="Morning" checked>
                                                    <label class="form-check-label fs-xs">M</label>
                                                </div>
                                                <div class="form-check form-check-inline m-0">
                                                    <input class="form-check-input med-aft" type="checkbox" title="Afternoon">
                                                    <label class="form-check-label fs-xs">A</label>
                                                </div>
                                                <div class="form-check form-check-inline m-0">
                                                    <input class="form-check-input med-night" type="checkbox" title="Night" checked>
                                                    <label class="form-check-label fs-xs">N</label>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm med-duration" placeholder="7 days" value="7 days">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm med-instructions" placeholder="After food" value="Take after food with water">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(this)" title="Remove">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Clinical Advice & Follow-Up Date -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-teal" for="rxAdvice">Clinical Advice, Diet, & Lifestyle Instructions</label>
                            <textarea class="form-control" id="rxAdvice" rows="3" placeholder="Maintain hydration (> 2.5L water/day), avoid oily/spicy foods, report if fever exceeds 101F...">Adequate rest for 3 days. Complete antibiotic course. Increase oral fluids. Report if chest pain or shortness of breath worsens.</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-teal" for="rxFollowUp">Recommended Follow-Up Date</label>
                            <input type="date" class="form-control" id="rxFollowUp" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" min="<?= date('Y-m-d') ?>">
                            <small class="text-secondary fs-xs mt-1 d-block">Scheduled review consultation date</small>
                        </div>
                    </div>

                    <!-- Automated Medication Reminder Opt-In (Feature 8 Integration) -->
                    <div class="p-3 rounded-3 bg-teal-subtle text-teal border border-teal-subtle mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="autoRemindersCheck" checked>
                            <label class="form-check-label fw-bold text-teal" for="autoRemindersCheck">
                                <i class="bi bi-bell-fill me-1"></i> Automatically schedule multi-channel Medication Reminders (WhatsApp, SMS, Email) for this patient
                            </label>
                            <small class="text-secondary d-block mt-1">CarePulse AI Adherence engine will send automated alerts to the patient's phone and email on the morning/afternoon/night schedule.</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" id="saveRxBtn" class="btn btn-teal text-white px-5 py-3 fw-bold rounded-pill shadow">
                            <i class="bi bi-check2-circle me-1"></i> Issue Digital Prescription & Complete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function addMedicineRow() {
    const tbody = document.getElementById('medicinesBody');
    const tr = document.createElement('tr');
    tr.className = 'medicine-row';
    tr.innerHTML = `
        <td><input type="text" class="form-control form-control-sm med-name" placeholder="e.g. Paracetamol 650mg" required></td>
        <td><input type="text" class="form-control form-control-sm med-dosage" placeholder="1 tablet" value="1 tablet" required></td>
        <td class="text-center">
            <div class="d-inline-flex gap-2">
                <div class="form-check form-check-inline m-0"><input class="form-check-input med-morn" type="checkbox" checked><label class="form-check-label fs-xs">M</label></div>
                <div class="form-check form-check-inline m-0"><input class="form-check-input med-aft" type="checkbox"><label class="form-check-label fs-xs">A</label></div>
                <div class="form-check form-check-inline m-0"><input class="form-check-input med-night" type="checkbox" checked><label class="form-check-label fs-xs">N</label></div>
            </div>
        </td>
        <td><input type="text" class="form-control form-control-sm med-duration" placeholder="5 days" value="5 days"></td>
        <td><input type="text" class="form-control form-control-sm med-instructions" placeholder="Take after food" value="Take after food"></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removeRow(btn) {
    const rows = document.querySelectorAll('.medicine-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
    } else {
        CarePulse.showToast('Prescription must contain at least one medication.', 'warning');
    }
}

document.getElementById('rxForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveRxBtn');
    const diagnosis = document.getElementById('rxDiagnosis').value.trim();
    if (!diagnosis) {
        CarePulse.showToast('Please enter a diagnosis.', 'warning');
        return;
    }

    const medicines = [];
    document.querySelectorAll('.medicine-row').forEach(row => {
        const name = row.querySelector('.med-name').value.trim();
        if (name) {
            medicines.push({
                medicine_name: name,
                dosage: row.querySelector('.med-dosage').value.trim(),
                morning: row.querySelector('.med-morn').checked ? 1 : 0,
                afternoon: row.querySelector('.med-aft').checked ? 1 : 0,
                night: row.querySelector('.med-night').checked ? 1 : 0,
                duration: row.querySelector('.med-duration').value.trim(),
                instructions: row.querySelector('.med-instructions').value.trim()
            });
        }
    });

    if (medicines.length === 0) {
        CarePulse.showToast('Please add at least one medication.', 'warning');
        return;
    }

    CarePulse.setButtonLoading(btn, true);

    const payload = {
        action: 'create',
        appointment_id: parseInt(document.getElementById('rxAppointmentId').value),
        diagnosis: diagnosis,
        advice: document.getElementById('rxAdvice').value.trim(),
        follow_up_date: document.getElementById('rxFollowUp').value,
        medicines: medicines,
        create_reminders: document.getElementById('autoRemindersCheck').checked ? 1 : 0,
        csrf_token: window.APP_CONFIG.csrfToken
    };

    const res = await CarePulse.api('api/prescriptions.php', {
        method: 'POST',
        body: payload
    });

    CarePulse.setButtonLoading(btn, false);

    if (res.success) {
        CarePulse.showToast('Digital prescription issued successfully!', 'success');
        setTimeout(() => {
            window.location.href = res.view_url;
        }, 1000);
    } else {
        CarePulse.showToast(res.error || 'Failed to issue prescription.', 'danger');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
