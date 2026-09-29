-- =======================================================
-- CarePulse AI - Intelligent Telehealth & Smart Hospital Platform
-- Database Schema (MySQL 8 / MariaDB compatible)
-- =======================================================

CREATE DATABASE IF NOT EXISTS `carepulse_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `carepulse_db`;

-- Drop existing tables in reverse dependency order
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `medication_logs`;
DROP TABLE IF EXISTS `medication_reminders`;
DROP TABLE IF EXISTS `symptom_history`;
DROP TABLE IF EXISTS `medical_records`;
DROP TABLE IF EXISTS `prescriptions`;
DROP TABLE IF EXISTS `consultations`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `doctor_profiles`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `opd_daily_stats`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS TABLE
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('patient', 'doctor', 'admin') NOT NULL DEFAULT 'patient',
    `phone` VARCHAR(20) DEFAULT NULL,
    `gender` ENUM('male', 'female', 'other') DEFAULT 'other',
    `dob` DATE DEFAULT NULL,
    `blood_group` VARCHAR(5) DEFAULT NULL,
    `otp_code` VARCHAR(10) DEFAULT NULL,
    `otp_expiry` DATETIME DEFAULT NULL,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `avatar` VARCHAR(255) DEFAULT 'default-avatar.png',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. DEPARTMENTS TABLE
CREATE TABLE `departments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `icon` VARCHAR(50) DEFAULT 'bi-heart-pulse',
    `description` TEXT DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. DOCTOR PROFILES TABLE
CREATE TABLE `doctor_profiles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL UNIQUE,
    `department_id` INT UNSIGNED NOT NULL,
    `specialization` VARCHAR(150) NOT NULL,
    `experience_years` INT UNSIGNED NOT NULL DEFAULT 1,
    `qualification` VARCHAR(200) NOT NULL DEFAULT 'MBBS, MD',
    `consultation_fee` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    `room_number` VARCHAR(30) DEFAULT 'Room 101',
    `avg_consult_time_mins` INT UNSIGNED NOT NULL DEFAULT 15,
    `rating` DECIMAL(3,2) NOT NULL DEFAULT 4.80,
    `available_days` VARCHAR(100) DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat',
    `slot_start_time` TIME NOT NULL DEFAULT '09:00:00',
    `slot_end_time` TIME NOT NULL DEFAULT '17:00:00',
    `slot_duration_mins` INT NOT NULL DEFAULT 20,
    `is_available_online` TINYINT(1) NOT NULL DEFAULT 1,
    `bio` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. APPOINTMENTS TABLE
CREATE TABLE `appointments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `appointment_number` VARCHAR(30) NOT NULL UNIQUE,
    `patient_id` INT UNSIGNED NOT NULL,
    `doctor_id` INT UNSIGNED NOT NULL,
    `department_id` INT UNSIGNED NOT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `appointment_type` ENUM('hospital_visit', 'online_consultation') NOT NULL DEFAULT 'online_consultation',
    `queue_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `status` ENUM('pending_payment', 'confirmed', 'in_consultation', 'completed', 'cancelled') NOT NULL DEFAULT 'pending_payment',
    `symptoms_summary` TEXT DEFAULT NULL,
    `meeting_room_id` VARCHAR(100) DEFAULT NULL,
    `estimated_wait_time` INT UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_doctor_slot` (`doctor_id`, `appointment_date`, `appointment_time`),
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT,
    INDEX `idx_date_doctor` (`appointment_date`, `doctor_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. PAYMENTS TABLE
CREATE TABLE `payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `appointment_id` INT UNSIGNED NOT NULL,
    `patient_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('upi', 'card', 'netbanking') NOT NULL,
    `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
    `receipt_number` VARCHAR(50) NOT NULL UNIQUE,
    `payment_status` ENUM('pending', 'successful', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `payment_details` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. CONSULTATIONS TABLE
CREATE TABLE `consultations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `appointment_id` INT UNSIGNED NOT NULL UNIQUE,
    `doctor_id` INT UNSIGNED NOT NULL,
    `patient_id` INT UNSIGNED NOT NULL,
    `meeting_room_id` VARCHAR(100) NOT NULL,
    `doctor_notes` TEXT DEFAULT NULL,
    `diagnosis` VARCHAR(255) DEFAULT NULL,
    `started_at` DATETIME DEFAULT NULL,
    `ended_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. PRESCRIPTIONS TABLE
CREATE TABLE `prescriptions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `prescription_number` VARCHAR(50) NOT NULL UNIQUE,
    `consultation_id` INT UNSIGNED DEFAULT NULL,
    `appointment_id` INT UNSIGNED NOT NULL,
    `doctor_id` INT UNSIGNED NOT NULL,
    `patient_id` INT UNSIGNED NOT NULL,
    `diagnosis` VARCHAR(255) DEFAULT NULL,
    `medicines` JSON NOT NULL,
    `advice` TEXT DEFAULT NULL,
    `follow_up_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. MEDICAL RECORDS TABLE (Vault)
CREATE TABLE `medical_records` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `record_type` ENUM('lab_report', 'prescription', 'scan', 'discharge_summary', 'other') NOT NULL DEFAULT 'lab_report',
    `file_name` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. SYMPTOM HISTORY TABLE
CREATE TABLE `symptom_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED DEFAULT NULL,
    `symptoms_input` TEXT NOT NULL,
    `predicted_department_id` INT UNSIGNED NOT NULL,
    `confidence_score` DECIMAL(5,2) NOT NULL,
    `top_predictions` JSON DEFAULT NULL,
    `recommended_doctor_ids` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`predicted_department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. MEDICATION REMINDERS TABLE
CREATE TABLE `medication_reminders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL,
    `prescription_id` INT UNSIGNED DEFAULT NULL,
    `medicine_name` VARCHAR(150) NOT NULL,
    `dosage` VARCHAR(100) NOT NULL,
    `instructions` VARCHAR(255) DEFAULT 'Take with water after food',
    `schedule_morning` TINYINT(1) NOT NULL DEFAULT 1,
    `schedule_afternoon` TINYINT(1) NOT NULL DEFAULT 0,
    `schedule_night` TINYINT(1) NOT NULL DEFAULT 1,
    `time_morning` TIME NOT NULL DEFAULT '08:00:00',
    `time_afternoon` TIME NOT NULL DEFAULT '13:00:00',
    `time_night` TIME NOT NULL DEFAULT '20:30:00',
    `channel_sms` TINYINT(1) NOT NULL DEFAULT 1,
    `channel_whatsapp` TINYINT(1) NOT NULL DEFAULT 1,
    `channel_email` TINYINT(1) NOT NULL DEFAULT 1,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('active', 'paused', 'completed') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. MEDICATION LOGS TABLE (Delivery tracker)
CREATE TABLE `medication_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reminder_id` INT UNSIGNED NOT NULL,
    `patient_id` INT UNSIGNED NOT NULL,
    `channel` ENUM('sms', 'whatsapp', 'email') NOT NULL,
    `sent_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `message_text` TEXT NOT NULL,
    `status` ENUM('delivered', 'mock_sent', 'failed') NOT NULL DEFAULT 'delivered',
    FOREIGN KEY (`reminder_id`) REFERENCES `medication_reminders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. OPD DAILY STATS (For Analytics & Scikit-learn predictive model)
CREATE TABLE `opd_daily_stats` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `stat_date` DATE NOT NULL UNIQUE,
    `total_patients` INT UNSIGNED NOT NULL,
    `online_consultations` INT UNSIGNED NOT NULL,
    `hospital_visits` INT UNSIGNED NOT NULL,
    `emergency_cases` INT UNSIGNED NOT NULL DEFAULT 0,
    `day_of_week` INT UNSIGNED NOT NULL, -- 0 = Monday, 6 = Sunday
    `is_holiday` TINYINT(1) NOT NULL DEFAULT 0,
    `weather_condition` VARCHAR(50) DEFAULT 'Clear',
    `avg_wait_time_mins` DECIMAL(5,2) NOT NULL DEFAULT 18.5,
    `total_revenue` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
