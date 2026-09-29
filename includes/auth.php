<?php
/**
 * CarePulse AI - Authentication & Role-Based Access Control (RBAC)
 * Handles BCRYPT password hashing, Email OTP generation/verification, and session management.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

class Auth {

    public static function isLoggedIn(): bool {
        return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
    }

    public static function currentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function currentUserRole(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    public static function currentUser(): ?array {
        if (!self::isLoggedIn()) return null;
        $userId = self::currentUserId();
        return Database::fetchOne("SELECT id, name, email, role, phone, gender, dob, blood_group, is_verified, avatar FROM users WHERE id = ?", [$userId]);
    }

    public static function hasRole(string $role): bool {
        return self::currentUserRole() === $role;
    }

    /**
     * Enforce access control for protected views and endpoints
     */
    public static function requireAuth(array $allowedRoles = []): void {
        if (!self::isLoggedIn()) {
            if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                json_response(['success' => false, 'error' => 'Authentication required.', 'redirect' => base_url('login.php')], 401);
            }
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header("Location: " . base_url('login.php'));
            exit;
        }

        if (!empty($allowedRoles) && !in_array(self::currentUserRole(), $allowedRoles)) {
            if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                json_response(['success' => false, 'error' => 'Access denied: insufficient privileges.'], 403);
            }
            header("Location: " . base_url(self::getDashboardUrl(self::currentUserRole())));
            exit;
        }
    }

    public static function getDashboardUrl(?string $role = null): string {
        $r = $role ?? self::currentUserRole();
        return match ($r) {
            'admin' => 'admin/index.php',
            'doctor' => 'doctor/index.php',
            default => 'patient/index.php',
        };
    }

    /**
     * Authenticate user with BCRYPT verification
     */
    public static function login(string $email, string $password, ?string $requiredRole = null): array {
        if (!Security::checkRateLimit('login_' . $email, 6, 300)) {
            return ['success' => false, 'error' => 'Too many failed login attempts. Please wait 5 minutes.'];
        }

        $user = Database::fetchOne("SELECT * FROM users WHERE email = ?", [strtolower(trim($email))]);
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'error' => 'Your account is suspended or inactive. Please contact administration.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        if ($requiredRole !== null && $user['role'] !== $requiredRole) {
            return ['success' => false, 'error' => "Account is not authorized for the {$requiredRole} portal."];
        }

        Security::clearRateLimit('login_' . $email);

        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'];

        // If doctor, also store doctor_profile id
        if ($user['role'] === 'doctor') {
            $doc = Database::fetchOne("SELECT id FROM doctor_profiles WHERE user_id = ?", [$user['id']]);
            if ($doc) {
                $_SESSION['doctor_profile_id'] = $doc['id'];
            }
        }

        return [
            'success' => true,
            'message' => 'Login successful.',
            'role' => $user['role'],
            'redirect' => self::getDashboardUrl($user['role'])
        ];
    }

    /**
     * Generate and store 6-digit OTP with 10-minute expiry
     */
    public static function generateOtp(string $email): string {
        $otp = (string)random_int(100000, 999999);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        Database::execute("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?", [
            $otp, $expiry, strtolower(trim($email))
        ]);

        return $otp;
    }

    /**
     * Register a new patient account with unverified OTP status
     */
    public static function register(array $data): array {
        $email = strtolower(trim($data['email'] ?? ''));
        $name = trim($data['name'] ?? '');
        $password = $data['password'] ?? '';
        $phone = trim($data['phone'] ?? '');
        $gender = $data['gender'] ?? 'other';
        $dob = !empty($data['dob']) ? $data['dob'] : null;
        $bloodGroup = trim($data['blood_group'] ?? 'O+');

        if (empty($name) || empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Name, email, and password are required.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please provide a valid email address.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters long.'];
        }

        // Check if user already exists
        $existing = Database::fetchOne("SELECT id, is_verified FROM users WHERE email = ?", [$email]);
        if ($existing) {
            if ($existing['is_verified']) {
                return ['success' => false, 'error' => 'An account with this email address already exists. Please login.'];
            } else {
                // Resend OTP for pending verification
                $otp = self::generateOtp($email);
                return [
                    'success' => true,
                    'is_existing_unverified' => true,
                    'otp' => $otp,
                    'message' => 'Account created previously but unverified. A new OTP has been dispatched to your email.'
                ];
            }
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $otp = (string)random_int(100000, 999999);
        $otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $userId = Database::insert(
            "INSERT INTO users (name, email, password_hash, role, phone, gender, dob, blood_group, otp_code, otp_expiry, is_verified, status) 
             VALUES (?, ?, ?, 'patient', ?, ?, ?, ?, ?, ?, 0, 'active')",
            [$name, $email, $passwordHash, $phone, $gender, $dob, $bloodGroup, $otp, $otpExpiry]
        );

        return [
            'success' => true,
            'user_id' => $userId,
            'email' => $email,
            'otp' => $otp, // Available for development/testing simulator & email dispatch
            'message' => 'Registration successful! An OTP code has been dispatched to your email address.'
        ];
    }

    /**
     * Verify email with OTP
     */
    public static function verifyOtp(string $email, string $otp): array {
        $user = Database::fetchOne("SELECT id, otp_code, otp_expiry, is_verified FROM users WHERE email = ?", [strtolower(trim($email))]);
        if (!$user) {
            return ['success' => false, 'error' => 'User account not found.'];
        }

        if (empty($user['otp_code']) || $user['otp_code'] !== trim($otp)) {
            return ['success' => false, 'error' => 'Invalid OTP code. Please check your email or request a new code.'];
        }

        if (strtotime($user['otp_expiry']) < time()) {
            return ['success' => false, 'error' => 'OTP has expired. Please request a new verification code.'];
        }

        // Mark verified and clear OTP
        Database::execute("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE id = ?", [$user['id']]);

        return ['success' => true, 'message' => 'Email verified successfully! You may now sign in.'];
    }

    /**
     * Request password reset OTP
     */
    public static function forgotPassword(string $email): array {
        $email = strtolower(trim($email));
        $user = Database::fetchOne("SELECT id, name FROM users WHERE email = ?", [$email]);
        if (!$user) {
            // For security, don't reveal non-existence
            return ['success' => true, 'message' => 'If an account exists with this email, an OTP has been sent.'];
        }

        $otp = self::generateOtp($email);

        return [
            'success' => true,
            'otp' => $otp,
            'message' => 'Password reset OTP has been sent to your email.'
        ];
    }

    /**
     * Reset password using verified OTP
     */
    public static function resetPassword(string $email, string $otp, string $newPassword): array {
        $email = strtolower(trim($email));
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'New password must be at least 8 characters long.'];
        }

        $user = Database::fetchOne("SELECT id, otp_code, otp_expiry FROM users WHERE email = ?", [$email]);
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid request.'];
        }

        if (empty($user['otp_code']) || $user['otp_code'] !== trim($otp)) {
            return ['success' => false, 'error' => 'Invalid or incorrect OTP code.'];
        }

        if (strtotime($user['otp_expiry']) < time()) {
            return ['success' => false, 'error' => 'OTP has expired. Please request a new one.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
        Database::execute("UPDATE users SET password_hash = ?, otp_code = NULL, otp_expiry = NULL WHERE id = ?", [$newHash, $user['id']]);

        return ['success' => true, 'message' => 'Password reset successfully! You can now log in with your new password.'];
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
