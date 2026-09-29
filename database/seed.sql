-- =======================================================
-- CarePulse AI - Seed Data (Realistic Demo Hospital Data)
-- Password for all seed users: Password@123
-- =======================================================

USE `carepulse_db`;

-- 1. DEPARTMENTS
INSERT INTO `departments` (`id`, `name`, `code`, `icon`, `description`) VALUES
(1, 'Cardiology', 'CARD', 'bi-heart-pulse-fill', 'Comprehensive cardiovascular care, ECG, echo, arrhythmia management, and hypertension prevention.'),
(2, 'Neurology', 'NEUR', 'bi-activity', 'Expert diagnosis of migraines, neurological disorders, seizures, nerve damage, and stroke care.'),
(3, 'Orthopedics', 'ORTH', 'bi-bandaid-fill', 'Bone health, joint replacement, sports injuries, fractures, and spine rehabilitation.'),
(4, 'Dermatology', 'DERM', 'bi-stars', 'Skin, hair, allergy, eczema, psoriasis, acne treatments, and advanced cosmetic dermatology.'),
(5, 'General Medicine', 'GENM', 'bi-capsule', 'Primary healthcare, viral fevers, diabetes management, preventive wellness checks, and infections.'),
(6, 'Pediatrics', 'PEDI', 'bi-emoji-smile-fill', 'Newborn, infant, child healthcare, vaccinations, growth monitoring, and pediatric wellness.'),
(7, 'Psychiatry', 'PSYC', 'bi-lightbulb-fill', 'Mental health therapy, anxiety, depression, insomnia management, and psychological counseling.'),
(8, 'ENT (Otolaryngology)', 'ENT0', 'bi-soundwave', 'Ear infections, hearing loss, nasal sinusitis, tonsils, and throat care.');

-- 2. USERS
-- Hashed password for 'Password@123': $2y$10$iRvtvrnC5VDOHLyFiQXRf.AxsrlhsMOfW09ancil46MSNJjH/HMa.
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `phone`, `gender`, `dob`, `blood_group`, `is_verified`, `status`, `avatar`) VALUES
-- Admin
(1, 'Dr. Sarah Mitchell (Admin)', 'admin@carepulse.ai', '$2y$10$iRvtvrnC5VDOHLyFiQXRf.AxsrlhsMOfW09ancil46MSNJjH/HMa.', 'admin', '+18005550100', 'female', '1982-05-14', 'O+', 1, 'active', 'admin-avatar.png'),

-- Doctors
(2, 'Dr. Rajesh Sharma', 'doctor.sharma@carepulse.ai', '$2y$10$iRvtvrnC5VDOHLyFiQXRf.AxsrlhsMOfW09ancil46MSNJjH/HMa.', 'doctor', '+919876543210', 'male', '1978-08-20', 'A+', 1, 'active', 'doc-sharma.png'),
(3, 'Dr. Priya Patel', 'doctor.patel@carepulse.ai', '$2y$10$iRvtvrnC5VDOHLyFiQXRf.AxsrlhsMOfW09ancil46MSNJjH/HMa.', 'doctor', '+919876543211', 'female', '1984-11-12', 'B+', 1, 'active', 'doc-patel.png'),
(4, 'Dr. Vikram Malhotra', 'doctor.vikram@carepulse.ai', '$2y$10$iRvtvrnC5VDOHLyFiQXRf.AxsrlhsMOfW09ancil46MSNJjH/HMa.', 'doctor', '+919876543212', 'male', '1975-03-30', 'O+', 1, 'active', 'doc-vikram.png'),
(5, 'Dr. Ananya Roy', 'doctor.ananya@carepulse.ai', '$2y$10$iRvtvrnC5VDOHLyFiQXRf.AxsrlhsMOfW09ancil46MSNJjH/HMa.', 'doctor', '+919876543213', 'female', '1989-07-25', 'AB+', 1, 'active', 'doc-ananya.png'),
(6, 'Dr. Arvind Swaminathan', 'doctor.arvind@carepulse.ai', '$2y$10$iRvtvrnC5VDOHLyFiQXRf.AxsrlhsMOfW09ancil46MSNJjH/HMa.', 'doctor', '+919876543214', 'male', '1981-09-18', 'B-', 1, 'active', 'doc-arvind.png'),
(7, 'Dr. Sneha Kulkarni', 'doctor.sneha@carepulse.ai', '$2y$10$iRvtvrnC5VDOHLyFiQXRf.AxsrlhsMOfW09ancil46MSNJjH/HMa.', 'doctor', '+919876543215', 'female', '1986-12-05', 'O-', 1, 'active', 'doc-sneha.png'),

-- Patients
(8, 'John Doe (Demo Patient)', 'patient@carepulse.ai', '$2y$10$iRvtvrnC5VDOHLyFiQXRf.AxsrlhsMOfW09ancil46MSNJjH/HMa.', 'patient', '+919811223344', 'male', '1992-06-15', 'O+', 1, 'active', 'patient-john.png'),
(9, 'Emily Davis', 'emily.davis@example.com', '$2y$10$iRvtvrnC5VDOHLyFiQXRf.AxsrlhsMOfW09ancil46MSNJjH/HMa.', 'patient', '+919822334455', 'female', '1996-03-22', 'A-', 1, 'active', 'patient-emily.png');

-- 3. DOCTOR PROFILES
INSERT INTO `doctor_profiles` (`id`, `user_id`, `department_id`, `specialization`, `experience_years`, `qualification`, `consultation_fee`, `room_number`, `avg_consult_time_mins`, `rating`, `available_days`, `slot_start_time`, `slot_end_time`, `slot_duration_mins`, `is_available_online`, `bio`) VALUES
(1, 2, 1, 'Senior Interventional Cardiologist', 18, 'MBBS, MD (Med), DM (Cardiology), FACC', 800.00, 'Cardio OPD - Suite 201', 15, 4.95, 'Mon,Tue,Wed,Thu,Fri,Sat', '09:00:00', '17:00:00', 20, 1, 'Specializes in coronary interventions, preventive cardiology, heart rhythm disorders, and clinical hypertension.'),
(2, 3, 2, 'Consultant Neurologist & Neurophysiologist', 12, 'MBBS, MD, DM (Neurology)', 750.00, 'Neuro OPD - Suite 305', 20, 4.88, 'Mon,Tue,Wed,Thu,Fri', '10:00:00', '18:00:00', 20, 1, 'Expert in migraine headaches, peripheral neuropathy, epilepsy, movement disorders, and stroke rehabilitation.'),
(3, 4, 3, 'Chief Orthopedic & Joint Surgeon', 22, 'MBBS, MS (Ortho), MCh (Ortho, UK)', 900.00, 'Ortho OPD - Suite 108', 15, 4.92, 'Mon,Wed,Fri,Sat', '09:30:00', '16:30:00', 20, 1, 'Pioneer in minimally invasive joint replacements, sports injury arthroscopy, and complex trauma reconstruction.'),
(4, 5, 4, 'Consultant Dermatologist & Trichologist', 9, 'MBBS, MD (Dermatology, Venereology, Leprosy)', 650.00, 'Derma OPD - Suite 402', 15, 4.85, 'Tue,Wed,Thu,Fri,Sat', '11:00:00', '19:00:00', 20, 1, 'Special interest in acne, psoriasis, chronic eczema, laser skin therapies, and pediatric dermatoses.'),
(5, 6, 5, 'Senior Consultant Physician (Internal Medicine)', 16, 'MBBS, MD (Internal Medicine)', 500.00, 'General OPD - Suite 102', 12, 4.90, 'Mon,Tue,Wed,Thu,Fri,Sat', '08:30:00', '16:30:00', 15, 1, 'Expertise in multi-system disorders, diabetes mellitus, infectious disease control, and chronic fever investigations.'),
(6, 7, 6, 'Consultant Pediatrician & Neonatologist', 11, 'MBBS, MD (Pediatrics), DNB', 600.00, 'Pediatric OPD - Suite 115', 15, 4.91, 'Mon,Tue,Wed,Thu,Fri,Sat', '09:00:00', '17:00:00', 20, 1, 'Dedicated to compassionate pediatric care, immunization schedules, newborn screening, and adolescent health.');

-- 4. APPOINTMENTS
INSERT INTO `appointments` (`id`, `appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `appointment_time`, `appointment_type`, `queue_number`, `status`, `symptoms_summary`, `meeting_room_id`, `estimated_wait_time`, `created_at`) VALUES
(1, 'CP-2026-9001', 8, 1, 1, CURDATE(), '10:00:00', 'online_consultation', 1, 'confirmed', 'Mild chest discomfort after brisk walk, intermittent palpitations.', 'carepulse-room-cp9001', 0, NOW()),
(2, 'CP-2026-9002', 9, 2, 2, CURDATE(), '11:00:00', 'online_consultation', 1, 'confirmed', 'Throbbing frontal headache lasting 2 days, light sensitivity.', 'carepulse-room-cp9002', 0, NOW()),
(3, 'CP-2026-9003', 8, 5, 5, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:30:00', 'hospital_visit', 1, 'confirmed', 'Seasonal flu follow up, dry cough, slight fatigue.', NULL, 15, NOW());

-- 5. PAYMENTS
INSERT INTO `payments` (`id`, `appointment_id`, `patient_id`, `amount`, `payment_method`, `transaction_id`, `receipt_number`, `payment_status`, `payment_details`, `created_at`) VALUES
(1, 1, 8, 800.00, 'upi', 'TXN_UPI_9837421873', 'RCP-2026-0001', 'successful', '{"vpa":"john@upi","gateway":"CarePulse FastPay UPI","status":"SUCCESS"}', NOW()),
(2, 2, 9, 750.00, 'card', 'TXN_CRD_1029384756', 'RCP-2026-0002', 'successful', '{"card_last4":"4242","brand":"Visa","status":"SUCCESS"}', NOW()),
(3, 3, 8, 500.00, 'netbanking', 'TXN_NB_5566778899', 'RCP-2026-0003', 'successful', '{"bank":"HDFC Bank","auth_code":"HDFC9821","status":"SUCCESS"}', NOW());

-- 6. CONSULTATIONS
INSERT INTO `consultations` (`id`, `appointment_id`, `doctor_id`, `patient_id`, `meeting_room_id`, `doctor_notes`, `diagnosis`, `started_at`, `ended_at`, `created_at`) VALUES
(1, 1, 1, 8, 'carepulse-room-cp9001', 'Patient reported exertional tightness. Normal heart sounds S1/S2, regular rhythm, BP 128/82. Advised Lipid Profile and ECG.', 'Atypical Angina / Exertional Tachycardia - Mild', NOW(), NULL, NOW());

-- 7. PRESCRIPTIONS
INSERT INTO `prescriptions` (`id`, `prescription_number`, `consultation_id`, `appointment_id`, `doctor_id`, `patient_id`, `diagnosis`, `medicines`, `advice`, `follow_up_date`, `created_at`) VALUES
(1, 'RX-2026-4401', 1, 1, 1, 8, 'Mild Exertional Tachycardia & Hypertension Stage 1', 
'[
  {"medicine_name": "Metoprolol Succinate", "dosage": "25 mg", "morning": 1, "afternoon": 0, "night": 0, "duration": "30 days", "instructions": "Take once daily in morning with food"},
  {"medicine_name": "Atorvastatin", "dosage": "10 mg", "morning": 0, "afternoon": 0, "night": 1, "duration": "30 days", "instructions": "Take at bedtime with water"},
  {"medicine_name": "Aspirin (Disprin EC)", "dosage": "75 mg", "morning": 0, "afternoon": 1, "night": 0, "duration": "30 days", "instructions": "Take immediately after lunch"}
]', 
'Reduce daily sodium intake (< 2g/day). 30 minutes light aerobic walking. Avoid caffeine in evening. Report immediately if chest pain radiates to arm or jaw.', 
DATE_ADD(CURDATE(), INTERVAL 14 DAY), NOW());

-- 8. MEDICATION REMINDERS
INSERT INTO `medication_reminders` (`id`, `patient_id`, `prescription_id`, `medicine_name`, `dosage`, `instructions`, `schedule_morning`, `schedule_afternoon`, `schedule_night`, `time_morning`, `time_afternoon`, `time_night`, `channel_sms`, `channel_whatsapp`, `channel_email`, `start_date`, `end_date`, `status`) VALUES
(1, 8, 1, 'Metoprolol Succinate', '25 mg', 'Take once daily in morning with breakfast', 1, 0, 0, '08:00:00', '13:00:00', '20:30:00', 1, 1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active'),
(2, 8, 1, 'Aspirin (Disprin EC)', '75 mg', 'Take immediately after lunch', 0, 1, 0, '08:00:00', '13:30:00', '20:30:00', 1, 1, 0, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active'),
(3, 8, 1, 'Atorvastatin', '10 mg', 'Take at bedtime with water', 0, 0, 1, '08:00:00', '13:00:00', '21:00:00', 1, 1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active');

-- 9. MEDICATION LOGS
INSERT INTO `medication_logs` (`reminder_id`, `patient_id`, `channel`, `sent_time`, `message_text`, `status`) VALUES
(1, 8, 'sms', NOW(), 'CarePulse Alert: Please take Metoprolol Succinate (25 mg) now with breakfast. Stay healthy!', 'delivered'),
(1, 8, 'whatsapp', NOW(), 'CarePulse WhatsApp Reminder: Hi John, time for Metoprolol 25mg. Stay healthy!', 'delivered');

-- 10. SYMPTOM HISTORY
INSERT INTO `symptom_history` (`patient_id`, `symptoms_input`, `predicted_department_id`, `confidence_score`, `top_predictions`, `recommended_doctor_ids`, `created_at`) VALUES
(8, 'chest tightness, shortness of breath on exertion, slight dizziness', 1, 94.60, '{"Cardiology": 94.6, "General Medicine": 3.8, "Neurology": 1.6}', '[1]', NOW());

-- 11. OPD DAILY STATS (Historical 30 days for Scikit-learn training & Chart.js)
INSERT INTO `opd_daily_stats` (`stat_date`, `total_patients`, `online_consultations`, `hospital_visits`, `emergency_cases`, `day_of_week`, `is_holiday`, `weather_condition`, `avg_wait_time_mins`, `total_revenue`) VALUES
(DATE_SUB(CURDATE(), INTERVAL 29 DAY), 112, 45, 67, 8, 0, 0, 'Clear', 17.5, 78400.00),
(DATE_SUB(CURDATE(), INTERVAL 28 DAY), 105, 42, 63, 6, 1, 0, 'Sunny', 16.0, 73500.00),
(DATE_SUB(CURDATE(), INTERVAL 27 DAY), 98, 38, 60, 5, 2, 0, 'Clear', 15.0, 68600.00),
(DATE_SUB(CURDATE(), INTERVAL 26 DAY), 110, 44, 66, 7, 3, 0, 'Cloudy', 17.0, 77000.00),
(DATE_SUB(CURDATE(), INTERVAL 25 DAY), 125, 52, 73, 9, 4, 0, 'Clear', 19.5, 87500.00),
(DATE_SUB(CURDATE(), INTERVAL 24 DAY), 140, 60, 80, 11, 5, 0, 'Sunny', 21.0, 98000.00),
(DATE_SUB(CURDATE(), INTERVAL 23 DAY), 65, 25, 40, 4, 6, 1, 'Clear', 12.0, 45500.00),
(DATE_SUB(CURDATE(), INTERVAL 22 DAY), 118, 48, 70, 7, 0, 0, 'Rainy', 18.0, 82600.00),
(DATE_SUB(CURDATE(), INTERVAL 21 DAY), 108, 43, 65, 5, 1, 0, 'Cloudy', 16.5, 75600.00),
(DATE_SUB(CURDATE(), INTERVAL 20 DAY), 102, 40, 62, 6, 2, 0, 'Clear', 15.5, 71400.00),
(DATE_SUB(CURDATE(), INTERVAL 19 DAY), 115, 47, 68, 8, 3, 0, 'Sunny', 17.5, 80500.00),
(DATE_SUB(CURDATE(), INTERVAL 18 DAY), 130, 55, 75, 10, 4, 0, 'Clear', 20.0, 91000.00),
(DATE_SUB(CURDATE(), INTERVAL 17 DAY), 145, 62, 83, 12, 5, 0, 'Sunny', 22.5, 101500.00),
(DATE_SUB(CURDATE(), INTERVAL 16 DAY), 70, 28, 42, 5, 6, 1, 'Clear', 12.5, 49000.00),
(DATE_SUB(CURDATE(), INTERVAL 15 DAY), 122, 50, 72, 8, 0, 0, 'Clear', 18.5, 85400.00),
(DATE_SUB(CURDATE(), INTERVAL 14 DAY), 114, 46, 68, 6, 1, 0, 'Sunny', 17.0, 79800.00),
(DATE_SUB(CURDATE(), INTERVAL 13 DAY), 106, 42, 64, 5, 2, 0, 'Cloudy', 16.0, 74200.00),
(DATE_SUB(CURDATE(), INTERVAL 12 DAY), 119, 49, 70, 7, 3, 0, 'Clear', 18.0, 83300.00),
(DATE_SUB(CURDATE(), INTERVAL 11 DAY), 135, 58, 77, 9, 4, 0, 'Clear', 20.5, 94500.00),
(DATE_SUB(CURDATE(), INTERVAL 10 DAY), 150, 65, 85, 13, 5, 0, 'Sunny', 23.0, 105000.00),
(DATE_SUB(CURDATE(), INTERVAL 9 DAY), 75, 30, 45, 4, 6, 1, 'Clear', 13.0, 52500.00),
(DATE_SUB(CURDATE(), INTERVAL 8 DAY), 128, 54, 74, 9, 0, 0, 'Rainy', 19.0, 89600.00),
(DATE_SUB(CURDATE(), INTERVAL 7 DAY), 116, 47, 69, 7, 1, 0, 'Cloudy', 17.0, 81200.00),
(DATE_SUB(CURDATE(), INTERVAL 6 DAY), 110, 45, 65, 6, 2, 0, 'Clear', 16.5, 77000.00),
(DATE_SUB(CURDATE(), INTERVAL 5 DAY), 124, 51, 73, 8, 3, 0, 'Sunny', 18.5, 86800.00),
(DATE_SUB(CURDATE(), INTERVAL 4 DAY), 138, 59, 79, 10, 4, 0, 'Clear', 21.0, 96600.00),
(DATE_SUB(CURDATE(), INTERVAL 3 DAY), 152, 66, 86, 12, 5, 0, 'Sunny', 23.5, 106400.00),
(DATE_SUB(CURDATE(), INTERVAL 2 DAY), 78, 32, 46, 5, 6, 1, 'Clear', 13.5, 54600.00),
(DATE_SUB(CURDATE(), INTERVAL 1 DAY), 132, 56, 76, 9, 0, 0, 'Clear', 19.5, 92400.00);
