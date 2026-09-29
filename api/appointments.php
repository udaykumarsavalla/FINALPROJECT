<?php
/**
 * CarePulse AI - Appointments API Endpoint
 * Handles appointment scheduling, double-booking prevention, and queue generation.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/queue_service.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? $_POST['action'] ?? 'create';

// Require authenticated patient for booking operations
Auth::requireAuth();

if ($action === 'create') {
    Security::requireCsrf();

    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true) ?: $_POST;

    $patientId = Auth::currentUserId();
    $doctorId = (int)($data['doctor_id'] ?? 0);
    $departmentId = (int)($data['department_id'] ?? 0);
    $appointmentDate = trim($data['appointment_date'] ?? '');
    $appointmentTime = trim($data['appointment_time'] ?? '');
    $appointmentType = trim($data['appointment_type'] ?? 'online_consultation');
    $symptomsSummary = trim($data['symptoms_summary'] ?? '');

    if ($doctorId <= 0 || empty($appointmentDate) || empty($appointmentTime)) {
        json_response(['success' => false, 'error' => 'Please provide complete appointment details.'], 400);
    }

    if (!in_array($appointmentType, ['hospital_visit', 'online_consultation'])) {
        $appointmentType = 'online_consultation';
    }

    // Verify Doctor exists and fetch department if not provided
    $doctor = Database::fetchOne("
        SELECT d.*, u.name as doctor_name, dep.id as dept_id, dep.name as dept_name
        FROM doctor_profiles d
        JOIN users u ON d.user_id = u.id
        JOIN departments dep ON d.department_id = dep.id
        WHERE d.id = ?
    ", [$doctorId]);

    if (!$doctor) {
        json_response(['success' => false, 'error' => 'Selected doctor profile was not found.'], 404);
    }

    if ($departmentId <= 0) {
        $departmentId = (int)$doctor['dept_id'];
    }

    // Prevent double booking using ACID transactional locking (SELECT ... FOR UPDATE)
    $pdo = Database::getInstance()->getConnection();
    $pdo->beginTransaction();

    try {
        $stmtLock = $pdo->prepare("
            SELECT id FROM appointments 
            WHERE doctor_id = ? 
              AND appointment_date = ? 
              AND appointment_time = ? 
              AND status NOT IN ('cancelled')
            FOR UPDATE
        ");
        $stmtLock->execute([$doctorId, $appointmentDate, $appointmentTime]);
        $existing = $stmtLock->fetch();

        if ($existing) {
            $pdo->rollBack();
            json_response([
                'success' => false,
                'error' => 'This time slot was just booked by another patient. Please select another slot.'
            ], 409);
        }

        // Generate Unique Appointment Number: CP-YYYY-RAND
        $aptNumber = 'CP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        
        // Generate Queue Number for the doctor on that date
        $queueNumber = QueueService::getNextQueueNumber($doctorId, $appointmentDate);

        // Generate WebRTC meeting room ID
        $meetingRoomId = 'carepulse-room-' . strtolower(bin2hex(random_bytes(6)));

        // Calculate initial estimated wait time
        $avgTime = (int)$doctor['avg_consult_time_mins'] ?: 15;
        $initialWaitMins = max(0, ($queueNumber - 1) * $avgTime);

        // Insert with status 'pending_payment'
        $stmtInsert = $pdo->prepare("
            INSERT INTO appointments (
                appointment_number, patient_id, doctor_id, department_id,
                appointment_date, appointment_time, appointment_type,
                queue_number, status, symptoms_summary, meeting_room_id, estimated_wait_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment', ?, ?, ?)
        ");
        $stmtInsert->execute([
            $aptNumber, $patientId, $doctorId, $departmentId,
            $appointmentDate, $appointmentTime, $appointmentType,
            $queueNumber, $symptomsSummary, $meetingRoomId, $initialWaitMins
        ]);
        $appointmentId = (int)$pdo->lastInsertId();

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(['success' => false, 'error' => 'Booking failed: ' . $e->getMessage()], 500);
    }

    json_response([
        'success' => true,
        'message' => 'Appointment slot reserved. Complete payment to confirm booking.',
        'appointment_id' => $appointmentId,
        'appointment_number' => $aptNumber,
        'consultation_fee' => (float)$doctor['consultation_fee'],
        'queue_number' => $queueNumber,
        'estimated_wait_time' => $initialWaitMins,
        'checkout_url' => base_url("payments/checkout.php?appointment_id={$appointmentId}")
    ]);
}

if ($action === 'cancel') {
    Security::requireCsrf();
    $aptId = (int)($_POST['appointment_id'] ?? 0);
    $patientId = Auth::currentUserId();

    $apt = Database::fetchOne("SELECT * FROM appointments WHERE id = ? AND patient_id = ?", [$aptId, $patientId]);
    if (!$apt) {
        json_response(['success' => false, 'error' => 'Appointment not found.'], 404);
    }

    if ($apt['status'] === 'completed') {
        json_response(['success' => false, 'error' => 'Completed appointments cannot be cancelled.'], 400);
    }

    Database::execute("UPDATE appointments SET status = 'cancelled' WHERE id = ?", [$aptId]);
    json_response(['success' => true, 'message' => 'Appointment cancelled successfully.']);
}

json_response(['success' => false, 'error' => 'Invalid action.'], 400);
