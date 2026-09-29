<?php
/**
 * CarePulse AI - Consultation Session & Clinical Notes API
 * Handles live note saving, starting call, and ending consultation.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireAuth();

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?: $_POST;

$action = $data['action'] ?? $_GET['action'] ?? '';
$appointmentId = (int)($data['appointment_id'] ?? $_GET['appointment_id'] ?? 0);

if ($appointmentId <= 0) {
    json_response(['success' => false, 'error' => 'Valid appointment ID is required.'], 400);
}

// Fetch appointment and consultation
$apt = Database::fetchOne("SELECT * FROM appointments WHERE id = ?", [$appointmentId]);
if (!$apt) {
    json_response(['success' => false, 'error' => 'Appointment not found.'], 404);
}

$consultation = Database::fetchOne("SELECT * FROM consultations WHERE appointment_id = ?", [$appointmentId]);

switch ($action) {
    case 'start':
        // Doctor calls patient into consultation
        Security::requireCsrf();
        Auth::requireAuth(['doctor']);

        Database::execute("UPDATE appointments SET status = 'in_consultation' WHERE id = ?", [$appointmentId]);
        if ($consultation) {
            Database::execute("UPDATE consultations SET started_at = COALESCE(started_at, NOW()) WHERE id = ?", [$consultation['id']]);
        } else {
            Database::insert("
                INSERT INTO consultations (appointment_id, doctor_id, patient_id, meeting_room_id, started_at)
                VALUES (?, ?, ?, ?, NOW())
            ", [$appointmentId, $apt['doctor_id'], $apt['patient_id'], $apt['meeting_room_id']]);
        }

        json_response(['success' => true, 'message' => 'Consultation session started. Patient called.']);
        break;

    case 'save_notes':
        Security::requireCsrf();
        Auth::requireAuth(['doctor']);

        $notes = trim($data['doctor_notes'] ?? '');
        $diagnosis = trim($data['diagnosis'] ?? '');

        if ($consultation) {
            Database::execute("
                UPDATE consultations 
                SET doctor_notes = ?, diagnosis = ? 
                WHERE id = ?
            ", [$notes, $diagnosis, $consultation['id']]);
        } else {
            Database::insert("
                INSERT INTO consultations (appointment_id, doctor_id, patient_id, meeting_room_id, doctor_notes, diagnosis, started_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ", [$appointmentId, $apt['doctor_id'], $apt['patient_id'], $apt['meeting_room_id'], $notes, $diagnosis]);
        }

        json_response(['success' => true, 'message' => 'Clinical notes saved successfully.']);
        break;

    case 'end':
        Security::requireCsrf();
        Auth::requireAuth(['doctor']);

        $notes = trim($data['doctor_notes'] ?? '');
        $diagnosis = trim($data['diagnosis'] ?? '');

        Database::execute("UPDATE appointments SET status = 'completed' WHERE id = ?", [$appointmentId]);
        if ($consultation) {
            Database::execute("
                UPDATE consultations 
                SET ended_at = NOW(), doctor_notes = ?, diagnosis = ? 
                WHERE id = ?
            ", [$notes, $diagnosis, $consultation['id']]);
        }

        json_response([
            'success' => true,
            'message' => 'Consultation concluded.',
            'redirect' => base_url("doctor/create_prescription.php?appointment_id={$appointmentId}")
        ]);
        break;

    case 'get_details':
        $patient = Database::fetchOne("SELECT id, name, email, phone, gender, dob, blood_group FROM users WHERE id = ?", [$apt['patient_id']]);
        $history = Database::fetchAll("
            SELECT p.prescription_number, p.diagnosis, p.created_at, u.name as doctor_name
            FROM prescriptions p
            JOIN doctor_profiles d ON p.doctor_id = d.id
            JOIN users u ON d.user_id = u.id
            WHERE p.patient_id = ?
            ORDER BY p.created_at DESC LIMIT 5
        ", [$apt['patient_id']]);

        json_response([
            'success' => true,
            'appointment' => $apt,
            'consultation' => $consultation,
            'patient' => $patient,
            'medical_history' => $history
        ]);
        break;

    default:
        json_response(['success' => false, 'error' => 'Invalid consultation action.'], 400);
}
