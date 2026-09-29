<?php
/**
 * CarePulse AI - Medical Record Vault API
 * Handles secure file uploads (PDF/JPG/PNG MIME check, random hashes), listing, and deletions.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'upload') {
    Security::requireCsrf();

    if (!isset($_FILES['file'])) {
        json_response(['success' => false, 'error' => 'No file received for upload.'], 400);
    }

    $title = trim($_POST['title'] ?? 'Medical Document');
    $recordType = trim($_POST['record_type'] ?? 'lab_report');
    $notes = trim($_POST['notes'] ?? '');
    $patientId = Auth::hasRole('patient') ? Auth::currentUserId() : (int)($_POST['patient_id'] ?? 0);
    $uploadedBy = Auth::currentUserId();

    if ($patientId <= 0) {
        json_response(['success' => false, 'error' => 'Valid patient target is required.'], 400);
    }

    // Secure MIME & Random Hashing via Security helper
    $uploadRes = Security::handleFileUpload($_FILES['file'], 'medical_records', 15728640); // 15MB limit
    if (!$uploadRes['success']) {
        json_response($uploadRes, 400);
    }

    $recordId = Database::insert("
        INSERT INTO medical_records (patient_id, title, record_type, file_name, original_name, file_path, mime_type, file_size, uploaded_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $patientId, $title, $recordType,
        $uploadRes['file_name'], $uploadRes['original_name'], $uploadRes['file_path'],
        $uploadRes['mime_type'], $uploadRes['file_size'], $uploadedBy, $notes
    ]);

    json_response([
        'success' => true,
        'message' => 'Document securely encrypted and uploaded to vault.',
        'record_id' => $recordId,
        'file_name' => $uploadRes['original_name'],
        'file_url' => base_url($uploadRes['file_path'])
    ]);
}

if ($action === 'delete') {
    Security::requireCsrf();
    $recordId = (int)($_POST['record_id'] ?? 0);
    $userId = Auth::currentUserId();

    $record = Database::fetchOne("SELECT * FROM medical_records WHERE id = ?", [$recordId]);
    if (!$record) {
        json_response(['success' => false, 'error' => 'Record not found.'], 404);
    }

    // Only owner patient or admin can delete
    if ($record['patient_id'] !== $userId && !Auth::hasRole('admin')) {
        json_response(['success' => false, 'error' => 'Permission denied.'], 403);
    }

    // Remove file on disk if exists
    $filePath = BASE_DIR . '/' . $record['file_path'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    Database::execute("DELETE FROM medical_records WHERE id = ?", [$recordId]);

    json_response(['success' => true, 'message' => 'Record deleted from vault.']);
}

// Default: List Records
$patientId = Auth::hasRole('patient') ? Auth::currentUserId() : (int)($_GET['patient_id'] ?? 0);
$type = trim($_GET['type'] ?? '');

$sql = "
    SELECT r.*, u.name as uploader_name
    FROM medical_records r
    JOIN users u ON r.uploaded_by = u.id
    WHERE r.patient_id = ?
";
$params = [$patientId];

if (!empty($type)) {
    $sql .= " AND r.record_type = ?";
    $params[] = $type;
}

$sql .= " ORDER BY r.created_at DESC";

$records = Database::fetchAll($sql, $params);

json_response([
    'success' => true,
    'count' => count($records),
    'records' => $records
]);
