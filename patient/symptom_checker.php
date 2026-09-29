<?php
/**
 * CarePulse AI - Intelligent Symptom Checker & Department Classifier
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "AI Symptom Checker & Clinical Triage";
$activeMenu = "symptom_checker";

$prefilledSymptom = trim($_GET['symptoms'] ?? '');

// Fetch past symptom assessment history for current user
$pastHistory = [];
if (Auth::isLoggedIn() && Auth::hasRole('patient')) {
    $patientId = Auth::currentUserId();
    $pastHistory = Database::fetchAll("
        SELECT h.*, dep.name as department_name, dep.icon as department_icon
        FROM symptom_history h
        JOIN departments dep ON h.predicted_department_id = dep.id
        WHERE h.patient_id = ?
        ORDER BY h.created_at DESC LIMIT 6
    ", [$patientId]);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header Banner -->
            <div class="text-center mb-5">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-teal-subtle text-teal fw-bold fs-xs mb-2">
                    <i class="bi bi-robot"></i> Scikit-Learn Machine Learning Classifier v1.9
                </div>
                <h1 class="display-6 fw-bold mb-2">AI Clinical Symptom Checker</h1>
                <p class="text-secondary max-w-700 mx-auto">
                    Describe how you are feeling in natural language. Our artificial intelligence analyzes symptom patterns, predicts the appropriate medical specialty, and matches you with available hospital doctors.
                </p>
            </div>

            <!-- Symptom Input Card -->
            <div class="glass-card p-4 p-md-5 mb-5 shadow-lg border-0">
                <form id="symptomForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-teal d-flex justify-content-between align-items-center" for="symptomInput">
                            <span><i class="bi bi-chat-heart me-1"></i> Describe Your Symptoms</span>
                            <span class="text-muted fw-normal fs-xs">Be as specific as possible (location, duration, sensation)</span>
                        </label>
                        <textarea class="form-control p-3 fs-5" id="symptomInput" rows="3" placeholder="e.g. Experiencing sharp chest tightness when walking fast, rapid palpitations, and mild dizziness for the past 2 days..." required><?= e($prefilledSymptom) ?></textarea>
                    </div>

                    <!-- Quick Sample Tags -->
                    <div class="mb-4">
                        <span class="text-secondary fs-xs fw-semibold me-2">Click to Try Example:</span>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fs-xs sample-btn" data-text="crushing chest pain radiating to left shoulder, breathlessness, and cold sweat">
                                <i class="bi bi-heart-pulse text-danger"></i> Cardiac Pain
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fs-xs sample-btn" data-text="severe throbbing migraine on one side, extreme sensitivity to light, and visual aura">
                                <i class="bi bi-activity text-primary"></i> Migraine Aura
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fs-xs sample-btn" data-text="swollen painful knee joint with clicking sound and stiffness after twisting knee in football">
                                <i class="bi bi-bandaid text-warning"></i> Knee Injury
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fs-xs sample-btn" data-text="red raised itchy skin welts and hives breaking out all over arms after food allergy">
                                <i class="bi bi-stars text-info"></i> Skin Hives
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fs-xs sample-btn" data-text="toddler has high fever 102F, persistent crying, pulling ears, and refusal to drink milk">
                                <i class="bi bi-emoji-smile text-success"></i> Pediatric Fever
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-secondary">
                            <i class="bi bi-shield-check text-success"></i> All queries are encrypted and processed privately.
                        </small>
                        <button type="submit" id="analyzeBtn" class="btn btn-teal text-white px-5 py-3 fw-bold rounded-pill shadow">
                            <i class="bi bi-cpu-fill me-2"></i> Run AI Clinical Analysis
                        </button>
                    </div>
                </form>
            </div>

            <!-- AI Assessment Results Section (Initially Hidden) -->
            <div id="resultsSection" class="d-none mb-5 fade-in">
                <div class="glass-card p-4 p-md-5 border border-teal shadow-lg mb-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 border-bottom pb-4 mb-4">
                        <div>
                            <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-2">
                                <i class="bi bi-check-circle-fill me-1"></i> AI Classification Result
                            </span>
                            <h3 class="fw-bold mb-1" id="resDepartmentName">Department Name</h3>
                            <p class="text-secondary small mb-0" id="resDepartmentDesc">Department Description</p>
                        </div>
                        <div class="text-center text-md-end">
                            <div class="d-inline-flex flex-column align-items-center p-3 rounded-4 bg-body-tertiary border">
                                <span class="fs-xs text-secondary text-uppercase fw-bold">Prediction Confidence</span>
                                <span class="display-6 fw-extrabold text-teal" id="resConfidence">--%</span>
                                <span class="badge bg-success-subtle text-success fs-xs mt-1">High Accuracy Match</span>
                            </div>
                        </div>
                    </div>

                    <!-- Top Structured Clinical Output Banner -->
                    <div class="row g-3 p-3 rounded-3 bg-body-tertiary border mb-4">
                        <div class="col-sm-6 col-md-3 border-end-md">
                            <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-1">Department:</span>
                            <h5 class="fw-bold text-teal mb-0" id="summaryDeptName">Cardiology</h5>
                        </div>
                        <div class="col-sm-6 col-md-3 border-end-md">
                            <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-1">Confidence:</span>
                            <h5 class="fw-bold text-success mb-0" id="summaryConfidence">94%</h5>
                        </div>
                        <div class="col-sm-6 col-md-3 border-end-md">
                            <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-1">Urgency Level:</span>
                            <span class="badge bg-warning-subtle text-warning fw-bold fs-6 py-1 px-3" id="summaryUrgency">High</span>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-1">Recommended Doctor:</span>
                            <div class="fw-bold text-body small" id="summaryDoctorName">Dr. Sharma</div>
                            <div class="text-teal fs-xs mt-1" id="summaryDoctorSlots">Slots: 10:30 AM, 11:15 AM</div>
                        </div>
                    </div>

                    <!-- Triage Clinical Guidance Alert -->
                    <div class="alert alert-info border-info-subtle d-flex align-items-start gap-3 p-3 rounded-3 mb-4" id="resTriageBox">
                        <i class="bi bi-info-circle-fill fs-4 text-info flex-shrink-0"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Clinical Triage Note</h6>
                            <p class="small mb-0" id="resTriageText">Triage advice message.</p>
                        </div>
                    </div>

                    <!-- Department Distribution Chart -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase fs-xs mb-3">Model Probability Breakdown Across Specialties:</h6>
                        <div id="topPredictionsContainer" class="space-y-2">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>

                    <!-- Recommended Available Doctors -->
                    <div class="pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Recommended Specialists in this Department</h5>
                            <span class="badge bg-body-secondary text-secondary">Ready for Booking</span>
                        </div>
                        <div class="row g-3" id="recommendedDoctorsList">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Past Symptom History Section (if logged in) -->
            <?php if (!empty($pastHistory)): ?>
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-teal"></i> Your Past Symptom Checks</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small">
                                    <th>Date</th>
                                    <th>Symptoms Input</th>
                                    <th>Predicted Department</th>
                                    <th>Confidence</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pastHistory as $hist): ?>
                                    <tr>
                                        <td class="text-secondary small"><?= date('M d, Y', strtotime($hist['created_at'])) ?></td>
                                        <td class="text-truncate small" style="max-width: 250px;"><?= e($hist['symptoms_input']) ?></td>
                                        <td>
                                            <span class="badge bg-teal-subtle text-teal">
                                                <i class="bi <?= e($hist['department_icon']) ?> me-1"></i> <?= e($hist['department_name']) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold"><?= number_format($hist['confidence_score'], 1) ?>%</td>
                                        <td class="text-end">
                                            <a href="<?= base_url('patient/book_appointment.php?dept=' . $hist['predicted_department_id']) ?>" class="btn btn-sm btn-outline-teal rounded-pill">
                                                Consult Doctor
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Sample chips click handlers
document.querySelectorAll('.sample-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('symptomInput').value = btn.dataset.text;
        document.getElementById('symptomForm').dispatchEvent(new Event('submit'));
    });
});

// Auto-trigger if query string contains symptom
if (document.getElementById('symptomInput').value.trim().length > 3) {
    window.addEventListener('DOMContentLoaded', () => {
        document.getElementById('symptomForm').dispatchEvent(new Event('submit'));
    });
}

document.getElementById('symptomForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('analyzeBtn');
    const symptoms = document.getElementById('symptomInput').value.trim();
    if (!symptoms) return;

    CarePulse.setButtonLoading(btn, true);

    const res = await CarePulse.api('api/symptom_checker.php', {
        method: 'POST',
        body: { symptoms: symptoms }
    });

    CarePulse.setButtonLoading(btn, false);

    if (res.success) {
        renderResults(res);
    } else {
        CarePulse.showToast(res.error || 'Failed to analyze symptoms.', 'danger');
    }
});

function renderResults(data) {
    const resultsSection = document.getElementById('resultsSection');
    resultsSection.classList.remove('d-none');

    document.getElementById('resDepartmentName').textContent = data.department.name;
    document.getElementById('resDepartmentDesc').textContent = data.department.description || '';
    document.getElementById('resConfidence').textContent = `${data.confidence}%`;
    document.getElementById('resTriageText').textContent = data.triage_advice;

    // Structured Summary
    document.getElementById('summaryDeptName').textContent = data.department.name;
    document.getElementById('summaryConfidence').textContent = `${data.confidence}%`;
    
    const urgEl = document.getElementById('summaryUrgency');
    urgEl.textContent = data.urgency_level || 'Moderate';
    urgEl.className = 'badge fw-bold fs-6 py-1 px-3 ' + (
        data.urgency_level === 'Critical Emergency' ? 'bg-danger text-white' :
        (data.urgency_level === 'High Urgency' ? 'bg-warning text-dark' : 'bg-info-subtle text-info')
    );

    if (data.recommended_doctor) {
        document.getElementById('summaryDoctorName').textContent = data.recommended_doctor.name;
        document.getElementById('summaryDoctorSlots').textContent = 'Slots: ' + (data.recommended_doctor.available_slots || []).slice(0, 2).join(', ');
    } else {
        document.getElementById('summaryDoctorName').textContent = 'Specialist on duty';
        document.getElementById('summaryDoctorSlots').textContent = 'Slots on demand';
    }

    // Render probability distribution bars
    const predContainer = document.getElementById('topPredictionsContainer');
    predContainer.innerHTML = '';
    (data.top_predictions || []).forEach(p => {
        const row = document.createElement('div');
        row.className = 'mb-2';
        row.innerHTML = `
            <div class="d-flex justify-content-between small mb-1">
                <span class="fw-semibold text-body">${p.department}</span>
                <span class="text-teal fw-bold">${p.confidence}%</span>
            </div>
            <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-teal" style="width: ${Math.max(5, p.confidence)}%"></div>
            </div>
        `;
        predContainer.appendChild(row);
    });

    // Render Doctors
    const docContainer = document.getElementById('recommendedDoctorsList');
    docContainer.innerHTML = '';

    if (!data.doctors || data.doctors.length === 0) {
        docContainer.innerHTML = `<div class="col-12 text-center text-muted py-3">No active doctors registered in this department currently.</div>`;
    } else {
        data.doctors.forEach(doc => {
            const slotsText = (doc.available_slots && doc.available_slots.length > 0) ? doc.available_slots.slice(0, 3).join(', ') : 'Next Slot Available';
            const col = document.createElement('div');
            col.className = 'col-md-6';
            col.innerHTML = `
                <div class="p-3 rounded-3 bg-body-tertiary border h-100 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="user-avatar-badge" style="width: 44px; height: 44px; font-size: 1.1rem;">
                            ${doc.name.charAt(4) || 'D'}
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-body">${doc.name}</h6>
                            <small class="text-secondary">${doc.specialization} &bull; ${doc.experience_years} yrs exp</small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center my-2 text-secondary fs-xs">
                        <span><i class="bi bi-star-fill text-warning"></i> ${doc.rating} / 5.0</span>
                        <span>Fee: <strong class="text-teal">₹${doc.consultation_fee}</strong></span>
                        <span>${doc.is_available_online ? '<i class="bi bi-camera-video text-success"></i> Video Available' : '<i class="bi bi-hospital"></i> In-Hospital'}</span>
                    </div>
                    <div class="p-2 rounded-2 bg-body border mb-3 fs-xs">
                        <span class="text-secondary d-block fw-semibold">Available Slots:</span>
                        <span class="text-teal fw-bold">${slotsText}</span>
                    </div>
                    <a href="${window.APP_CONFIG.baseUrl}/patient/book_appointment.php?doctor_id=${doc.id}&dept=${data.department.id}" class="btn btn-teal text-white btn-sm rounded-pill mt-auto fw-semibold">
                        <i class="bi bi-calendar-plus me-1"></i> Book Consultation
                    </a>
                </div>
            `;
            docContainer.appendChild(col);
        });
    }

    // Scroll smoothly to result
    resultsSection.scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
