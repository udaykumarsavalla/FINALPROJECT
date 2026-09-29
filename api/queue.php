<?php
/**
 * CarePulse AI - Real-Time Queue & Dynamic Wait-Time Tracker API
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/queue_service.php';

header('Content-Type: application/json; charset=UTF-8');

$appointmentId = (int)($_GET['appointment_id'] ?? 0);
$doctorId = (int)($_GET['doctor_id'] ?? 0);
$date = trim($_GET['date'] ?? date('Y-m-d'));

if ($appointmentId > 0) {
    // Return specific appointment queue position and dynamic wait time
    $info = QueueService::calculateEstimatedWaitTime($appointmentId);
    
    // Fetch doctor info
    $apt = Database::fetchOne("
        SELECT a.appointment_date, a.appointment_time, a.appointment_type, a.meeting_room_id,
               u.name as doctor_name, dep.name as department_name, d.room_number
        FROM appointments a
        JOIN doctor_profiles d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        JOIN departments dep ON a.department_id = dep.id
        WHERE a.id = ?
    ", [$appointmentId]);

    json_response([
        'success' => true,
        'queue' => $info,
        'appointment' => $apt
    ]);
}

if ($doctorId > 0) {
    // Return entire doctor queue for today (for doctor workbench or OPD display screen)
    $appointments = Database::fetchAll("
        SELECT a.id, a.appointment_number, a.queue_number, a.appointment_time, a.appointment_type,
               a.status, a.symptoms_summary, a.meeting_room_id, a.estimated_wait_time,
               u.name as patient_name, u.phone as patient_phone, u.gender as patient_gender
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status NOT IN ('cancelled', 'pending_payment')
        ORDER BY a.queue_number ASC
    ", [$doctorId, $date]);

    // Current serving token
    $current = Database::fetchOne("
        SELECT queue_number, id, meeting_room_id 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status = 'in_consultation'
        ORDER BY queue_number ASC LIMIT 1
    ", [$doctorId, $date]);

    json_response([
        'success' => true,
        'doctor_id' => $doctorId,
        'date' => $date,
        'current_serving' => $current ? (int)$current['queue_number'] : null,
        'active_appointment_id' => $current ? (int)$current['id'] : null,
        'appointments' => $appointments
    ]);
}

json_response(['success' => false, 'error' => 'Provide either appointment_id or doctor_id.'], 400);
