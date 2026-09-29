<?php
/**
 * CarePulse AI - Smart Queue & Wait-Time Prediction Engine
 * Computes live doctor workload, slot availability, and dynamic waiting time predictions.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class QueueService {

    /**
     * Prevent double booking: check if doctor already has an active appointment for date & time
     */
    public static function isSlotAvailable(int $doctorId, string $date, string $time): bool {
        $count = (int)Database::fetchOne("
            SELECT COUNT(*) as cnt 
            FROM appointments 
            WHERE doctor_id = ? 
              AND appointment_date = ? 
              AND appointment_time = ? 
              AND status NOT IN ('cancelled')
        ", [$doctorId, $date, $time])['cnt'];

        return $count === 0;
    }

    /**
     * Generate dynamic queue number for a doctor on a specific date
     */
    public static function getNextQueueNumber(int $doctorId, string $date): int {
        $maxQueue = (int)Database::fetchOne("
            SELECT COALESCE(MAX(queue_number), 0) as max_q 
            FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? AND status NOT IN ('cancelled')
        ", [$doctorId, $date])['max_q'];

        return $maxQueue + 1;
    }

    /**
     * Compute dynamic estimated wait time in minutes for an appointment
     * Based on:
     * - Number of confirmed/in-progress patients ahead in queue
     * - Doctor's average consultation duration
     * - Buffer factor for transitions / emergencies
     */
    public static function calculateEstimatedWaitTime(int $appointmentId): array {
        $apt = Database::fetchOne("
            SELECT a.*, d.avg_consult_time_mins, u.name as doctor_name, dep.name as department_name
            FROM appointments a
            JOIN doctor_profiles d ON a.doctor_id = d.id
            JOIN users u ON d.user_id = u.id
            JOIN departments dep ON a.department_id = dep.id
            WHERE a.id = ?
        ", [$appointmentId]);

        if (!$apt) {
            return ['estimated_minutes' => 0, 'queue_position' => 1, 'ahead_count' => 0];
        }

        $doctorId = $apt['doctor_id'];
        $date = $apt['appointment_date'];
        $myQueueNum = (int)$apt['queue_number'];
        $avgConsultMins = (int)$apt['avg_consult_time_mins'] ?: 15;

        // Count how many patients ahead of this one are not yet completed
        $aheadRow = Database::fetchOne("
            SELECT COUNT(*) as ahead 
            FROM appointments 
            WHERE doctor_id = ? 
              AND appointment_date = ? 
              AND queue_number < ? 
              AND status IN ('confirmed', 'in_consultation')
        ", [$doctorId, $date, $myQueueNum]);

        $aheadCount = (int)($aheadRow['ahead'] ?? 0);

        // Find the token currently in consultation
        $currentServing = Database::fetchOne("
            SELECT queue_number 
            FROM appointments 
            WHERE doctor_id = ? 
              AND appointment_date = ? 
              AND status = 'in_consultation'
            ORDER BY queue_number ASC 
            LIMIT 1
        ", [$doctorId, $date]);

        $currentServingToken = $currentServing ? (int)$currentServing['queue_number'] : max(1, $myQueueNum - $aheadCount);

        // Calculate dynamic wait time
        $estimatedMins = $aheadCount * $avgConsultMins;
        
        // If current appointment is in consultation, wait time is 0
        if ($apt['status'] === 'in_consultation') {
            $estimatedMins = 0;
        } elseif ($apt['status'] === 'completed') {
            $estimatedMins = 0;
        }

        // Persist update
        Database::execute("UPDATE appointments SET estimated_wait_time = ? WHERE id = ?", [$estimatedMins, $appointmentId]);

        return [
            'appointment_id' => $appointmentId,
            'appointment_number' => $apt['appointment_number'],
            'queue_number' => $myQueueNum,
            'current_serving_token' => $currentServingToken,
            'patients_ahead' => $aheadCount,
            'avg_consult_time_mins' => $avgConsultMins,
            'estimated_wait_minutes' => $estimatedMins,
            'status' => $apt['status']
        ];
    }

    /**
     * Get available time slots for a doctor on a given date
     */
    public static function getDoctorSlots(int $doctorId, string $date): array {
        $doctor = Database::fetchOne("
            SELECT slot_start_time, slot_end_time, slot_duration_mins, available_days
            FROM doctor_profiles
            WHERE id = ?
        ", [$doctorId]);

        if (!$doctor) return [];

        // Check if doctor is available on this day of week
        $dayOfWeekShort = date('D', strtotime($date)); // e.g. Mon, Tue
        $availableDays = explode(',', $doctor['available_days']);
        if (!in_array($dayOfWeekShort, $availableDays)) {
            return []; // Doctor does not practice on this day
        }

        // Fetch already booked slots for this date
        $bookedSlots = Database::fetchAll("
            SELECT appointment_time 
            FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? AND status NOT IN ('cancelled')
        ", [$doctorId, $date]);

        $bookedTimes = array_map(function($row) {
            return date('H:i', strtotime($row['appointment_time']));
        }, $bookedSlots);

        $slots = [];
        $start = strtotime($doctor['slot_start_time']);
        $end = strtotime($doctor['slot_end_time']);
        $step = ($doctor['slot_duration_mins'] ?: 20) * 60;

        while ($start < $end) {
            $timeStr = date('H:i', $start);
            $isBooked = in_array($timeStr, $bookedTimes);
            $isPast = ($date === date('Y-m-d') && $start <= time());

            $slots[] = [
                'time_24' => $timeStr,
                'time_12' => date('h:i A', $start),
                'available' => (!$isBooked && !$isPast),
                'reason' => $isBooked ? 'Booked' : ($isPast ? 'Past' : 'Available')
            ];

            $start += $step;
        }

        return $slots;
    }
}
