<?php
/**
 * CarePulse AI - Smart Appointment Booking Wizard
 * Live AJAX doctor search, real-time slot selection, double-booking prevention.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['patient']);

$pageTitle = "Smart Appointment Booking";
$activeMenu = "book";

$selectedDoctorId = (int)($_GET['doctor_id'] ?? 0);
$selectedDeptId = (int)($_GET['dept'] ?? 0);

$departments = Database::fetchAll("SELECT * FROM departments ORDER BY name ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header -->
            <div class="mb-4">
                <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                    <i class="bi bi-calendar2-check-fill me-1"></i> Telemedicine & OPD Booking
                </span>
                <h2 class="display-6 fw-bold mb-1">Schedule Consultation</h2>
                <p class="text-secondary small">Filter doctors live, choose real-time time slots, and reserve your queue token.</p>
            </div>

            <div class="row g-4">
                <!-- Left: Filters & Doctor Selection -->
                <div class="col-lg-7">
                    <div class="glass-card p-4 mb-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-search me-2 text-teal"></i> Select Doctor & Department</h5>
                        
                        <!-- Search & Filters -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-7">
                                <div class="input-group">
                                    <span class="input-group-text bg-body"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" id="docSearchInput" class="form-control" placeholder="Search doctor name or specialty...">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <select id="deptFilter" class="form-select">
                                    <option value="0">All Departments</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= $selectedDeptId === (int)$d['id'] ? 'selected' : '' ?>>
                                            <?= e($d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Live Doctor Cards Container -->
                        <div id="doctorsListContainer" class="space-y-3" style="max-height: 480px; overflow-y: auto;">
                            <div class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm text-teal" role="status"></div>
                                <span class="ms-2">Loading available hospital specialists...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Booking Form & Real-Time Slot Picker -->
                <div class="col-lg-5">
                    <div class="glass-card p-4 sticky-top" style="top: 90px;">
                        <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-teal"></i> Booking Details</h5>

                        <form id="appointmentBookingForm">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" id="selectedDoctorId" name="doctor_id" value="<?= $selectedDoctorId ?>">

                            <!-- Selected Doctor Summary Card -->
                            <div id="selectedDoctorSummary" class="p-3 rounded-3 bg-body-tertiary border mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-info-circle text-teal fs-4"></i>
                                    <div>
                                        <span class="fw-bold d-block text-body" id="selectedDocNameText">No Doctor Selected</span>
                                        <small class="text-secondary" id="selectedDocSpecialtyText">Please select a specialist from the list</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Consultation Mode -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Consultation Mode *</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="appointment_type" id="typeOnline" value="online_consultation" checked>
                                        <label class="btn btn-outline-primary w-100 py-2 rounded-3 text-start d-flex align-items-center gap-2" for="typeOnline">
                                            <i class="bi bi-camera-video-fill text-primary"></i>
                                            <div>
                                                <div class="fw-bold fs-xs">Online Video</div>
                                                <div class="text-muted fs-xs">Browser Call</div>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="appointment_type" id="typeHospital" value="hospital_visit">
                                        <label class="btn btn-outline-secondary w-100 py-2 rounded-3 text-start d-flex align-items-center gap-2" for="typeHospital">
                                            <i class="bi bi-hospital text-teal"></i>
                                            <div>
                                                <div class="fw-bold fs-xs">Hospital Visit</div>
                                                <div class="text-muted fs-xs">OPD Chamber</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Date Picker -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold" for="appointmentDate">Appointment Date *</label>
                                <input type="date" id="appointmentDate" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <!-- Real-time Slot Picker -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold d-flex justify-content-between align-items-center">
                                    <span>Available Time Slots *</span>
                                    <span id="slotStatusBadge" class="badge bg-secondary-subtle text-secondary fs-xs">Select Doctor & Date</span>
                                </label>
                                
                                <div id="slotsGrid" class="d-flex flex-wrap gap-2 p-2 bg-body-tertiary rounded-3 border" style="max-height: 160px; overflow-y: auto;">
                                    <small class="text-muted p-2">Choose a doctor and date to load slots.</small>
                                </div>
                                <input type="hidden" id="selectedTime" name="appointment_time" required>
                            </div>

                            <!-- Symptoms Note -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold" for="bookingSymptoms">Symptoms / Reason for Visit</label>
                                <textarea id="bookingSymptoms" class="form-control" rows="2" placeholder="Briefly state reason for visit..."></textarea>
                            </div>

                            <!-- Consultation Fee Breakdown -->
                            <div class="p-3 rounded-3 bg-teal-subtle text-teal border border-teal-subtle mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold small">Consultation Fee:</span>
                                    <span class="fs-5 fw-extrabold" id="consultationFeeText">₹0</span>
                                </div>
                                <small class="text-secondary d-block fs-xs mt-1">Confirmed after fast UPI, Card, or Net Banking checkout.</small>
                            </div>

                            <button type="submit" id="bookBtn" class="btn btn-teal text-white w-100 py-3 fw-bold rounded-pill shadow-sm" disabled>
                                <i class="bi bi-credit-card-2-front me-1"></i> Proceed to Payment & Queue
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedDoctor = null;
const preselectedDocId = <?= $selectedDoctorId ?>;

// Search debounce
let searchTimer = null;
document.getElementById('docSearchInput').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchDoctors, 300);
});

document.getElementById('deptFilter').addEventListener('change', fetchDoctors);
document.getElementById('appointmentDate').addEventListener('change', loadSlots);

async function fetchDoctors() {
    const q = document.getElementById('docSearchInput').value.trim();
    const deptId = document.getElementById('deptFilter').value;
    const container = document.getElementById('doctorsListContainer');

    const res = await CarePulse.api(`api/doctors.php?q=${encodeURIComponent(q)}&department_id=${deptId}`);
    if (!res.success) return;

    container.innerHTML = '';
    if (res.doctors.length === 0) {
        container.innerHTML = `<div class="text-center py-4 text-muted">No doctors found matching criteria.</div>`;
        return;
    }

    res.doctors.forEach(doc => {
        const isSelected = selectedDoctor && selectedDoctor.id === doc.id;
        const card = document.createElement('div');
        card.className = `p-3 rounded-3 border mb-2 cursor-pointer transition-all doc-item-card ${isSelected ? 'border-teal bg-teal-subtle' : 'bg-body-tertiary'}`;
        card.style.cursor = 'pointer';
        card.innerHTML = `
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar-badge" style="width: 42px; height: 42px; font-size: 1rem;">
                        ${doc.doctor_name.charAt(4) || 'D'}
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-body">${doc.doctor_name}</h6>
                        <small class="text-teal fw-semibold">${doc.department_name}</small> &bull;
                        <small class="text-secondary">${doc.experience_years} yrs exp</small>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-body">₹${doc.consultation_fee}</div>
                    <small class="text-warning"><i class="bi bi-star-fill"></i> ${doc.rating}</small>
                </div>
            </div>
        `;

        card.addEventListener('click', () => selectDoctor(doc));
        container.appendChild(card);

        if (preselectedDocId === parseInt(doc.id) && !selectedDoctor) {
            selectDoctor(doc);
        }
    });
}

function selectDoctor(doc) {
    selectedDoctor = doc;
    document.getElementById('selectedDoctorId').value = doc.id;
    document.getElementById('selectedDocNameText').textContent = doc.doctor_name;
    document.getElementById('selectedDocSpecialtyText').textContent = `${doc.department_name} • Fee: ₹${doc.consultation_fee}`;
    document.getElementById('consultationFeeText').textContent = `₹${doc.consultation_fee}`;

    // Highlight card
    document.querySelectorAll('.doc-item-card').forEach(el => {
        el.classList.remove('border-teal', 'bg-teal-subtle');
        el.classList.add('bg-body-tertiary');
    });

    loadSlots();
}

async function loadSlots() {
    if (!selectedDoctor) return;
    const date = document.getElementById('appointmentDate').value;
    const badge = document.getElementById('slotStatusBadge');
    const grid = document.getElementById('slotsGrid');

    badge.className = 'badge bg-warning-subtle text-warning fs-xs';
    badge.textContent = 'Checking real-time availability...';
    grid.innerHTML = '<span class="spinner-border spinner-border-sm text-teal m-2"></span>';

    const res = await CarePulse.api(`api/doctors.php?action=slots&doctor_id=${selectedDoctor.id}&date=${date}`);
    if (!res.success) {
        grid.innerHTML = `<small class="text-danger p-2">${res.error || 'Failed to fetch slots'}</small>`;
        return;
    }

    grid.innerHTML = '';
    document.getElementById('selectedTime').value = '';
    document.getElementById('bookBtn').disabled = true;

    if (!res.slots || res.slots.length === 0) {
        badge.className = 'badge bg-danger-subtle text-danger fs-xs';
        badge.textContent = 'Doctor Not Practicing on this Day';
        grid.innerHTML = '<small class="text-muted p-2">Doctor does not have OPD timings on this date. Please pick another date.</small>';
        return;
    }

    badge.className = 'badge bg-success-subtle text-success fs-xs';
    badge.textContent = 'Slots Available';

    res.slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `btn btn-sm ${slot.available ? 'btn-outline-teal' : 'btn-outline-secondary opacity-50'} rounded-pill px-3 py-1`;
        btn.textContent = slot.time_12;
        btn.disabled = !slot.available;

        if (slot.available) {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#slotsGrid .btn').forEach(b => {
                    b.classList.remove('btn-teal', 'text-white');
                    b.classList.add('btn-outline-teal');
                });
                btn.classList.remove('btn-outline-teal');
                btn.classList.add('btn-teal', 'text-white');
                document.getElementById('selectedTime').value = slot.time_24;
                document.getElementById('bookBtn').disabled = false;
            });
        }
        grid.appendChild(btn);
    });
}

document.getElementById('appointmentBookingForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!selectedDoctor) {
        CarePulse.showToast('Please select a doctor.', 'warning');
        return;
    }

    const time = document.getElementById('selectedTime').value;
    if (!time) {
        CarePulse.showToast('Please select an available time slot.', 'warning');
        return;
    }

    const btn = document.getElementById('bookBtn');
    CarePulse.setButtonLoading(btn, true);

    const payload = {
        doctor_id: selectedDoctor.id,
        department_id: selectedDoctor.department_id,
        appointment_date: document.getElementById('appointmentDate').value,
        appointment_time: time,
        appointment_type: document.querySelector('input[name="appointment_type"]:checked').value,
        symptoms_summary: document.getElementById('bookingSymptoms').value.trim(),
        csrf_token: window.APP_CONFIG.csrfToken
    };

    const res = await CarePulse.api('api/appointments.php', {
        method: 'POST',
        body: payload
    });

    CarePulse.setButtonLoading(btn, false);

    if (res.success) {
        CarePulse.showToast('Slot reserved! Redirecting to secure checkout...', 'success');
        setTimeout(() => {
            window.location.href = res.checkout_url;
        }, 1000);
    } else {
        CarePulse.showToast(res.error || 'Failed to book slot.', 'danger');
        // Refresh slots in case of double-booking collision
        loadSlots();
    }
});

// Initial load
fetchDoctors();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
