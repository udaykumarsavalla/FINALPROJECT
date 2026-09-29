<?php
/**
 * CarePulse AI - Secure Payment Checkout Gateway
 * Supports UPI (QR / VPA), Cards, and Net Banking.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['patient']);

$appointmentId = (int)($_GET['appointment_id'] ?? 0);
$patientId = Auth::currentUserId();

if ($appointmentId <= 0) {
    header("Location: " . base_url('patient/index.php'));
    exit;
}

$apt = Database::fetchOne("
    SELECT a.*, d.consultation_fee, u_doc.name as doctor_name, dep.name as department_name, dep.icon as department_icon
    FROM appointments a
    JOIN doctor_profiles d ON a.doctor_id = d.id
    JOIN users u_doc ON d.user_id = u_doc.id
    JOIN departments dep ON a.department_id = dep.id
    WHERE a.id = ? AND a.patient_id = ?
", [$appointmentId, $patientId]);

if (!$apt) {
    die("Appointment record not found.");
}

// If already confirmed, redirect to receipt or appointments
if ($apt['status'] !== 'pending_payment') {
    $existingPay = Database::fetchOne("SELECT id FROM payments WHERE appointment_id = ? ORDER BY id DESC LIMIT 1", [$appointmentId]);
    if ($existingPay) {
        header("Location: " . base_url('payments/receipt.php?id=' . $existingPay['id']));
        exit;
    }
}

$pageTitle = "Secure Consultation Checkout";
$activeMenu = "book";

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Header -->
            <div class="text-center mb-4">
                <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-2">
                    <i class="bi bi-shield-lock-fill me-1"></i> 256-Bit SSL Encrypted Healthcare Checkout
                </span>
                <h2 class="display-6 fw-bold mb-1">Confirm & Pay Consultation Fee</h2>
                <p class="text-secondary small">Your appointment token is held. Complete payment to confirm your consultation slot.</p>
            </div>

            <div class="row g-4">
                <!-- Left: Appointment Order Summary -->
                <div class="col-md-5">
                    <div class="glass-card p-4 h-100">
                        <h5 class="fw-bold mb-3"><i class="bi bi-receipt me-2 text-teal"></i> Booking Summary</h5>

                        <div class="p-3 rounded-3 bg-body-tertiary border mb-3">
                            <span class="fs-xs text-secondary d-block">Doctor</span>
                            <strong class="text-body d-block"><?= e($apt['doctor_name']) ?></strong>
                            <small class="text-teal fw-semibold"><?= e($apt['department_name']) ?></small>
                        </div>

                        <div class="space-y-2 text-secondary small mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Appointment No:</span>
                                <strong class="text-body"><?= e($apt['appointment_number']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Date & Time:</span>
                                <strong class="text-body"><?= date('M d, Y', strtotime($apt['appointment_date'])) ?> <?= date('h:i A', strtotime($apt['appointment_time'])) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Consultation Mode:</span>
                                <strong class="text-body"><?= ucwords(str_replace('_', ' ', $apt['appointment_type'])) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Queue Token:</span>
                                <strong class="text-teal">#<?= $apt['queue_number'] ?></strong>
                            </div>
                        </div>

                        <div class="p-3 rounded-3 bg-teal-subtle text-teal border border-teal-subtle mt-auto">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Total Amount Due:</span>
                                <span class="fs-4 fw-extrabold">₹<?= number_format($apt['consultation_fee'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Payment Method Selector & Gateway Inputs -->
                <div class="col-md-7">
                    <div class="glass-card p-4 shadow-lg">
                        <h5 class="fw-bold mb-3"><i class="bi bi-wallet2 me-2 text-teal"></i> Select Payment Method</h5>

                        <!-- Method Tabs -->
                        <ul class="nav nav-pills nav-fill mb-4 p-1 bg-body-tertiary rounded-pill border" id="paymentTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active rounded-pill fw-semibold py-2" id="upi-tab" data-bs-toggle="pill" data-bs-target="#upiContent" type="button">
                                    <i class="bi bi-qr-code me-1"></i> UPI / QR
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link rounded-pill fw-semibold py-2" id="card-tab" data-bs-toggle="pill" data-bs-target="#cardContent" type="button">
                                    <i class="bi bi-credit-card me-1"></i> Card
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link rounded-pill fw-semibold py-2" id="nb-tab" data-bs-toggle="pill" data-bs-target="#nbContent" type="button">
                                    <i class="bi bi-bank me-1"></i> Net Banking
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="paymentTabsContent">
                            <!-- 1. UPI Tab -->
                            <div class="tab-pane fade show active" id="upiContent" role="tabpanel">
                                <form id="upiForm">
                                    <div class="text-center p-3 bg-body-tertiary rounded-3 border mb-3">
                                        <div class="bg-white p-2 d-inline-block rounded-2 shadow-sm border mb-2">
                                            <!-- Simulated SVG Dynamic QR Code -->
                                            <svg width="120" height="120" viewBox="0 0 100 100">
                                                <rect width="100" height="100" fill="#ffffff"/>
                                                <rect x="10" y="10" width="30" height="30" fill="#0d9488"/>
                                                <rect x="15" y="15" width="20" height="20" fill="#ffffff"/>
                                                <rect x="20" y="20" width="10" height="10" fill="#0d9488"/>
                                                <rect x="60" y="10" width="30" height="30" fill="#0d9488"/>
                                                <rect x="65" y="15" width="20" height="20" fill="#ffffff"/>
                                                <rect x="70" y="20" width="10" height="10" fill="#0d9488"/>
                                                <rect x="10" y="60" width="30" height="30" fill="#0d9488"/>
                                                <rect x="15" y="65" width="20" height="20" fill="#ffffff"/>
                                                <rect x="20" y="70" width="10" height="10" fill="#0d9488"/>
                                                <rect x="50" y="50" width="10" height="10" fill="#0f172a"/>
                                                <rect x="65" y="65" width="15" height="15" fill="#0f172a"/>
                                                <rect x="80" y="80" width="10" height="10" fill="#0f172a"/>
                                            </svg>
                                        </div>
                                        <span class="d-block text-secondary fs-xs">Scan with Google Pay, PhonePe, Paytm, or BHIM</span>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label small fw-semibold" for="upiId">Or Enter UPI ID (VPA)</label>
                                        <input type="text" class="form-control" id="upiId" value="patient@okhdfcbank" placeholder="username@okhdfcbank" required>
                                    </div>

                                    <button type="submit" class="btn btn-teal text-white w-100 py-2 fw-bold rounded-pill shadow-sm pay-submit-btn">
                                        Pay ₹<?= number_format($apt['consultation_fee'], 2) ?> via UPI
                                    </button>
                                </form>
                            </div>

                            <!-- 2. Card Tab -->
                            <div class="tab-pane fade" id="cardContent" role="tabpanel">
                                <form id="cardForm">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold" for="cardNum">Card Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-body"><i class="bi bi-credit-card"></i></span>
                                            <input type="text" class="form-control" id="cardNum" value="4532 8923 7162 4242" placeholder="4532 •••• •••• ••••" maxlength="19" required>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold" for="cardExp">Expiry Date (MM/YY)</label>
                                            <input type="text" class="form-control" id="cardExp" value="12/28" placeholder="MM/YY" maxlength="5" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold" for="cardCvv">CVV / CVC</label>
                                            <input type="password" class="form-control" id="cardCvv" value="888" placeholder="•••" maxlength="4" required>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label small fw-semibold" for="cardName">Cardholder Name</label>
                                        <input type="text" class="form-control" id="cardName" value="<?= e($currentUser['name']) ?>" required>
                                    </div>

                                    <button type="submit" class="btn btn-teal text-white w-100 py-2 fw-bold rounded-pill shadow-sm pay-submit-btn">
                                        Pay ₹<?= number_format($apt['consultation_fee'], 2) ?> via Card
                                    </button>
                                </form>
                            </div>

                            <!-- 3. Net Banking Tab -->
                            <div class="tab-pane fade" id="nbContent" role="tabpanel">
                                <form id="nbForm">
                                    <div class="mb-4">
                                        <label class="form-label small fw-semibold" for="bankSelect">Select Bank</label>
                                        <select class="form-select py-2" id="bankSelect" required>
                                            <option value="HDFC Bank" selected>HDFC Bank</option>
                                            <option value="State Bank of India">State Bank of India (SBI)</option>
                                            <option value="ICICI Bank">ICICI Bank</option>
                                            <option value="Axis Bank">Axis Bank</option>
                                            <option value="Kotak Mahindra Bank">Kotak Mahindra Bank</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-teal text-white w-100 py-2 fw-bold rounded-pill shadow-sm pay-submit-btn">
                                        Proceed to Net Banking Gateway
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const appointmentId = <?= $appointmentId ?>;

async function processPayment(method, extraData = {}) {
    const activeBtn = document.querySelector('.tab-pane.active .pay-submit-btn');
    CarePulse.setButtonLoading(activeBtn, true);

    const payload = {
        appointment_id: appointmentId,
        payment_method: method,
        ...extraData,
        csrf_token: window.APP_CONFIG.csrfToken
    };

    const res = await CarePulse.api('api/payments.php', {
        method: 'POST',
        body: payload
    });

    CarePulse.setButtonLoading(activeBtn, false);

    if (res.success) {
        CarePulse.showToast('Payment successful! Generating receipt...', 'success');
        setTimeout(() => {
            window.location.href = res.receipt_url;
        }, 1200);
    } else {
        CarePulse.showToast(res.error || 'Payment failed.', 'danger');
    }
}

document.getElementById('upiForm').addEventListener('submit', function(e) {
    e.preventDefault();
    processPayment('upi', { upi_id: document.getElementById('upiId').value.trim() });
});

document.getElementById('cardForm').addEventListener('submit', function(e) {
    e.preventDefault();
    processPayment('card', {
        card_number: document.getElementById('cardNum').value.trim(),
        card_expiry: document.getElementById('cardExp').value.trim(),
        card_cvv: document.getElementById('cardCvv').value.trim()
    });
});

document.getElementById('nbForm').addEventListener('submit', function(e) {
    e.preventDefault();
    processPayment('netbanking', { bank_name: document.getElementById('bankSelect').value });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
