<?php
/**
 * CarePulse AI - Multi-Channel Notification Service (Email, SMS, WhatsApp)
 * Handles automated alerts, OTP dispatches, and medication schedule notifications.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class NotificationService {

    /**
     * Send OTP Verification Email
     */
    public static function sendOtpEmail(string $recipientEmail, string $userName, string $otp, string $purpose = 'Registration Verification'): bool {
        $subject = "Your CarePulse AI Verification Code: $otp";
        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #0d9488; margin: 0;'>CarePulse AI</h2>
                <p style='color: #64748b; font-size: 14px; margin-top: 4px;'>Intelligent Telehealth & Smart Hospital Platform</p>
            </div>
            <div style='padding: 20px; background-color: #f8fafc; border-radius: 8px;'>
                <p style='color: #334155; font-size: 16px;'>Hello <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                <p style='color: #475569;'>Your one-time passcode for <strong>$purpose</strong> is:</p>
                <div style='text-align: center; margin: 25px 0;'>
                    <span style='display: inline-block; font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #0d9488; background: #ccfbf1; padding: 12px 28px; border-radius: 8px; border: 1px dashed #0d9488;'>$otp</span>
                </div>
                <p style='color: #64748b; font-size: 13px; margin: 0;'>This code will expire in 10 minutes. If you did not request this, please ignore this email.</p>
            </div>
            <p style='color: #94a3b8; font-size: 12px; text-align: center; margin-top: 24px;'>&copy; " . date('Y') . " CarePulse AI Hospital Systems. All rights reserved.</p>
        </div>";

        return self::dispatchEmail($recipientEmail, $subject, $html);
    }

    /**
     * Send Appointment Confirmation Notice
     */
    public static function sendAppointmentNotice(array $appointment): bool {
        $subject = "Appointment Confirmed: #" . $appointment['appointment_number'];
        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;'>
            <h2 style='color: #0d9488;'>CarePulse AI Appointment Confirmation</h2>
            <p>Your appointment has been successfully scheduled and confirmed.</p>
            <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                <tr><td style='padding: 8px; border-bottom: 1px solid #f1f5f9;'><strong>Appointment No:</strong></td><td style='padding: 8px; border-bottom: 1px solid #f1f5f9;'>" . $appointment['appointment_number'] . "</td></tr>
                <tr><td style='padding: 8px; border-bottom: 1px solid #f1f5f9;'><strong>Doctor:</strong></td><td style='padding: 8px; border-bottom: 1px solid #f1f5f9;'>" . ($appointment['doctor_name'] ?? 'Doctor') . "</td></tr>
                <tr><td style='padding: 8px; border-bottom: 1px solid #f1f5f9;'><strong>Department:</strong></td><td style='padding: 8px; border-bottom: 1px solid #f1f5f9;'>" . ($appointment['department_name'] ?? 'Specialty') . "</td></tr>
                <tr><td style='padding: 8px; border-bottom: 1px solid #f1f5f9;'><strong>Date & Time:</strong></td><td style='padding: 8px; border-bottom: 1px solid #f1f5f9;'>" . $appointment['appointment_date'] . " at " . date('h:i A', strtotime($appointment['appointment_time'])) . "</td></tr>
                <tr><td style='padding: 8px; border-bottom: 1px solid #f1f5f9;'><strong>Queue Token:</strong></td><td style='padding: 8px; border-bottom: 1px solid #f1f5f9; font-size: 18px; font-weight: bold; color: #0d9488;'>#" . $appointment['queue_number'] . "</td></tr>
                <tr><td style='padding: 8px;'><strong>Type:</strong></td><td style='padding: 8px;'>" . ucwords(str_replace('_', ' ', $appointment['appointment_type'])) . "</td></tr>
            </table>
            " . ($appointment['appointment_type'] === 'online_consultation' ? "
            <div style='margin-top: 20px; text-align: center;'>
                <a href='" . base_url('patient/video_consultation.php?room=' . urlencode($appointment['meeting_room_id'])) . "' style='background-color: #0d9488; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Join Video Meeting</a>
            </div>" : "") . "
        </div>";

        return self::dispatchEmail($appointment['patient_email'] ?? '', $subject, $html);
    }

    /**
     * Dispatch Medication Reminder across selected channels (SMS, WhatsApp, Email)
     */
    public static function dispatchMedicationReminder(int $reminderId): array {
        $reminder = Database::fetchOne("
            SELECT r.*, u.name as patient_name, u.email as patient_email, u.phone as patient_phone
            FROM medication_reminders r
            JOIN users u ON r.patient_id = u.id
            WHERE r.id = ?
        ", [$reminderId]);

        if (!$reminder) {
            return ['success' => false, 'error' => 'Reminder not found.'];
        }

        $results = [];
        $msgText = "CarePulse Rx Reminder: Dear {$reminder['patient_name']}, it is time for your scheduled medicine: {$reminder['medicine_name']} ({$reminder['dosage']}). Instructions: {$reminder['instructions']}. Please stay healthy!";

        // 1. Dispatch Email
        if ($reminder['channel_email'] && !empty($reminder['patient_email'])) {
            $subject = "Medication Reminder: {$reminder['medicine_name']} ({$reminder['dosage']})";
            $html = "
            <div style='font-family: Arial, sans-serif; max-width: 540px; margin: 0 auto; padding: 20px; border: 1px solid #cbd5e1; border-radius: 10px; background: #ffffff;'>
                <div style='background: #0d9488; color: white; padding: 15px; border-radius: 8px 8px 0 0; text-align: center;'>
                    <h3 style='margin: 0;'>CarePulse AI Medication Reminder</h3>
                </div>
                <div style='padding: 20px; background: #f8fafc;'>
                    <p>Dear <strong>{$reminder['patient_name']}</strong>,</p>
                    <p>This is your automated healthcare reminder to take your prescribed medication:</p>
                    <div style='background: #ffffff; padding: 15px; border-left: 4px solid #0d9488; border-radius: 4px; margin: 15px 0;'>
                        <h4 style='margin: 0 0 5px 0; color: #0f172a;'>{$reminder['medicine_name']}</h4>
                        <p style='margin: 0; color: #475569;'><strong>Dosage:</strong> {$reminder['dosage']}</p>
                        <p style='margin: 4px 0 0 0; color: #64748b;'><strong>Notes:</strong> {$reminder['instructions']}</p>
                    </div>
                </div>
            </div>";

            self::dispatchEmail($reminder['patient_email'], $subject, $html);
            self::logDispatch($reminder['id'], $reminder['patient_id'], 'email', $msgText, 'delivered');
            $results['email'] = 'Delivered to ' . $reminder['patient_email'];
        }

        // 2. Dispatch SMS Simulator
        if ($reminder['channel_sms'] && !empty($reminder['patient_phone'])) {
            self::dispatchSms($reminder['patient_phone'], $msgText);
            self::logDispatch($reminder['id'], $reminder['patient_id'], 'sms', $msgText, 'delivered');
            $results['sms'] = 'Delivered SMS to ' . $reminder['patient_phone'];
        }

        // 3. Dispatch WhatsApp Simulator
        if ($reminder['channel_whatsapp'] && !empty($reminder['patient_phone'])) {
            self::dispatchWhatsApp($reminder['patient_phone'], $msgText);
            self::logDispatch($reminder['id'], $reminder['patient_id'], 'whatsapp', $msgText, 'delivered');
            $results['whatsapp'] = 'Delivered WhatsApp to ' . $reminder['patient_phone'];
        }

        return ['success' => true, 'dispatched' => $results];
    }

    private static function dispatchEmail(string $to, string $subject, string $html): bool {
        if (empty($to)) return false;
        
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: CarePulse AI Platform <no-reply@carepulse.ai>\r\n";
        $headers .= "Reply-To: support@carepulse.ai\r\n";

        // Try native mail, otherwise fallback gracefully
        @mail($to, $subject, $html, $headers);

        // Record to simulated alert log for auditing & dev preview
        self::logToFile("EMAIL -> To: $to | Subject: $subject");
        return true;
    }

    private static function dispatchSms(string $phone, string $msg): bool {
        self::logToFile("SMS -> To: $phone | Body: $msg");
        return true;
    }

    private static function dispatchWhatsApp(string $phone, string $msg): bool {
        self::logToFile("WHATSAPP -> To: $phone | Body: $msg");
        return true;
    }

    private static function logDispatch(int $reminderId, int $patientId, string $channel, string $message, string $status): void {
        Database::insert(
            "INSERT INTO medication_logs (reminder_id, patient_id, channel, message_text, status) VALUES (?, ?, ?, ?, ?)",
            [$reminderId, $patientId, $channel, $message, $status]
        );
    }

    private static function logToFile(string $text): void {
        $logFile = BASE_DIR . '/notifications.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] " . $text . "\n";
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
