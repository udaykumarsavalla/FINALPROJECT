<?php
/**
 * CarePulse AI - Digital Prescription API
 * Handles digital Rx generation, automated medication reminders setup, and retrieval.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'create') {
    Security::requireCsrf();
    Auth::requireAuth(['doctor']);

    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true) ?: $_POST;

    $appointmentId = (int)($data['appointment_id'] ?? 0);
    $diagnosis = trim($data['diagnosis'] ?? '');
    $advice = trim($data['advice'] ?? '');
    $followUpDate = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;
    $medicines = $data['medicines'] ?? [];
    $createReminders = !empty($data['create_reminders']);

    if ($appointmentId <= 0 || empty($diagnosis) || empty($medicines)) {
        json_response(['success' => false, 'error' => 'Diagnosis and at least one prescribed medicine are required.'], 400);
    }

    $apt = Database::fetchOne("SELECT * FROM appointments WHERE id = ?", [$appointmentId]);
    if (!$apt) {
        json_response(['success' => false, 'error' => 'Appointment not found.'], 404);
    }

    $consultation = Database::fetchOne("SELECT id FROM consultations WHERE appointment_id = ?", [$appointmentId]);
    $consultationId = $consultation['id'] ?? null;

    $rxNumber = 'RX-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

    try {
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        $prescriptionId = Database::insert("
            INSERT INTO prescriptions (prescription_number, consultation_id, appointment_id, doctor_id, patient_id, diagnosis, medicines, advice, follow_up_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $rxNumber, $consultationId, $appointmentId, $apt['doctor_id'], $apt['patient_id'],
            $diagnosis, json_encode($medicines), $advice, $followUpDate
        ]);

        // Mark appointment completed
        Database::execute("UPDATE appointments SET status = 'completed' WHERE id = ?", [$appointmentId]);

        // Auto-create Medication Reminders if checked
        if ($createReminders && is_array($medicines)) {
            $startDate = date('Y-m-d');
            foreach ($medicines as $med) {
                $medName = trim($med['medicine_name'] ?? '');
                $dosage = trim($med['dosage'] ?? '1 tablet');
                $morn = !empty($med['morning']) ? 1 : 0;
                $aft = !empty($med['afternoon']) ? 1 : 0;
                $night = !empty($med['night']) ? 1 : 0;
                $instr = trim($med['instructions'] ?? 'Take after food with water');
                $durationDays = (int)preg_replace('/[^0-9]/', '', $med['duration'] ?? '7') ?: 7;
                $endDate = date('Y-m-d', strtotime("+$durationDays days"));

                if (!empty($medName)) {
                    Database::insert("
                        INSERT INTO medication_reminders (
                            patient_id, prescription_id, medicine_name, dosage, instructions,
                            schedule_morning, schedule_afternoon, schedule_night,
                            start_date, end_date, channel_sms, channel_whatsapp, channel_email, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, 'active')
                    ", [
                        $apt['patient_id'], $prescriptionId, $medName, $dosage, $instr,
                        $morn, $aft, $night, $startDate, $endDate
                    ]);
                }
            }
        }

        $pdo->commit();

        json_response([
            'success' => true,
            'message' => 'Digital prescription issued successfully!',
            'prescription_id' => $prescriptionId,
            'prescription_number' => $rxNumber,
            'view_url' => base_url("patient/prescriptions.php?rx_id={$prescriptionId}")
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'get') {
    $rxId = (int)($_GET['id'] ?? 0);
    $rx = Database::fetchOne("
        SELECT p.*, 
               u_doc.name as doctor_name, u_doc.phone as doctor_phone, u_doc.email as doctor_email,
               doc.qualification, doc.specialization, doc.room_number,
               dep.name as department_name,
               u_pat.name as patient_name, u_pat.email as patient_email, u_pat.phone as patient_phone,
               u_pat.gender as patient_gender, u_pat.dob as patient_dob, u_pat.blood_group as patient_blood_group,
               a.appointment_number, a.appointment_date, a.appointment_type
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN doctor_profiles doc ON p.doctor_id = doc.id
        JOIN users u_doc ON doc.user_id = u_doc.id
        JOIN departments dep ON doc.department_id = dep.id
        JOIN users u_pat ON p.patient_id = u_pat.id
        WHERE p.id = ?
    ", [$rxId]);

    if (!$rx) {
        json_response(['success' => false, 'error' => 'Prescription not found.'], 404);
    }

    $rx['medicines'] = json_decode($rx['medicines'], true) ?: [];

    json_response(['success' => true, 'prescription' => $rx]);
}

// List user prescriptions
$patientId = Auth::hasRole('patient') ? Auth::currentUserId() : (int)($_GET['patient_id'] ?? 0);
$list = Database::fetchAll("
    SELECT p.*, u.name as doctor_name, dep.name as department_name, a.appointment_date
    FROM prescriptions p
    JOIN appointments a ON p.appointment_id = a.id
    JOIN doctor_profiles d ON p.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON d.department_id = dep.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
", [$patientId]);

json_response(['success' => true, 'prescriptions' => $list]);
