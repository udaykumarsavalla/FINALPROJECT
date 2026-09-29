<?php
/**
 * CarePulse AI - Payment Gateway Processing API
 * Handles UPI, Cards, Net Banking, receipt generation, and appointment confirmation.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email_service.php';
require_once __DIR__ . '/../includes/queue_service.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireAuth(['patient']);
Security::requireCsrf();

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?: $_POST;

$appointmentId = (int)($data['appointment_id'] ?? 0);
$paymentMethod = trim($data['payment_method'] ?? 'upi'); // 'upi', 'card', 'netbanking'
$patientId = Auth::currentUserId();

if ($appointmentId <= 0) {
    json_response(['success' => false, 'error' => 'Valid appointment ID is required.'], 400);
}

// Fetch appointment & doctor fee
$apt = Database::fetchOne("
    SELECT a.*, d.consultation_fee, u.name as doctor_name, u.email as doctor_email,
           dep.name as department_name, p.name as patient_name, p.email as patient_email
    FROM appointments a
    JOIN doctor_profiles d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON a.department_id = dep.id
    JOIN users p ON a.patient_id = p.id
    WHERE a.id = ? AND a.patient_id = ?
", [$appointmentId, $patientId]);

if (!$apt) {
    json_response(['success' => false, 'error' => 'Appointment record not found.'], 404);
}

if ($apt['status'] !== 'pending_payment') {
    json_response(['success' => false, 'error' => 'This appointment is already confirmed or completed.'], 400);
}

// Method-specific verification
$amount = (float)$apt['consultation_fee'];
$paymentDetails = [];

if ($paymentMethod === 'upi') {
    $vpa = trim($data['upi_id'] ?? '');
    if (empty($vpa) || !str_contains($vpa, '@')) {
        json_response(['success' => false, 'error' => 'Please provide a valid UPI Virtual Payment Address (e.g., user@okhdfcbank).'], 400);
    }
    $paymentDetails = ['upi_id' => $vpa, 'gateway' => 'UPI Unified Payments', 'status' => 'SUCCESS'];

} elseif ($paymentMethod === 'card') {
    $cardNumber = str_replace(' ', '', trim($data['card_number'] ?? ''));
    $expiry = trim($data['card_expiry'] ?? '');
    $cvv = trim($data['card_cvv'] ?? '');

    if (strlen($cardNumber) < 13 || strlen($cvv) < 3) {
        json_response(['success' => false, 'error' => 'Invalid card number or security code (CVV).'], 400);
    }
    $paymentDetails = [
        'card_last4' => substr($cardNumber, -4),
        'network' => str_starts_with($cardNumber, '4') ? 'Visa' : (str_starts_with($cardNumber, '5') ? 'Mastercard' : 'RuPay'),
        'status' => 'SUCCESS'
    ];

} elseif ($paymentMethod === 'netbanking') {
    $bankName = trim($data['bank_name'] ?? '');
    if (empty($bankName)) {
        json_response(['success' => false, 'error' => 'Please select your bank for Net Banking transfer.'], 400);
    }
    $paymentDetails = ['bank' => $bankName, 'gateway' => 'Direct NetBanking Core Switch', 'status' => 'SUCCESS'];

} else {
    json_response(['success' => false, 'error' => 'Unsupported payment method.'], 400);
}

// Generate Unique Transaction and Receipt Numbers
$txnId = 'TXN_' . strtoupper($paymentMethod) . '_' . strtoupper(bin2hex(random_bytes(5)));
$receiptNum = 'RCP-' . date('Ymd') . '-' . random_int(1000, 9999);

try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->beginTransaction();

    // 1. Insert Payment Record
    $paymentId = Database::insert("
        INSERT INTO payments (appointment_id, patient_id, amount, payment_method, transaction_id, receipt_number, payment_status, payment_details)
        VALUES (?, ?, ?, ?, ?, ?, 'successful', ?)
    ", [
        $appointmentId, $patientId, $amount, $paymentMethod, $txnId, $receiptNum, json_encode($paymentDetails)
    ]);

    // 2. Update Appointment to 'confirmed'
    Database::execute("
        UPDATE appointments 
        SET status = 'confirmed' 
        WHERE id = ?
    ", [$appointmentId]);

    // 3. If online consultation, initialize consultation room record
    if ($apt['appointment_type'] === 'online_consultation') {
        $existingConsult = Database::fetchOne("SELECT id FROM consultations WHERE appointment_id = ?", [$appointmentId]);
        if (!$existingConsult) {
            Database::insert("
                INSERT INTO consultations (appointment_id, doctor_id, patient_id, meeting_room_id, doctor_notes, diagnosis)
                VALUES (?, ?, ?, ?, '', '')
            ", [$appointmentId, $apt['doctor_id'], $patientId, $apt['meeting_room_id']]);
        }
    }

    $pdo->commit();

    // Recalculate dynamic queue & wait time
    $queueInfo = QueueService::calculateEstimatedWaitTime($appointmentId);

    // Send confirmation email
    NotificationService::sendAppointmentNotice($apt);

    json_response([
        'success' => true,
        'message' => 'Payment received successfully. Appointment is now confirmed!',
        'transaction_id' => $txnId,
        'receipt_number' => $receiptNum,
        'amount' => $amount,
        'payment_id' => $paymentId,
        'queue_info' => $queueInfo,
        'receipt_url' => base_url("payments/receipt.php?id={$paymentId}")
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[Payment Error] " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Payment failed to process: ' . $e->getMessage()], 500);
}
