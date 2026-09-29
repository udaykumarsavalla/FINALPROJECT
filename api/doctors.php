<?php
/**
 * CarePulse AI - Doctors Directory & Slot Availability API
 * Real-time AJAX search and real-time slot generation without page reload.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/queue_service.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? 'search';

if ($action === 'slots') {
    $doctorId = (int)($_GET['doctor_id'] ?? 0);
    $date = trim($_GET['date'] ?? date('Y-m-d'));

    if ($doctorId <= 0) {
        json_response(['success' => false, 'error' => 'Valid doctor ID is required.'], 400);
    }

    $slots = QueueService::getDoctorSlots($doctorId, $date);
    $doctor = Database::fetchOne("
        SELECT d.*, u.name, dep.name as department_name 
        FROM doctor_profiles d
        JOIN users u ON d.user_id = u.id
        JOIN departments dep ON d.department_id = dep.id
        WHERE d.id = ?
    ", [$doctorId]);

    json_response([
        'success' => true,
        'doctor' => $doctor,
        'date' => $date,
        'slots' => $slots
    ]);
}

// Default: Live Search
$query = trim($_GET['q'] ?? '');
$deptId = (int)($_GET['department_id'] ?? 0);
$type = trim($_GET['type'] ?? ''); // 'online' or 'all'

$sql = "
    SELECT d.id, d.specialization, d.experience_years, d.qualification, d.consultation_fee,
           d.room_number, d.avg_consult_time_mins, d.rating, d.is_available_online, d.bio,
           u.name as doctor_name, u.email, u.phone, u.avatar,
           dep.id as department_id, dep.name as department_name, dep.icon as department_icon
    FROM doctor_profiles d
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON d.department_id = dep.id
    WHERE u.status = 'active'
";
$params = [];

if (!empty($query)) {
    $sql .= " AND (u.name LIKE ? OR d.specialization LIKE ? OR dep.name LIKE ?)";
    $like = '%' . $query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($deptId > 0) {
    $sql .= " AND d.department_id = ?";
    $params[] = $deptId;
}

if ($type === 'online') {
    $sql .= " AND d.is_available_online = 1";
}

$sql .= " ORDER BY d.rating DESC, d.experience_years DESC";

$doctors = Database::fetchAll($sql, $params);

json_response([
    'success' => true,
    'count' => count($doctors),
    'doctors' => $doctors
]);
