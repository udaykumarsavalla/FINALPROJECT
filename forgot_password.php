<?php
/**
 * CarePulse AI - Password Reset via Email OTP
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Reset Forgotten Password";
$activeMenu = "login";

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <div class="glass-card p-4 p-sm-5 shadow-lg border-0">
                
                <!-- STEP 1: Enter Email -->
                <div id="forgotStep1">
                    <div class="text-center mb-4">
                        <div class="brand-logo-icon mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.6rem;">
                            <i class="bi bi-key-fill"></i>
                        </div>
                        <h3 class="fw-bold mb-1">Reset Password</h3>
                        <p class="text-secondary small">Enter your registered email to receive a recovery OTP</p>
                    </div>

                    <div id="forgotAlert1" class="alert alert-danger d-none py-2 px-3 small" role="alert"></div>

                    <form id="forgotForm1">
                        <div class="mb-4">
                            <label class="form-label small fw-semibold" for="resetEmail">Registered Email Address</label>
                            <input type="email" class="form-control py-2" id="resetEmail" placeholder="patient@carepulse.ai" required autofocus>
                        </div>

                        <button type="submit" id="sendOtpBtn" class="btn btn-teal w-100 py-2 fw-bold text-white rounded-pill shadow-sm">
                            <i class="bi bi-send me-1"></i> Send Password Reset OTP
                        </button>
                    </form>
                </div>

                <!-- STEP 2: Enter OTP and New Password -->
                <div id="forgotStep2" class="d-none">
                    <div class="text-center mb-4">
                        <div class="brand-logo-icon mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.6rem; background: linear-gradient(135deg, #10b981, #0d9488);">
                            <i class="bi bi-lock-fill"></i>
                        </div>
                        <h3 class="fw-bold mb-1">Set New Password</h3>
                        <p class="text-secondary small">Enter the 6-digit OTP code and your new password</p>
                    </div>

                    <div id="devOtpBoxReset" class="p-3 mb-3 bg-teal-subtle text-teal border border-teal-subtle rounded-3 text-center d-none">
                        <small class="d-block fw-bold mb-1">Reset OTP Code:</small>
                        <span id="devOtpValueReset" class="fs-4 fw-extrabold letter-spacing-lg"></span>
                    </div>

                    <div id="forgotAlert2" class="alert alert-danger d-none py-2 px-3 small" role="alert"></div>

                    <form id="forgotForm2">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="resetOtp">6-Digit Passcode</label>
                            <input type="text" class="form-control text-center fs-3 fw-bold letter-spacing-lg" id="resetOtp" maxlength="6" placeholder="000000" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-semibold" for="newPassword">New Password (min. 8 characters)</label>
                            <input type="password" class="form-control py-2" id="newPassword" placeholder="••••••••" minlength="8" required>
                        </div>

                        <button type="submit" id="resetSubmitBtn" class="btn btn-teal w-100 py-2 fw-bold text-white rounded-pill shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Update Password & Sign In
                        </button>
                    </form>
                </div>

                <div class="text-center mt-4">
                    <a href="<?= base_url('login.php') ?>" class="small text-secondary text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Return to Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let targetEmail = '';

document.getElementById('forgotForm1').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertBox = document.getElementById('forgotAlert1');
    const btn = document.getElementById('sendOtpBtn');
    alertBox.classList.add('d-none');

    targetEmail = document.getElementById('resetEmail').value.trim();
    CarePulse.setButtonLoading(btn, true);

    const res = await CarePulse.api('api/auth.php', {
        method: 'POST',
        body: {
            action: 'forgot_password',
            email: targetEmail,
            csrf_token: window.APP_CONFIG.csrfToken
        }
    });

    CarePulse.setButtonLoading(btn, false);

    if (res.success) {
        CarePulse.showToast(res.message, 'success');
        if (res.otp) {
            document.getElementById('devOtpValueReset').textContent = res.otp;
            document.getElementById('devOtpBoxReset').classList.remove('d-none');
            document.getElementById('resetOtp').value = res.otp;
        }
        document.getElementById('forgotStep1').classList.add('d-none');
        document.getElementById('forgotStep2').classList.remove('d-none');
    } else {
        alertBox.textContent = res.error || 'Failed to dispatch reset code.';
        alertBox.classList.remove('d-none');
    }
});

document.getElementById('forgotForm2').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertBox = document.getElementById('forgotAlert2');
    const btn = document.getElementById('resetSubmitBtn');
    alertBox.classList.add('d-none');

    const otp = document.getElementById('resetOtp').value.trim();
    const newPass = document.getElementById('newPassword').value;

    CarePulse.setButtonLoading(btn, true);

    const res = await CarePulse.api('api/auth.php', {
        method: 'POST',
        body: {
            action: 'reset_password',
            email: targetEmail,
            otp: otp,
            new_password: newPass,
            csrf_token: window.APP_CONFIG.csrfToken
        }
    });

    CarePulse.setButtonLoading(btn, false);

    if (res.success) {
        CarePulse.showToast('Password reset successfully! Redirecting to sign in...', 'success');
        setTimeout(() => {
            window.location.href = window.APP_CONFIG.baseUrl + '/login.php';
        }, 1200);
    } else {
        alertBox.textContent = res.error || 'Failed to update password.';
        alertBox.classList.remove('d-none');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
