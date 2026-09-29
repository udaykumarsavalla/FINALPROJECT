<?php
/**
 * CarePulse AI - Hospital Doctor Directory & Roster Management
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['admin']);

$pageTitle = "Specialist Staff Directory";
$activeMenu = "admin_doctors";

$doctors = Database::fetchAll("
    SELECT d.*, u.name as doctor_name, u.email as doctor_email, u.phone as doctor_phone, u.status as user_status,
           dep.name as department_name, dep.icon as department_icon
    FROM doctor_profiles d
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON d.department_id = dep.id
    ORDER BY dep.name ASC, d.rating DESC
");

$departments = Database::fetchAll("SELECT * FROM departments ORDER BY name ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                <i class="bi bi-people-fill me-1"></i> Medical Staff Governance
            </span>
            <h2 class="display-6 fw-bold mb-0">Hospital Specialists & Physicians</h2>
            <p class="text-secondary small mb-0">Department rosters, consultation tariffs, OPD chambers, and teleconsultation status.</p>
        </div>
    </div>

    <!-- Doctors Table Card -->
    <div class="glass-card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small">
                        <th>Doctor Information</th>
                        <th>Department</th>
                        <th>Experience & Rating</th>
                        <th>OPD Chamber</th>
                        <th>Consultation Fee</th>
                        <th>Teleconsultation</th>
                        <th class="text-end">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doc): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar-badge" style="width: 44px; height: 44px; font-size: 1.1rem;">
                                        <?= strtoupper(substr($doc['doctor_name'], 4, 1)) ?>
                                    </div>
                                    <div>
                                        <strong class="text-body d-block"><?= e($doc['doctor_name']) ?></strong>
                                        <small class="text-secondary"><?= e($doc['qualification']) ?> &bull; <?= e($doc['specialization']) ?></small>
                                        <div class="text-muted fs-xs"><?= e($doc['doctor_email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-teal-subtle text-teal">
                                    <i class="bi <?= e($doc['department_icon']) ?> me-1"></i> <?= e($doc['department_name']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?= $doc['experience_years'] ?> Years Experience</div>
                                <small class="text-warning"><i class="bi bi-star-fill"></i> <?= number_format($doc['rating'], 2) ?> / 5.0</small>
                            </td>
                            <td>
                                <span class="small text-secondary"><?= e($doc['room_number']) ?></span>
                            </td>
                            <td>
                                <strong class="text-body fs-6">₹<?= number_format($doc['consultation_fee'], 0) ?></strong>
                            </td>
                            <td>
                                <?php if ($doc['is_available_online']): ?>
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-camera-video me-1"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">In-Chamber Only</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-success-subtle text-success text-capitalize"><?= e($doc['user_status']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
