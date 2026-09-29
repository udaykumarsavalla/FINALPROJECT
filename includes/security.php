<?php
/**
 * CarePulse AI - Security & Sanitization Layer
 * Implements CSRF tokens, MIME validation, random filename generation, and XSS filtering.
 */

require_once __DIR__ . '/config.php';

class Security {

    /**
     * Generate or return existing CSRF token
     */
    public static function getCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token from POST body or X-CSRF-Token request header
     */
    public static function validateCsrfToken(?string $token = null): bool {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Assert CSRF token or abort with 403 Forbidden
     */
    public static function requireCsrf(): void {
        if (!self::validateCsrfToken()) {
            json_response([
                'success' => false,
                'error' => 'Invalid or expired CSRF token. Please refresh the page and try again.'
            ], 403);
        }
    }

    /**
     * Validate and securely save uploaded file
     * Allowed types: PDF, JPG, PNG
     * Verifies actual MIME type using finfo, not just file extension
     */
    public static function handleFileUpload(array $file, string $subfolder = 'medical_records', int $maxSizeBytes = 10485760): array {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Invalid file upload parameters.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'error' => 'No file was uploaded.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'error' => 'Uploaded file exceeds the maximum allowed size (10 MB).'];
            default:
                return ['success' => false, 'error' => 'Unknown file upload error.'];
        }

        if ($file['size'] > $maxSizeBytes) {
            return ['success' => false, 'error' => 'File size exceeds limit of 10 MB.'];
        }

        // Validate actual MIME type using finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        $allowedMimes = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp'
        ];

        if (!array_key_exists($mimeType, $allowedMimes)) {
            return [
                'success' => false,
                'error' => 'Invalid file format (' . htmlspecialchars($mimeType) . '). Only PDF, JPG, PNG, and WebP files are permitted.'
            ];
        }

        $extension = $allowedMimes[$mimeType];
        // Generate cryptographically secure random filename
        $randomFileName = bin2hex(random_bytes(16)) . '.' . $extension;

        $targetDir = UPLOAD_DIR . '/' . trim($subfolder, '/');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $randomFileName;
        $relativePath = 'uploads/' . trim($subfolder, '/') . '/' . $randomFileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file to secure storage.'];
        }

        return [
            'success' => true,
            'file_name' => $randomFileName,
            'original_name' => basename($file['name']),
            'file_path' => $relativePath,
            'mime_type' => $mimeType,
            'file_size' => $file['size']
        ];
    }

    /**
     * Prevent brute-force with a lightweight session/IP rate limiter
     */
    public static function checkRateLimit(string $actionKey, int $maxAttempts = 5, int $decaySeconds = 300): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'rate_' . md5($actionKey . '_' . $ip);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
            return true;
        }

        $elapsed = time() - $_SESSION[$key]['first_attempt'];
        if ($elapsed > $decaySeconds) {
            $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
            return true;
        }

        $_SESSION[$key]['count']++;
        return $_SESSION[$key]['count'] <= $maxAttempts;
    }

    /**
     * Clear rate limit on successful action
     */
    public static function clearRateLimit(string $actionKey): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'rate_' . md5($actionKey . '_' . $ip);
        unset($_SESSION[$key]);
    }
}

// Global short helper for safe HTML escaping
function e($string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}
