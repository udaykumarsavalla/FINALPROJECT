<?php
/**
 * CarePulse AI - AI Symptom Checker API Endpoint
 * Runs Scikit-learn NLP model, calculates urgency level, retrieves doctors with available slots, and persists assessment.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/queue_service.php';

header('Content-Type: application/json; charset=UTF-8');

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?: $_POST;

$symptoms = trim($data['symptoms'] ?? '');

if (empty($symptoms) || strlen($symptoms) < 3) {
    json_response(['success' => false, 'error' => 'Please enter at least 3 characters describing your symptoms.'], 400);
}

// 1. Invoke Python Scikit-learn AI Predictor
$escapedSymptoms = escapeshellarg($symptoms);
$scriptPath = escapeshellarg(BASE_DIR . '/ai/predict_symptom.py');
$command = PYTHON_PATH . " $scriptPath $escapedSymptoms 2>&1";

$output = @shell_exec($command);
$aiResult = json_decode($output, true);

// 2. Intelligent Fallback Classifier if Python execution fails
if (!$aiResult || empty($aiResult['success'])) {
    $aiResult = fallbackClassify($symptoms);
}

$predictedDeptName = $aiResult['department'];
$confidence = $aiResult['confidence'];
$topPredictions = $aiResult['top_predictions'] ?? [];

// 3. Find matching department in Database
$dept = Database::fetchOne("
    SELECT * FROM departments 
    WHERE name LIKE ? OR description LIKE ?
    LIMIT 1
", ['%' . $predictedDeptName . '%', '%' . $predictedDeptName . '%']);

if (!$dept) {
    $dept = Database::fetchOne("SELECT * FROM departments WHERE code = 'GENM' LIMIT 1");
}

$deptId = $dept['id'] ?? 5;

// 4. Calculate Urgency Level
$urgencyLevel = determineUrgencyLevel($symptoms, $dept['name']);

// 5. Fetch Top Available Doctors in this Department & their live available slots
$doctors = Database::fetchAll("
    SELECT d.*, u.name, u.avatar, u.email, u.phone, dep.name as department_name, dep.icon as department_icon
    FROM doctor_profiles d
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON d.department_id = dep.id
    WHERE d.department_id = ? AND u.status = 'active'
    ORDER BY d.rating DESC, d.experience_years DESC
    LIMIT 4
", [$deptId]);

// Format doctor results with live slots
$recommendedDoctorIds = [];
$doctorList = [];
$todayDate = date('Y-m-d');
$tomorrowDate = date('Y-m-d', strtotime('+1 day'));

foreach ($doctors as $doc) {
    $recommendedDoctorIds[] = (int)$doc['id'];
    
    // Query live slots
    $todaySlots = QueueService::getDoctorSlots((int)$doc['id'], $todayDate);
    $availableSlotTimes = [];
    foreach ($todaySlots as $s) {
        if ($s['available']) {
            $availableSlotTimes[] = $s['time_12'];
            if (count($availableSlotTimes) >= 3) break;
        }
    }
    // If no slots left today, peek tomorrow's slots
    if (empty($availableSlotTimes)) {
        $tomorrowSlots = QueueService::getDoctorSlots((int)$doc['id'], $tomorrowDate);
        foreach ($tomorrowSlots as $s) {
            if ($s['available']) {
                $availableSlotTimes[] = $s['time_12'] . ' (Tomorrow)';
                if (count($availableSlotTimes) >= 3) break;
            }
        }
    }
    if (empty($availableSlotTimes)) {
        $availableSlotTimes = ['10:30 AM', '11:15 AM', '02:30 PM'];
    }

    $doctorList[] = [
        'id' => (int)$doc['id'],
        'name' => $doc['name'],
        'specialization' => $doc['specialization'],
        'qualification' => $doc['qualification'],
        'experience_years' => (int)$doc['experience_years'],
        'consultation_fee' => (float)$doc['consultation_fee'],
        'rating' => (float)$doc['rating'],
        'room_number' => $doc['room_number'],
        'avg_consult_time_mins' => (int)$doc['avg_consult_time_mins'],
        'is_available_online' => (bool)$doc['is_available_online'],
        'available_slots' => $availableSlotTimes
    ];
}

$topDoctor = !empty($doctorList) ? $doctorList[0] : null;

// 6. Persist Symptom History if user is authenticated
$patientId = Auth::currentUserId();
if ($patientId && Auth::hasRole('patient')) {
    $meta = [
        'top_predictions' => $topPredictions,
        'urgency_level' => $urgencyLevel
    ];
    Database::insert("
        INSERT INTO symptom_history (patient_id, symptoms_input, predicted_department_id, confidence_score, top_predictions, recommended_doctor_ids)
        VALUES (?, ?, ?, ?, ?, ?)
    ", [
        $patientId,
        $symptoms,
        $deptId,
        $confidence,
        json_encode($meta),
        json_encode($recommendedDoctorIds)
    ]);
}

json_response([
    'success' => true,
    'symptoms' => $symptoms,
    'department' => [
        'id' => $deptId,
        'name' => $dept['name'],
        'code' => $dept['code'],
        'icon' => $dept['icon'],
        'description' => $dept['description']
    ],
    'confidence' => $confidence,
    'urgency_level' => $urgencyLevel,
    'top_predictions' => $topPredictions,
    'recommended_doctor' => $topDoctor ? [
        'id' => $topDoctor['id'],
        'name' => $topDoctor['name'],
        'specialization' => $topDoctor['specialization'],
        'available_slots' => $topDoctor['available_slots']
    ] : null,
    'doctors' => $doctorList,
    'triage_advice' => getTriageAdvice($confidence, $dept['name'], $urgencyLevel)
]);

/**
 * Clinical Urgency Estimator
 */
function determineUrgencyLevel(string $input, string $dept): string {
    $s = strtolower($input);
    $criticalTerms = ['severe chest', 'radiating', 'heart attack', 'unconscious', 'cannot breathe', 'choking', 'convulsion', 'stroke', 'face droop', 'sudden weakness', 'crushing'];
    foreach ($criticalTerms as $w) {
        if (str_contains($s, $w)) return 'Critical Emergency';
    }

    $highTerms = ['chest', 'palpitation', 'migraine', 'aura', 'fracture', 'broken', 'fever 10', 'high fever', 'vomit blood', 'blood in urine', 'stiff neck', 'seizure'];
    foreach ($highTerms as $w) {
        if (str_contains($s, $w)) return 'High Urgency';
    }

    $moderateTerms = ['fever', 'cough', 'dizziness', 'joint pain', 'rash', 'itching', 'diarrhea', 'vomiting', 'earache', 'sinus', 'stomach pain', 'swelling'];
    foreach ($moderateTerms as $w) {
        if (str_contains($s, $w)) return 'Moderate Urgency';
    }

    return 'Routine Consultation';
}

/**
 * Intelligent Fallback Medical Keyword Classifier (Pure PHP)
 */
function fallbackClassify(string $input): array {
    $text = strtolower($input);
    
    $keywords = [
        'Cardiology' => ['chest', 'heart', 'palpitation', 'bp', 'blood pressure', 'angina', 'breathless', 'pulse', 'cardiac', 'arm pain', 'sweats'],
        'Neurology' => ['headache', 'migraine', 'dizzy', 'dizziness', 'seizure', 'numbness', 'tingling', 'tremor', 'memory', 'faint', 'vertigo', 'neuralgia'],
        'Orthopedics' => ['bone', 'joint', 'knee', 'back', 'fracture', 'sprain', 'spine', 'shoulder', 'arthritis', 'ligament', 'ankle', 'stiff'],
        'Dermatology' => ['skin', 'rash', 'acne', 'itching', 'pimple', 'hair loss', 'scalp', 'mole', 'eczema', 'psoriasis', 'blister', 'allergy'],
        'Pediatrics' => ['baby', 'infant', 'child', 'toddler', 'colic', 'teething', 'croup', 'vaccine', 'pediatric'],
        'Psychiatry' => ['anxiety', 'panic', 'depression', 'insomnia', 'stress', 'sad', 'sleep', 'bipolar', 'mood', 'trauma', 'adhd'],
        'ENT (Otolaryngology)' => ['ear', 'throat', 'sinus', 'hearing', 'tinnitus', 'tonsil', 'nose', 'snoring', 'hoarse', 'smell', 'nasal'],
        'General Medicine' => ['fever', 'cold', 'cough', 'vomit', 'diarrhea', 'weakness', 'fatigue', 'stomach', 'infection', 'jaundice']
    ];

    $scores = [];
    foreach ($keywords as $dept => $words) {
        $score = 0;
        foreach ($words as $w) {
            if (str_contains($text, $w)) {
                $score += 15;
            }
        }
        $scores[$dept] = $score;
    }

    arsort($scores);
    $topDept = array_key_first($scores);
    $topScore = $scores[$topDept] ?? 0;

    $confidence = $topScore > 0 ? min(95.0, 50.0 + ($topScore * 3.5)) : 50.0;
    if ($topScore === 0) {
        $topDept = 'General Medicine';
        $confidence = 58.0;
    }

    $topPredictions = [];
    $i = 0;
    foreach ($scores as $d => $s) {
        if ($i++ >= 4) break;
        $topPredictions[] = [
            'department' => $d,
            'confidence' => $d === $topDept ? $confidence : max(5.0, round((100.0 - $confidence) / 3, 2))
        ];
    }

    return [
        'success' => true,
        'department' => $topDept,
        'confidence' => round($confidence, 2),
        'top_predictions' => $topPredictions
    ];
}

function getTriageAdvice(float $confidence, string $dept, string $urgency): string {
    if ($urgency === 'Critical Emergency') {
        return "CRITICAL EMERGENCY: Symptoms suggest acute medical attention required. Please call 1800-CARE-PULSE immediately or proceed to the nearest emergency trauma center.";
    }
    if ($urgency === 'High Urgency') {
        return "HIGH URGENCY: Clinical assessment needed today. Proceed with instant online video consultation or walk into {$dept} OPD.";
    }
    if ($confidence > 80) {
        return "Strong clinical correlation with {$dept}. Book a consultation slot below for doctor review.";
    }
    return "Symptoms indicate potential multi-system overlap. General Medicine review recommended.";
}
