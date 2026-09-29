<?php
/**
 * CarePulse AI - Authentication API Endpoint
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email_service.php';

// Accept JSON payload or standard POST
$rawData = file_get_contents('php://input');
$json = json_decode($rawData, true) ?: [];
$params = array_merge($_POST, $json);

$action = $params['action'] ?? $_GET['action'] ?? '';

// Verify CSRF for mutating actions (except initial registration/login where token is passed)
if (in_array($action, ['login', 'register', 'verify_otp', 'forgot_password', 'reset_password'])) {
    $token = $params['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!Security::validateCsrfToken($token)) {
        json_response(['success' => false, 'error' => 'Invalid or missing CSRF security token.'], 403);
    }
}

switch ($action) {
    case 'login':
        $email = $params['email'] ?? '';
        $password = $params['password'] ?? '';
        $role = $params['role'] ?? null; // Optional: specify portal

        if (empty($email) || empty($password)) {
            json_response(['success' => false, 'error' => 'Please provide both email and password.'], 400);
        }

        $result = Auth::login($email, $password, $role);
        json_response($result, $result['success'] ? 200 : 401);
        break;

    case 'register':
        $result = Auth::register($params);
        if ($result['success'] && !empty($result['otp'])) {
            // Dispatch OTP Email
            NotificationService::sendOtpEmail($result['email'], $params['name'] ?? 'User', $result['otp'], 'Patient Account Registration');
        }
        json_response($result, $result['success'] ? 200 : 400);
        break;

    case 'verify_otp':
        $email = $params['email'] ?? '';
        $otp = $params['otp'] ?? '';
        if (empty($email) || empty($otp)) {
            json_response(['success' => false, 'error' => 'Email and OTP code are required.'], 400);
        }

        $result = Auth::verifyOtp($email, $otp);
        json_response($result, $result['success'] ? 200 : 400);
        break;

    case 'resend_otp':
        $email = strtolower(trim($params['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'error' => 'Valid email is required.'], 400);
        }
        $user = Database::fetchOne("SELECT name FROM users WHERE email = ?", [$email]);
        if ($user) {
            $otp = Auth::generateOtp($email);
            NotificationService::sendOtpEmail($email, $user['name'], $otp, 'Verification Code Resend');
            json_response(['success' => true, 'message' => 'A fresh OTP code has been dispatched to your email.', 'otp' => $otp]);
        }
        json_response(['success' => true, 'message' => 'If an account exists, a code was sent.']);
        break;

    case 'forgot_password':
        $email = $params['email'] ?? '';
        $result = Auth::forgotPassword($email);
        if ($result['success'] && !empty($result['otp'])) {
            NotificationService::sendOtpEmail($email, 'Patient', $result['otp'], 'Password Reset');
        }
        json_response($result);
        break;

    case 'reset_password':
        $email = $params['email'] ?? '';
        $otp = $params['otp'] ?? '';
        $newPassword = $params['new_password'] ?? '';
        $result = Auth::resetPassword($email, $otp, $newPassword);
        json_response($result, $result['success'] ? 200 : 400);
        break;

    case 'logout':
        Auth::logout();
        json_response(['success' => true, 'redirect' => base_url('login.php')]);
        break;

    default:
        json_response(['success' => false, 'error' => 'Invalid authentication action.'], 400);
}
