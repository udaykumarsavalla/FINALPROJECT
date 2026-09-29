<?php
/**
 * CarePulse AI - Medication Reminder & Multi-Channel Notification Hub
 * Schedules SMS, WhatsApp, and Email dosage reminders with Morning/Afternoon/Night timeline.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();

$pageTitle = "Medication Reminders";
$activeMenu = "reminders";
$patientId = Auth::hasRole('patient') ? Auth::currentUserId() : (int)($_GET['patient_id'] ?? 0);

$reminders = Database::fetchAll("
    SELECT * FROM medication_reminders 
    WHERE patient_id = ? 
    ORDER BY status ASC, id DESC
", [$patientId]);

$logs = Database::fetchAll("
    SELECT l.*, r.medicine_name
    FROM medication_logs l
    JOIN medication_reminders r ON l.reminder_id = r.id
    WHERE l.patient_id = ?
    ORDER BY l.sent_time DESC LIMIT 10
", [$patientId]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                <i class="bi bi-bell-fill me-1"></i> Multi-Channel Adherence Engine
            </span>
            <h2 class="display-6 fw-bold mb-1">Medication Reminders</h2>
            <p class="text-secondary small mb-0">Never miss a dose. Automated dispatch across WhatsApp, SMS, and Email according to your daily routine.</p>
        </div>
        <button type="button" class="btn btn-teal text-white rounded-pill px-4 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#newReminderModal">
            <i class="bi bi-plus-circle me-1"></i> Add Medication Schedule
        </button>
    </div>

    <div class="row g-4">
        <!-- Active Schedules -->
        <div class="col-lg-8">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-calendar3-range me-2 text-teal"></i> Active Prescribed Schedules</h5>

                <?php if (empty($reminders)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-capsule fs-1 d-block mb-3"></i>
                        <h5>No Active Medication Schedules</h5>
                        <p class="small">Add your medicines or receive them automatically when your doctor issues a digital prescription.</p>
                        <button type="button" class="btn btn-outline-teal rounded-pill btn-sm" data-bs-toggle="modal" data-bs-target="#newReminderModal">
                            Create First Schedule
                        </button>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($reminders as $rem): ?>
                            <div class="p-3 rounded-3 bg-body-tertiary border mb-3">
                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-2 mb-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <h5 class="fw-bold mb-0 text-body"><?= e($rem['medicine_name']) ?></h5>
                                            <span class="badge bg-teal-subtle text-teal"><?= e($rem['dosage']) ?></span>
                                            <span class="badge bg-<?= $rem['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $rem['status'] === 'active' ? 'success' : 'secondary' ?> fs-xs text-uppercase"><?= e($rem['status']) ?></span>
                                        </div>
                                        <p class="text-secondary small mb-0 mt-1"><?= e($rem['instructions']) ?></p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-teal rounded-pill" onclick="triggerAlert(<?= $rem['id'] ?>)" title="Test Immediate Alert">
                                            <i class="bi bi-send me-1"></i> Send Test Alert
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="toggleStatus(<?= $rem['id'] ?>, '<?= $rem['status'] === 'active' ? 'paused' : 'active' ?>')">
                                            <?= $rem['status'] === 'active' ? '<i class="bi bi-pause"></i> Pause' : '<i class="bi bi-play"></i> Resume' ?>
                                        </button>
                                    </div>
                                </div>

                                <!-- Schedule Timeline Strip -->
                                <div class="row g-2 p-2 rounded-2 bg-body border my-2 text-center fs-xs">
                                    <div class="col-4 border-end">
                                        <span class="text-secondary d-block"><i class="bi bi-sun text-warning me-1"></i> Morning</span>
                                        <strong class="<?= $rem['schedule_morning'] ? 'text-teal' : 'text-muted text-decoration-line-through' ?>">
                                            <?= $rem['schedule_morning'] ? date('h:i A', strtotime($rem['time_morning'])) : 'None' ?>
                                        </strong>
                                    </div>
                                    <div class="col-4 border-end">
                                        <span class="text-secondary d-block"><i class="bi bi-brightness-high text-warning me-1"></i> Afternoon</span>
                                        <strong class="<?= $rem['schedule_afternoon'] ? 'text-teal' : 'text-muted text-decoration-line-through' ?>">
                                            <?= $rem['schedule_afternoon'] ? date('h:i A', strtotime($rem['time_afternoon'])) : 'None' ?>
                                        </strong>
                                    </div>
                                    <div class="col-4">
                                        <span class="text-secondary d-block"><i class="bi bi-moon-stars text-primary me-1"></i> Night</span>
                                        <strong class="<?= $rem['schedule_night'] ? 'text-teal' : 'text-muted text-decoration-line-through' ?>">
                                            <?= $rem['schedule_night'] ? date('h:i A', strtotime($rem['time_night'])) : 'None' ?>
                                        </strong>
                                    </div>
                                </div>

                                <!-- Delivery Channels -->
                                <div class="d-flex justify-content-between align-items-center text-secondary fs-xs mt-2">
                                    <div class="d-flex gap-3">
                                        <span class="<?= $rem['channel_whatsapp'] ? 'text-success fw-bold' : 'text-muted' ?>">
                                            <i class="bi bi-whatsapp"></i> WhatsApp <?= $rem['channel_whatsapp'] ? 'ON' : 'OFF' ?>
                                        </span>
                                        <span class="<?= $rem['channel_sms'] ? 'text-primary fw-bold' : 'text-muted' ?>">
                                            <i class="bi bi-chat-dots-fill"></i> SMS <?= $rem['channel_sms'] ? 'ON' : 'OFF' ?>
                                        </span>
                                        <span class="<?= $rem['channel_email'] ? 'text-teal fw-bold' : 'text-muted' ?>">
                                            <i class="bi bi-envelope-fill"></i> Email <?= $rem['channel_email'] ? 'ON' : 'OFF' ?>
                                        </span>
                                    </div>
                                    <span>Until <?= date('M d, Y', strtotime($rem['end_date'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Delivery Logs Telemetry -->
        <div class="col-lg-4">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-activity me-2 text-primary"></i> Alert Dispatch Log</h5>

                <?php if (empty($logs)): ?>
                    <p class="text-secondary small mb-0">No dispatch history recorded yet. Click "Send Test Alert" to test notification triggers.</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($logs as $l): ?>
                            <div class="p-2 rounded-2 bg-body-tertiary border mb-2 fs-xs">
                                <div class="d-flex justify-content-between text-secondary">
                                    <span>
                                        <?php if ($l['channel'] === 'whatsapp'): ?>
                                            <i class="bi bi-whatsapp text-success me-1"></i> WhatsApp
                                        <?php elseif ($l['channel'] === 'sms'): ?>
                                            <i class="bi bi-chat-dots text-primary me-1"></i> SMS
                                        <?php else: ?>
                                            <i class="bi bi-envelope text-teal me-1"></i> Email
                                        <?php endif; ?>
                                    </span>
                                    <span><?= date('h:i A, M d', strtotime($l['sent_time'])) ?></span>
                                </div>
                                <div class="fw-bold text-body mt-1"><?= e($l['medicine_name']) ?></div>
                                <div class="text-muted text-truncate"><?= e($l['message_text']) ?></div>
                                <span class="badge bg-success-subtle text-success fs-xs mt-1">Delivered</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Medication Reminder Modal -->
<div class="modal fade" id="newReminderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg glass-card">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="bi bi-alarm-fill text-teal me-2"></i> Schedule Medication Reminder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newReminderForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="medName">Medicine Name & Strength *</label>
                        <input type="text" class="form-control" id="medName" placeholder="e.g. Metformin 500mg" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="medDosage">Dosage</label>
                            <input type="text" class="form-control" id="medDosage" value="1 Tablet">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="medDuration">Duration (Days)</label>
                            <input type="number" class="form-control" id="medDuration" value="30" min="1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="medInstructions">Instructions</label>
                        <input type="text" class="form-control" id="medInstructions" value="Take after food with a glass of water">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold d-block">Daily Dosage Schedule *</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="schedMorn" checked>
                            <label class="form-check-label small" for="schedMorn">Morning (08:00 AM)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="schedAft">
                            <label class="form-check-label small" for="schedAft">Afternoon (01:00 PM)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="schedNight" checked>
                            <label class="form-check-label small" for="schedNight">Night (08:30 PM)</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold d-block">Notification Delivery Channels *</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="chanWa" checked>
                            <label class="form-check-label small text-success fw-bold" for="chanWa"><i class="bi bi-whatsapp"></i> WhatsApp</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="chanSms" checked>
                            <label class="form-check-label small text-primary fw-bold" for="chanSms"><i class="bi bi-chat-dots"></i> SMS</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="chanEmail" checked>
                            <label class="form-check-label small text-teal fw-bold" for="chanEmail"><i class="bi bi-envelope"></i> Email</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="saveReminderBtn" class="btn btn-teal text-white rounded-pill btn-sm px-4 fw-bold">
                        Activate Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('newReminderForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveReminderBtn');
    CarePulse.setButtonLoading(btn, true);

    const payload = {
        action: 'create',
        medicine_name: document.getElementById('medName').value.trim(),
        dosage: document.getElementById('medDosage').value.trim(),
        instructions: document.getElementById('medInstructions').value.trim(),
        schedule_morning: document.getElementById('schedMorn').checked ? 1 : 0,
        schedule_afternoon: document.getElementById('schedAft').checked ? 1 : 0,
        schedule_night: document.getElementById('schedNight').checked ? 1 : 0,
        channel_whatsapp: document.getElementById('chanWa').checked ? 1 : 0,
        channel_sms: document.getElementById('chanSms').checked ? 1 : 0,
        channel_email: document.getElementById('chanEmail').checked ? 1 : 0,
        csrf_token: window.APP_CONFIG.csrfToken
    };

    const res = await CarePulse.api('api/reminders.php', {
        method: 'POST',
        body: payload
    });

    CarePulse.setButtonLoading(btn, false);

    if (res.success) {
        CarePulse.showToast(res.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        CarePulse.showToast(res.error || 'Failed to schedule reminder.', 'danger');
    }
});

async function triggerAlert(id) {
    CarePulse.showToast('Dispatching immediate multi-channel reminder...', 'info');
    const res = await CarePulse.api('api/reminders.php', {
        method: 'POST',
        body: {
            action: 'trigger_alert',
            reminder_id: id,
            csrf_token: window.APP_CONFIG.csrfToken
        }
    });

    if (res.success) {
        CarePulse.showToast('Reminder alerts dispatched successfully via WhatsApp, SMS, & Email!', 'success');
        setTimeout(() => location.reload(), 1200);
    } else {
        CarePulse.showToast(res.error || 'Alert trigger failed.', 'danger');
    }
}

async function toggleStatus(id, newStatus) {
    const res = await CarePulse.api('api/reminders.php', {
        method: 'POST',
        body: {
            action: 'toggle_status',
            reminder_id: id,
            status: newStatus,
            csrf_token: window.APP_CONFIG.csrfToken
        }
    });

    if (res.success) {
        CarePulse.showToast(res.message, 'info');
        setTimeout(() => location.reload(), 800);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
