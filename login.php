<?php
/**
 * CarePulse AI - Secure Authentication Login Page
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to respective dashboard
if (Auth::isLoggedIn()) {
    header("Location: " . base_url(Auth::getDashboardUrl()));
    exit;
}

$pageTitle = "Secure Sign In";
$activeMenu = "login";

$quickRole = $_GET['quick'] ?? '';
$defaultEmail = '';
$defaultPassword = '';
if ($quickRole === 'admin') {
    $defaultEmail = 'admin@carepulse.ai';
    $defaultPassword = 'Password@123';
} elseif ($quickRole === 'doctor') {
    $defaultEmail = 'doctor.sharma@carepulse.ai';
    $defaultPassword = 'Password@123';
} elseif ($quickRole === 'patient') {
    $defaultEmail = 'patient@carepulse.ai';
    $defaultPassword = 'Password@123';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <div class="glass-card p-4 p-sm-5 shadow-lg border-0">
                <div class="text-center mb-4">
                    <div class="brand-logo-icon mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.6rem;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Welcome Back</h3>
                    <p class="text-secondary small">Sign in to your CarePulse AI healthcare portal</p>
                </div>

                <!-- Alert container -->
                <div id="loginAlert" class="alert alert-danger d-none py-2 px-3 small" role="alert"></div>

                <form id="loginForm" method="POST" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="loginEmail">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-body border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                            <input type="email" class="form-control border-start-0" id="loginEmail" name="email" value="<?= e($defaultEmail) ?>" placeholder="name@carepulse.ai" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <label class="form-label small fw-semibold" for="loginPassword">Password</label>
                            <a href="<?= base_url('forgot_password.php') ?>" class="text-decoration-none text-teal small">Forgot password?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-body border-end-0"><i class="bi bi-lock text-muted"></i></span>
                            <input type="password" class="form-control border-start-0" id="loginPassword" name="password" value="<?= e($defaultPassword) ?>" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe" checked>
                        <label class="form-check-label small text-secondary" for="rememberMe">Remember this browser session</label>
                    </div>

                    <button type="submit" id="loginSubmitBtn" class="btn btn-teal w-100 py-2 fw-bold text-white rounded-pill shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                    </button>
                </form>

                <!-- Quick-Fill Demo Logins for Easy Verification -->
                <div class="mt-4 pt-3 border-top">
                    <span class="d-block text-center text-secondary fs-xs fw-bold text-uppercase mb-2">1-Click Demo Accounts</span>
                    <div class="row g-2">
                        <div class="col-4">
                            <button type="button" class="btn btn-sm btn-outline-dark w-100 rounded-pill fs-xs" onclick="fillAndLogin('admin@carepulse.ai', 'Password@123')">
                                <i class="bi bi-shield-check"></i> Admin
                            </button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-sm btn-outline-teal w-100 rounded-pill fs-xs" onclick="fillAndLogin('doctor.sharma@carepulse.ai', 'Password@123')">
                                <i class="bi bi-heart-pulse"></i> Doctor
                            </button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100 rounded-pill fs-xs" onclick="fillAndLogin('patient@carepulse.ai', 'Password@123')">
                                <i class="bi bi-person"></i> Patient
                            </button>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <p class="small text-secondary mb-0">Don't have an account yet? <a href="<?= base_url('register.php') ?>" class="text-teal fw-bold text-decoration-none">Create Patient Account</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillAndLogin(email, pass) {
    document.getElementById('loginEmail').value = email;
    document.getElementById('loginPassword').value = pass;
    document.getElementById('loginForm').dispatchEvent(new Event('submit'));
}

document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertBox = document.getElementById('loginAlert');
    const submitBtn = document.getElementById('loginSubmitBtn');
    alertBox.classList.add('d-none');

    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;

    CarePulse.setButtonLoading(submitBtn, true);

    const res = await CarePulse.api('api/auth.php', {
        method: 'POST',
        body: {
            action: 'login',
            email: email,
            password: password,
            csrf_token: window.APP_CONFIG.csrfToken
        }
    });

    CarePulse.setButtonLoading(submitBtn, false);

    if (res.success) {
        CarePulse.showToast('Login successful! Redirecting...', 'success');
        window.location.href = res.redirect || window.APP_CONFIG.baseUrl;
    } else {
        alertBox.textContent = res.error || 'Authentication failed.';
        alertBox.classList.remove('d-none');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
