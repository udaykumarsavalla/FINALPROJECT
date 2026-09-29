<?php
/**
 * CarePulse AI - Medication Reminders API
 * Schedules dosage alerts and dispatches multi-channel alerts (SMS, WhatsApp, Email).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email_service.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireAuth();

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?: $_POST;

$action = $data['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'create') {
    Security::requireCsrf();
    $patientId = Auth::hasRole('patient') ? Auth::currentUserId() : (int)($data['patient_id'] ?? 0);

    $medicineName = trim($data['medicine_name'] ?? '');
    $dosage = trim($data['dosage'] ?? '1 tablet');
    $instructions = trim($data['instructions'] ?? 'Take after food');
    $morn = !empty($data['schedule_morning']) ? 1 : 0;
    $aft = !empty($data['schedule_afternoon']) ? 1 : 0;
    $night = !empty($data['schedule_night']) ? 1 : 0;
    $timeMorn = $data['time_morning'] ?? '08:00:00';
    $timeAft = $data['time_afternoon'] ?? '13:00:00';
    $timeNight = $data['time_night'] ?? '20:30:00';
    $sms = !empty($data['channel_sms']) ? 1 : 0;
    $wa = !empty($data['channel_whatsapp']) ? 1 : 0;
    $email = !empty($data['channel_email']) ? 1 : 0;
    $startDate = $data['start_date'] ?? date('Y-m-d');
    $endDate = $data['end_date'] ?? date('Y-m-d', strtotime('+30 days'));

    if (empty($medicineName)) {
        json_response(['success' => false, 'error' => 'Medicine name is required.'], 400);
    }

    $reminderId = Database::insert("
        INSERT INTO medication_reminders (
            patient_id, medicine_name, dosage, instructions,
            schedule_morning, schedule_afternoon, schedule_night,
            time_morning, time_afternoon, time_night,
            channel_sms, channel_whatsapp, channel_email,
            start_date, end_date, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ", [
        $patientId, $medicineName, $dosage, $instructions,
        $morn, $aft, $night,
        $timeMorn, $timeAft, $timeNight,
        $sms, $wa, $email,
        $startDate, $endDate
    ]);

    json_response([
        'success' => true,
        'message' => 'Medication reminder scheduled.',
        'reminder_id' => $reminderId
    ]);
}

if ($action === 'trigger_alert') {
    // Manually trigger immediate reminder delivery for demonstration/testing
    Security::requireCsrf();
    $reminderId = (int)($data['reminder_id'] ?? 0);

    if ($reminderId <= 0) {
        json_response(['success' => false, 'error' => 'Valid reminder ID required.'], 400);
    }

    $res = NotificationService::dispatchMedicationReminder($reminderId);
    json_response($res);
}

if ($action === 'toggle_status') {
    Security::requireCsrf();
    $reminderId = (int)($data['reminder_id'] ?? 0);
    $status = in_array($data['status'] ?? '', ['active', 'paused', 'completed']) ? $data['status'] : 'active';

    Database::execute("UPDATE medication_reminders SET status = ? WHERE id = ?", [$status, $reminderId]);
    json_response(['success' => true, 'message' => "Reminder status updated to {$status}."]);
}

// Default: List patient reminders & logs
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

json_response([
    'success' => true,
    'reminders' => $reminders,
    'logs' => $logs
]);
