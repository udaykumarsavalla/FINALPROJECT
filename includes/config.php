<?php
/**
 * CarePulse AI - Intelligent Telehealth & Smart Hospital Platform
 * Global Configuration File
 */

// Start session with hardened security settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Application Constants
define('APP_NAME', 'CarePulse AI');
define('APP_TAGLINE', 'Intelligent Telehealth & Smart Hospital Platform');
define('APP_VERSION', '2.0.0');

// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3307);
define('DB_NAME', 'carepulse_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base Paths and URLs
define('BASE_DIR', realpath(__DIR__ . '/..'));
define('UPLOAD_DIR', BASE_DIR . '/uploads');
define('PYTHON_PATH', 'python'); // Python executable on system

// Determine Base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = preg_replace('#/(patient|doctor|admin|api|payments|assets|includes|ai|database).*#i', '', $scriptDir);
define('APP_URL', rtrim($protocol . $host . $basePath, '/'));

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Helper Functions
function base_url($path = '') {
    $cleanPath = ltrim($path, '/');
    return APP_URL . ($cleanPath ? '/' . $cleanPath : '');
}

function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

// Enable error reporting in development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide raw errors from public output for security
ini_set('log_errors', 1);
ini_set('error_log', BASE_DIR . '/error.log');
