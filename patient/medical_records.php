<?php
/**
 * CarePulse AI - Secure Medical Record Vault
 * Supports PDF/JPG/PNG MIME-validated uploads, cryptographic hashing, and categorical previews.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();

$pageTitle = "Medical Records Vault";
$activeMenu = "vault";
$patientId = Auth::hasRole('patient') ? Auth::currentUserId() : (int)($_GET['patient_id'] ?? 0);

$records = Database::fetchAll("
    SELECT r.*, u.name as uploader_name
    FROM medical_records r
    JOIN users u ON r.uploaded_by = u.id
    WHERE r.patient_id = ?
    ORDER BY r.created_at DESC
", [$patientId]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                <i class="bi bi-shield-lock-fill me-1"></i> End-to-End Encrypted Storage
            </span>
            <h2 class="display-6 fw-bold mb-1">Health Records Vault</h2>
            <p class="text-secondary small mb-0">Securely store and share lab investigations, radiology scans, and clinical discharge summaries.</p>
        </div>
        <button type="button" class="btn btn-teal text-white rounded-pill px-4 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-cloud-arrow-up me-1"></i> Upload Document
        </button>
    </div>

    <!-- Category Filter Bar -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <button class="btn btn-sm btn-teal text-white rounded-pill filter-btn active" data-type="all">All Documents (<?= count($records) ?>)</button>
        <button class="btn btn-sm btn-outline-secondary rounded-pill filter-btn" data-type="lab_report">Lab Reports</button>
        <button class="btn btn-sm btn-outline-secondary rounded-pill filter-btn" data-type="scan">Imaging / Scans</button>
        <button class="btn btn-sm btn-outline-secondary rounded-pill filter-btn" data-type="discharge_summary">Discharge Summaries</button>
        <button class="btn btn-sm btn-outline-secondary rounded-pill filter-btn" data-type="prescription">External Prescriptions</button>
    </div>

    <!-- Records Grid -->
    <div class="row g-4" id="recordsGrid">
        <?php if (empty($records)): ?>
            <div class="col-12 text-center py-5 text-secondary">
                <i class="bi bi-folder-x fs-1 d-block mb-3"></i>
                <h5>Vault is Currently Empty</h5>
                <p class="small">Upload your blood tests, MRI scans, or past prescriptions for doctor review.</p>
                <button type="button" class="btn btn-outline-teal rounded-pill btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    Upload Your First Document
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($records as $rec): ?>
                <div class="col-md-6 col-lg-4 record-item" data-type="<?= e($rec['record_type']) ?>">
                    <div class="glass-card glass-card-hover p-4 h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon-box bg-teal-subtle text-teal rounded-3 p-3">
                                <?php if (str_contains($rec['mime_type'], 'pdf')): ?>
                                    <i class="bi bi-file-earmark-pdf fs-4 text-danger"></i>
                                <?php else: ?>
                                    <i class="bi bi-file-earmark-image fs-4 text-primary"></i>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-body-secondary text-secondary text-capitalize fs-xs">
                                <?= str_replace('_', ' ', $rec['record_type']) ?>
                            </span>
                        </div>

                        <h6 class="fw-bold mb-1 text-body text-truncate"><?= e($rec['title']) ?></h6>
                        <small class="text-secondary mb-2 text-truncate"><?= e($rec['original_name']) ?></small>
                        
                        <?php if (!empty($rec['notes'])): ?>
                            <p class="text-secondary fs-xs bg-body-tertiary p-2 rounded-2 mb-3 text-truncate">
                                <?= e($rec['notes']) ?>
                            </p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top text-secondary fs-xs">
                            <span><?= number_format($rec['file_size'] / 1024, 1) ?> KB</span>
                            <span><?= date('M d, Y', strtotime($rec['created_at'])) ?></span>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <a href="<?= base_url($rec['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-teal rounded-pill flex-grow-1">
                                <i class="bi bi-eye me-1"></i> View
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="deleteRecord(<?= $rec['id'] ?>)" title="Delete Record">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg glass-card">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="uploadModalLabel"><i class="bi bi-cloud-arrow-up text-teal me-2"></i> Upload to Health Vault</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="recordUploadForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="docTitle">Document Title *</label>
                        <input type="text" class="form-control" id="docTitle" name="title" placeholder="e.g. Fasting Blood Sugar Report" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="docType">Record Category *</label>
                        <select class="form-select" id="docType" name="record_type" required>
                            <option value="lab_report">Lab Report / Blood Test</option>
                            <option value="scan">Radiology / MRI / CT / X-Ray</option>
                            <option value="discharge_summary">Hospital Discharge Summary</option>
                            <option value="prescription">Past Doctor Prescription</option>
                            <option value="other">Other Health Record</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="docFile">Choose File (PDF, JPG, PNG) *</label>
                        <input type="file" class="form-control" id="docFile" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        <div class="form-text fs-xs">Strict MIME verification enforced. Max size: 15 MB.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="docNotes">Doctor Notes or Description</label>
                        <textarea class="form-control" id="docNotes" name="notes" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="uploadSubmitBtn" class="btn btn-teal text-white rounded-pill btn-sm px-4 fw-bold">
                        <i class="bi bi-lock-fill me-1"></i> Encrypt & Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Category filtering
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => {
            b.classList.remove('btn-teal', 'text-white', 'active');
            b.classList.add('btn-outline-secondary');
        });
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-teal', 'text-white', 'active');

        const type = btn.dataset.type;
        document.querySelectorAll('.record-item').forEach(item => {
            if (type === 'all' || item.dataset.type === type) {
                item.classList.remove('d-none');
            } else {
                item.classList.add('d-none');
            }
        });
    });
});

// Upload Form Submission
document.getElementById('recordUploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('uploadSubmitBtn');
    CarePulse.setButtonLoading(btn, true);

    const formData = new FormData(this);
    formData.append('action', 'upload');

    const res = await CarePulse.api('api/medical_records.php', {
        method: 'POST',
        body: formData
    });

    CarePulse.setButtonLoading(btn, false);

    if (res.success) {
        CarePulse.showToast(res.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        CarePulse.showToast(res.error || 'Upload failed.', 'danger');
    }
});

async function deleteRecord(id) {
    if (!confirm('Are you sure you want to remove this record from your vault?')) return;

    const res = await CarePulse.api('api/medical_records.php', {
        method: 'POST',
        body: {
            action: 'delete',
            record_id: id,
            csrf_token: window.APP_CONFIG.csrfToken
        }
    });

    if (res.success) {
        CarePulse.showToast(res.message, 'info');
        setTimeout(() => location.reload(), 800);
    } else {
        CarePulse.showToast(res.error, 'danger');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
