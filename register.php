<?php
/**
 * CarePulse AI - Patient Registration with Email OTP Verification
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (Auth::isLoggedIn()) {
    header("Location: " . base_url(Auth::getDashboardUrl()));
    exit;
}

$pageTitle = "Patient Registration & OTP Verification";
$activeMenu = "register";

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-7">
            <div class="glass-card p-4 p-sm-5 shadow-lg border-0">
                
                <!-- STEP 1: Registration Form -->
                <div id="registrationStep1">
                    <div class="text-center mb-4">
                        <div class="brand-logo-icon mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.6rem;">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <h3 class="fw-bold mb-1">Create Patient Account</h3>
                        <p class="text-secondary small">Join CarePulse AI Telemedicine & Digital Health Records</p>
                    </div>

                    <div id="regAlert" class="alert alert-danger d-none py-2 px-3 small" role="alert"></div>

                    <form id="registerForm">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold" for="regName">Full Legal Name *</label>
                                <input type="text" class="form-control" id="regName" placeholder="e.g. Johnathan Doe" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold" for="regEmail">Email Address *</label>
                                <input type="email" class="form-control" id="regEmail" placeholder="john@example.com" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold" for="regPhone">Mobile Phone *</label>
                                <input type="tel" class="form-control" id="regPhone" placeholder="+91 98765 43210" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold" for="regBlood">Blood Group</label>
                                <select class="form-select" id="regBlood">
                                    <option value="O+">O+ (Positive)</option>
                                    <option value="O-">O- (Negative)</option>
                                    <option value="A+">A+ (Positive)</option>
                                    <option value="A-">A- (Negative)</option>
                                    <option value="B+">B+ (Positive)</option>
                                    <option value="B-">B- (Negative)</option>
                                    <option value="AB+">AB+ (Positive)</option>
                                    <option value="AB-">AB- (Negative)</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold" for="regGender">Gender</label>
                                <select class="form-select" id="regGender">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold" for="regDob">Date of Birth</label>
                                <input type="date" class="form-control" id="regDob" value="1995-01-01">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold" for="regPassword">Create Password (min. 8 characters) *</label>
                                <input type="password" class="form-control" id="regPassword" placeholder="••••••••" required minlength="8">
                            </div>
                        </div>

                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input" id="termsCheck" required checked>
                            <label class="form-check-label small text-secondary" for="termsCheck">
                                I agree to the <a href="#" class="text-teal">Terms of Telehealth Care</a> and HIPAA Consent Policy.
                            </label>
                        </div>

                        <button type="submit" id="regSubmitBtn" class="btn btn-teal w-100 py-2 fw-bold text-white rounded-pill shadow-sm">
                            <i class="bi bi-shield-check me-1"></i> Continue to Email OTP Verification
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="small text-secondary mb-0">Already registered? <a href="<?= base_url('login.php') ?>" class="text-teal fw-bold text-decoration-none">Sign In Here</a></p>
                    </div>
                </div>

                <!-- STEP 2: Email OTP Verification Screen -->
                <div id="registrationStep2" class="d-none">
                    <div class="text-center mb-4">
                        <div class="brand-logo-icon mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.6rem; background: linear-gradient(135deg, #10b981, #0d9488);">
                            <i class="bi bi-envelope-check-fill"></i>
                        </div>
                        <h3 class="fw-bold mb-1">Verify Your Email</h3>
                        <p class="text-secondary small">We have sent a 6-digit verification code to <br><strong id="displayTargetEmail" class="text-body"></strong></p>
                    </div>

                    <!-- Developer Preview Box for Easy Testing -->
                    <div id="devOtpBox" class="p-3 mb-3 bg-teal-subtle text-teal border border-teal-subtle rounded-3 text-center d-none">
                        <small class="d-block fw-bold mb-1">Developer Auto-Detected OTP Code:</small>
                        <span id="devOtpValue" class="fs-4 fw-extrabold letter-spacing-lg"></span>
                    </div>

                    <div id="otpAlert" class="alert alert-danger d-none py-2 px-3 small" role="alert"></div>

                    <form id="otpForm">
                        <div class="mb-4 text-center">
                            <label class="form-label small fw-semibold d-block mb-3" for="otpInput">Enter 6-Digit Passcode</label>
                            <input type="text" class="form-control text-center fs-2 fw-extrabold py-2 letter-spacing-lg mx-auto" id="otpInput" maxlength="6" pattern="[0-9]{6}" placeholder="000000" style="max-width: 260px; letter-spacing: 8px;" required autofocus>
                        </div>

                        <button type="submit" id="otpSubmitBtn" class="btn btn-teal w-100 py-2 fw-bold text-white rounded-pill shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Verify & Activate Account
                        </button>
                    </form>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-sm btn-link text-secondary text-decoration-none" onclick="backToStep1()">
                            <i class="bi bi-arrow-left"></i> Change Email
                        </button>
                        <button type="button" id="resendOtpBtn" class="btn btn-sm btn-outline-teal rounded-pill" onclick="resendOtp()">
                            Resend Code
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
let currentEmail = '';

document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertBox = document.getElementById('regAlert');
    const submitBtn = document.getElementById('regSubmitBtn');
    alertBox.classList.add('d-none');

    currentEmail = document.getElementById('regEmail').value.trim();

    CarePulse.setButtonLoading(submitBtn, true);

    const payload = {
        action: 'register',
        name: document.getElementById('regName').value.trim(),
        email: currentEmail,
        phone: document.getElementById('regPhone').value.trim(),
        blood_group: document.getElementById('regBlood').value,
        gender: document.getElementById('regGender').value,
        dob: document.getElementById('regDob').value,
        password: document.getElementById('regPassword').value,
        csrf_token: window.APP_CONFIG.csrfToken
    };

    const res = await CarePulse.api('api/auth.php', {
        method: 'POST',
        body: payload
    });

    CarePulse.setButtonLoading(submitBtn, false);

    if (res.success) {
        CarePulse.showToast('OTP sent! Please check your email inbox.', 'success');
        document.getElementById('displayTargetEmail').textContent = currentEmail;
        
        // Show dev preview helper if OTP returned in dev response
        if (res.otp) {
            document.getElementById('devOtpValue').textContent = res.otp;
            document.getElementById('devOtpBox').classList.remove('d-none');
            document.getElementById('otpInput').value = res.otp; // Auto-populate for speed!
        }

        document.getElementById('registrationStep1').classList.add('d-none');
        document.getElementById('registrationStep2').classList.remove('d-none');
    } else {
        alertBox.textContent = res.error || 'Registration failed.';
        alertBox.classList.remove('d-none');
    }
});

document.getElementById('otpForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertBox = document.getElementById('otpAlert');
    const submitBtn = document.getElementById('otpSubmitBtn');
    alertBox.classList.add('d-none');

    const otp = document.getElementById('otpInput').value.trim();

    CarePulse.setButtonLoading(submitBtn, true);

    const res = await CarePulse.api('api/auth.php', {
        method: 'POST',
        body: {
            action: 'verify_otp',
            email: currentEmail,
            otp: otp,
            csrf_token: window.APP_CONFIG.csrfToken
        }
    });

    CarePulse.setButtonLoading(submitBtn, false);

    if (res.success) {
        CarePulse.showToast('Email verified successfully! You may now sign in.', 'success');
        setTimeout(() => {
            window.location.href = window.APP_CONFIG.baseUrl + '/login.php';
        }, 1200);
    } else {
        alertBox.textContent = res.error || 'OTP verification failed.';
        alertBox.classList.remove('d-none');
    }
});

async function resendOtp() {
    const btn = document.getElementById('resendOtpBtn');
    CarePulse.setButtonLoading(btn, true);

    const res = await CarePulse.api('api/auth.php', {
        method: 'POST',
        body: {
            action: 'resend_otp',
            email: currentEmail,
            csrf_token: window.APP_CONFIG.csrfToken
        }
    });

    CarePulse.setButtonLoading(btn, false);

    if (res.success) {
        CarePulse.showToast(res.message, 'success');
        if (res.otp) {
            document.getElementById('devOtpValue').textContent = res.otp;
            document.getElementById('otpInput').value = res.otp;
        }
    } else {
        CarePulse.showToast(res.error, 'danger');
    }
}

function backToStep1() {
    document.getElementById('registrationStep2').classList.add('d-none');
    document.getElementById('registrationStep1').classList.remove('d-none');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
