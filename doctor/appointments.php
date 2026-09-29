<?php
/**
 * CarePulse AI - Doctor Appointments & Queue Roster Management
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['doctor']);

$pageTitle = "Doctor OPD Appointments";
$activeMenu = "doc_appointments";
$userId = Auth::currentUserId();

$docProfile = Database::fetchOne("SELECT id, department_id, specialization FROM doctor_profiles WHERE user_id = ?", [$userId]);
$doctorId = (int)$docProfile['id'];

$filterDate = trim($_GET['date'] ?? date('Y-m-d'));

$appointments = Database::fetchAll("
    SELECT a.*, u.name as patient_name, u.email as patient_email, u.phone as patient_phone,
           u.gender as patient_gender, u.blood_group as patient_blood_group,
           p.receipt_number, p.payment_status
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    LEFT JOIN payments p ON a.id = p.appointment_id
    WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status NOT IN ('cancelled')
    ORDER BY a.queue_number ASC
", [$doctorId, $filterDate]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header with Date Switcher -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                <i class="bi bi-calendar3 me-1"></i> Patient Queue Roster
            </span>
            <h2 class="display-6 fw-bold mb-0">Scheduled Consultations</h2>
            <p class="text-secondary small mb-0">Manage daily tokens, trigger patient calls, and issue digital prescriptions.</p>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="form-label small fw-semibold text-secondary mb-0 text-nowrap" for="filterDateInput">Roster Date:</label>
            <input type="date" id="filterDateInput" name="date" class="form-control form-control-sm" value="<?= e($filterDate) ?>" onchange="this.form.submit()">
            <a href="<?= base_url('doctor/appointments.php?date=' . date('Y-m-d')) ?>" class="btn btn-sm btn-outline-teal rounded-pill text-nowrap">Today</a>
        </form>
    </div>

    <!-- Appointments Table -->
    <div class="glass-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Roster for <?= date('l, F d, Y', strtotime($filterDate)) ?> (<?= count($appointments) ?> Patients)</h5>
            <span class="badge bg-body-secondary text-secondary">Token Ordered</span>
        </div>

        <?php if (empty($appointments)): ?>
            <div class="text-center py-5 text-secondary">
                <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                <h5>No Patient Appointments Found for this Date</h5>
                <p class="small">Patients booking consultations will appear here automatically.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-secondary small">
                            <th>Token</th>
                            <th>Patient Information</th>
                            <th>Slot Time</th>
                            <th>Mode</th>
                            <th>Symptoms</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $apt): ?>
                            <tr class="<?= $apt['status'] === 'in_consultation' ? 'table-primary' : '' ?>">
                                <td>
                                    <span class="badge bg-teal-subtle text-teal fw-bold fs-6">#<?= $apt['queue_number'] ?></span>
                                </td>
                                <td>
                                    <strong class="text-body d-block"><?= e($apt['patient_name']) ?></strong>
                                    <small class="text-secondary"><?= ucfirst($apt['patient_gender']) ?> &bull; <?= e($apt['patient_blood_group'] ?? 'O+') ?> &bull; <?= e($apt['patient_phone']) ?></small>
                                </td>
                                <td>
                                    <strong><?= date('h:i A', strtotime($apt['appointment_time'])) ?></strong>
                                </td>
                                <td>
                                    <?php if ($apt['appointment_type'] === 'online_consultation'): ?>
                                        <span class="badge bg-primary-subtle text-primary"><i class="bi bi-camera-video me-1"></i> Video</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-hospital me-1"></i> In-Chamber</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-truncate small" style="max-width: 180px;">
                                    <?= e($apt['symptoms_summary'] ?: 'General checkup') ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= e($apt['status']) ?>"><?= e(str_replace('_', ' ', $apt['status'])) ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if ($apt['status'] === 'confirmed'): ?>
                                        <a href="<?= base_url('doctor/consultation.php?appointment_id=' . $apt['id']) ?>" class="btn btn-sm btn-teal text-white rounded-pill px-3 shadow-sm">
                                            <i class="bi bi-telephone-inbound me-1"></i> Call Patient
                                        </a>
                                    <?php elseif ($apt['status'] === 'in_consultation'): ?>
                                        <a href="<?= base_url('doctor/consultation.php?appointment_id=' . $apt['id']) ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                                            <i class="bi bi-play-circle me-1"></i> Resume Call
                                        </a>
                                    <?php elseif ($apt['status'] === 'completed'): ?>
                                        <a href="<?= base_url('doctor/create_prescription.php?appointment_id=' . $apt['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                            Prescription
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
