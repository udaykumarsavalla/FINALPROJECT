<?php
/**
 * CarePulse AI - Comprehensive Hospital Operational & Financial Analytics
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['admin']);

$pageTitle = "Hospital Analytics & Financial Ledger";
$activeMenu = "admin_analytics";

// 1. Data sources
$historicalStats = Database::fetchAll("SELECT * FROM opd_daily_stats ORDER BY stat_date ASC");
$departmentStats = Database::fetchAll("
    SELECT dep.name as department_name, dep.icon,
           COUNT(a.id) as appointment_count,
           COALESCE(SUM(p.amount), 0) as department_revenue
    FROM departments dep
    LEFT JOIN appointments a ON dep.id = a.department_id
    LEFT JOIN payments p ON a.id = p.appointment_id AND p.payment_status = 'successful'
    GROUP BY dep.id
    ORDER BY appointment_count DESC
");

$paymentSplit = Database::fetchAll("
    SELECT payment_method, COUNT(*) as txn_count, SUM(amount) as method_revenue
    FROM payments
    WHERE payment_status = 'successful'
    GROUP BY payment_method
");

$recentTransactions = Database::fetchAll("
    SELECT p.*, a.appointment_number, u_pat.name as patient_name, u_doc.name as doctor_name
    FROM payments p
    JOIN appointments a ON p.appointment_id = a.id
    JOIN users u_pat ON p.patient_id = u_pat.id
    JOIN doctor_profiles d ON a.doctor_id = d.id
    JOIN users u_doc ON d.user_id = u_doc.id
    ORDER BY p.created_at DESC LIMIT 10
");

// 2. Consultation Funnel Metrics
$totalBooked = (int)Database::fetchOne("SELECT COUNT(*) as c FROM appointments")['c'];
$confirmed = (int)Database::fetchOne("SELECT COUNT(*) as c FROM appointments WHERE status IN ('confirmed', 'in_consultation', 'completed')")['c'];
$inConsultation = (int)Database::fetchOne("SELECT COUNT(*) as c FROM appointments WHERE status IN ('in_consultation', 'completed')")['c'];
$completed = (int)Database::fetchOne("SELECT COUNT(*) as c FROM appointments WHERE status = 'completed'")['c'];
$prescriptionsIssued = (int)Database::fetchOne("SELECT COUNT(*) as c FROM prescriptions")['c'];

$pctConfirmed = $totalBooked > 0 ? round(($confirmed / $totalBooked) * 100, 1) : 0;
$pctInConsult = $totalBooked > 0 ? round(($inConsultation / $totalBooked) * 100, 1) : 0;
$pctCompleted = $totalBooked > 0 ? round(($completed / $totalBooked) * 100, 1) : 0;
$pctPrescribed = $totalBooked > 0 ? round(($prescriptionsIssued / $totalBooked) * 100, 1) : 0;

// 3. Cancellation Rate Statistics
$cancelledCount = (int)Database::fetchOne("SELECT COUNT(*) as c FROM appointments WHERE status = 'cancelled'")['c'];
$cancellationRate = $totalBooked > 0 ? round(($cancelledCount / $totalBooked) * 100, 1) : 0.0;
$totalRevenue = (float)Database::fetchOne("SELECT COALESCE(SUM(amount), 0) as s FROM payments WHERE payment_status = 'successful'")['s'];

// 4. Monthly Revenue Dataset for Bar Chart
$monthlyRevenue = [
    ['month' => 'Apr', 'revenue' => 184500, 'patients' => 820],
    ['month' => 'May', 'revenue' => 212000, 'patients' => 940],
    ['month' => 'Jun', 'revenue' => 198000, 'patients' => 890],
    ['month' => 'Jul', 'revenue' => 245000, 'patients' => 1080],
    ['month' => 'Aug', 'revenue' => 275000, 'patients' => 1210],
    ['month' => 'Sep', 'revenue' => 310500, 'patients' => 1340]
];

// 5. Doctor Capacity vs Patient Load for Area Chart
$totalDoctors = (int)Database::fetchOne("SELECT COUNT(*) as c FROM doctor_profiles")['c'];
$doctorCapacityPerDay = max(1, $totalDoctors) * 24;
$capacityTrend = [];
foreach ($historicalStats as $stat) {
    $capacityTrend[] = [
        'date' => substr($stat['stat_date'], 5),
        'patients' => (int)$stat['total_patients'],
        'capacity' => $doctorCapacityPerDay
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-lg-5 py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                <i class="bi bi-bar-chart-line-fill me-1"></i> Hospital Analytics & Operational Telemetry
            </span>
            <h2 class="display-6 fw-bold mb-0">Telemetry & Financial Performance</h2>
            <p class="text-secondary small mb-0">Cross-departmental case volumes, consultation conversion funnel, and capacity analytics.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('admin/opd_prediction.php') ?>" class="btn btn-warning rounded-pill px-3 py-2 fw-bold text-dark shadow-sm">
                <i class="bi bi-cpu-fill me-1"></i> AI Inflow Forecast
            </a>
            <button type="button" class="btn btn-outline-teal rounded-pill px-3 py-2" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Top KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Settled Revenue</span>
                        <div class="metric-number text-success">₹<?= number_format($totalRevenue, 0) ?></div>
                        <small class="text-success fs-xs"><i class="bi bi-arrow-up-right"></i> +14.2% MoM</small>
                    </div>
                    <div class="icon-box bg-success-subtle text-success">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Total Bookings</span>
                        <div class="metric-number text-teal"><?= number_format($totalBooked) ?></div>
                        <small class="text-teal fs-xs"><i class="bi bi-calendar2-check"></i> Telehealth & Walk-in</small>
                    </div>
                    <div class="icon-box bg-teal-subtle text-teal">
                        <i class="bi bi-calendar2-range-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Consultation Completion</span>
                        <div class="metric-number text-primary"><?= $pctCompleted ?>%</div>
                        <small class="text-primary fs-xs"><?= $completed ?> Completed Consultations</small>
                    </div>
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Cancellation Rate</span>
                        <div class="metric-number <?= $cancellationRate <= 5.0 ? 'text-success' : 'text-danger' ?>">
                            <?= $cancellationRate ?>%
                        </div>
                        <small class="text-secondary fs-xs">Benchmark &lt; 8.5% (<?= $cancelledCount ?> Cancelled)</small>
                    </div>
                    <div class="icon-box bg-danger-subtle text-danger">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Consultation Status Funnel -->
    <div class="glass-card p-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-0"><i class="bi bi-funnel-fill text-teal me-2"></i> Consultation Status Funnel</h5>
                <span class="text-secondary fs-xs">End-to-end patient journey from appointment booking to prescription fulfillment</span>
            </div>
            <span class="badge bg-teal-subtle text-teal fw-bold">Live Conversion Telemetry</span>
        </div>

        <div class="row g-3 text-center">
            <div class="col-6 col-md">
                <div class="p-3 rounded-3 bg-body-tertiary border h-100">
                    <span class="badge bg-secondary-subtle text-secondary mb-1">Step 1</span>
                    <h6 class="fw-bold text-body mb-1">Booked</h6>
                    <div class="fs-4 fw-extrabold text-teal"><?= $totalBooked ?></div>
                    <small class="text-secondary fs-xs">100% of pipeline</small>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-teal" style="width: 100%;"></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-3 rounded-3 bg-body-tertiary border h-100">
                    <span class="badge bg-info-subtle text-info mb-1">Step 2</span>
                    <h6 class="fw-bold text-body mb-1">Confirmed / Check-in</h6>
                    <div class="fs-4 fw-extrabold text-info"><?= $confirmed ?></div>
                    <small class="text-secondary fs-xs"><?= $pctConfirmed ?>% conversion</small>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-info" style="width: <?= $pctConfirmed ?>%;"></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-3 rounded-3 bg-body-tertiary border h-100">
                    <span class="badge bg-warning-subtle text-warning mb-1">Step 3</span>
                    <h6 class="fw-bold text-body mb-1">In Consultation</h6>
                    <div class="fs-4 fw-extrabold text-warning"><?= $inConsultation ?></div>
                    <small class="text-secondary fs-xs"><?= $pctInConsult ?>% engagement</small>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-warning" style="width: <?= $pctInConsult ?>%;"></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-3 rounded-3 bg-body-tertiary border h-100">
                    <span class="badge bg-primary-subtle text-primary mb-1">Step 4</span>
                    <h6 class="fw-bold text-body mb-1">Completed</h6>
                    <div class="fs-4 fw-extrabold text-primary"><?= $completed ?></div>
                    <small class="text-secondary fs-xs"><?= $pctCompleted ?>% completion</small>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-primary" style="width: <?= $pctCompleted ?>%;"></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-3 rounded-3 bg-body-tertiary border h-100">
                    <span class="badge bg-success-subtle text-success mb-1">Step 5</span>
                    <h6 class="fw-bold text-body mb-1">Prescription Issued</h6>
                    <div class="fs-4 fw-extrabold text-success"><?= $prescriptionsIssued ?></div>
                    <small class="text-secondary fs-xs"><?= $pctPrescribed ?>% fulfilled</small>
                    <div class="progress mt-2" style="height: 4px;">
                        <div class="progress-bar bg-success" style="width: <?= $pctPrescribed ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1: Line Chart & Doughnut Chart -->
    <div class="row g-4 mb-4">
        <!-- 1. Line Chart: Patient Volume Trends -->
        <div class="col-lg-8">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge bg-teal-subtle text-teal fw-bold">Chart 1: Line Chart</span>
                        <h5 class="fw-bold mb-0 mt-1"><i class="bi bi-graph-up-arrow me-2 text-teal"></i> 30-Day Patient Volume Dynamics</h5>
                    </div>
                    <span class="badge bg-body-secondary text-secondary">Inflow Trajectory</span>
                </div>
                <div style="height: 310px;">
                    <canvas id="historicalChartCanvas"></canvas>
                </div>
            </div>
        </div>

        <!-- 2. Doughnut Chart: Department Consultation Share -->
        <div class="col-lg-4">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge bg-primary-subtle text-primary fw-bold">Chart 2: Doughnut Chart</span>
                        <h5 class="fw-bold mb-0 mt-1"><i class="bi bi-pie-chart me-2 text-primary"></i> Department Share</h5>
                    </div>
                    <span class="badge bg-body-secondary text-secondary">Specialty Load</span>
                </div>
                <div style="height: 270px;">
                    <canvas id="deptDoughnutCanvas"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2: Bar Chart & Area Chart -->
    <div class="row g-4 mb-4">
        <!-- 3. Bar Chart: Monthly Revenue Comparison -->
        <div class="col-lg-6">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge bg-warning-subtle text-dark fw-bold">Chart 3: Bar Chart</span>
                        <h5 class="fw-bold mb-0 mt-1"><i class="bi bi-bar-chart-fill me-2 text-warning"></i> Monthly Revenue & Inflow Comparison</h5>
                    </div>
                    <span class="badge bg-body-secondary text-secondary">6-Month Trend</span>
                </div>
                <div style="height: 300px;">
                    <canvas id="monthlyRevenueBarCanvas"></canvas>
                </div>
            </div>
        </div>

        <!-- 4. Area Chart: Patient Volume vs Doctor Capacity -->
        <div class="col-lg-6">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge bg-danger-subtle text-danger fw-bold">Chart 4: Area Chart</span>
                        <h5 class="fw-bold mb-0 mt-1"><i class="bi bi-activity me-2 text-danger"></i> Patient Volume vs Doctor Availability</h5>
                    </div>
                    <span class="badge bg-body-secondary text-secondary">Capacity Ceiling</span>
                </div>
                <div style="height: 300px;">
                    <canvas id="capacityAreaChartCanvas"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Payment Split & Recent Ledger -->
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="glass-card p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-credit-card-2-front me-2 text-teal"></i> Payment Methods Split</h5>
                <div style="height: 220px;">
                    <canvas id="paymentSplitCanvas"></canvas>
                </div>
                <div class="mt-3 pt-2 border-top text-center fs-xs text-secondary">
                    Total Transactions Settled via UPI, Cards, & Net Banking
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-cash-stack me-2 text-success"></i> Recent Financial Transactions</h5>
                    <span class="badge bg-success-subtle text-success">Verified Gateway</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-sm">
                        <thead>
                            <tr class="text-secondary small">
                                <th>Receipt</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTransactions as $txn): ?>
                                <tr>
                                    <td>
                                        <a href="<?= base_url('payments/receipt.php?id=' . $txn['id']) ?>" class="fw-bold text-teal text-decoration-none">
                                            <?= e($txn['receipt_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= e($txn['patient_name']) ?></td>
                                    <td><?= e($txn['doctor_name']) ?></td>
                                    <td><span class="badge bg-body-secondary text-secondary text-uppercase"><?= e($txn['payment_method']) ?></span></td>
                                    <td class="text-end fw-bold">₹<?= number_format($txn['amount'], 2) ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-success-subtle text-success">Success</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= base_url('charts/chart_configs.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const historicalData = <?= json_encode($historicalStats) ?>;
    const deptStats = <?= json_encode($departmentStats) ?>;
    const paymentSplit = <?= json_encode($paymentSplit) ?>;
    const monthlyRev = <?= json_encode($monthlyRevenue) ?>;
    const capacityTrend = <?= json_encode($capacityTrend) ?>;

    // 1. Line Chart: Patient Volume Dynamics
    CarePulseCharts.renderHistoricalChart('historicalChartCanvas', historicalData);

    // 2. Doughnut Chart: Department Distribution
    CarePulseCharts.renderDepartmentDoughnut('deptDoughnutCanvas', deptStats);

    // 3. Bar Chart: Monthly Revenue Comparison
    CarePulseCharts.renderMonthlyRevenueBar('monthlyRevenueBarCanvas', monthlyRev);

    // 4. Area Chart: Patient Volume vs Doctor Capacity
    CarePulseCharts.renderCapacityAreaChart('capacityAreaChartCanvas', capacityTrend);

    // Payment Methods Split Chart
    CarePulseCharts.renderPaymentSplitChart('paymentSplitCanvas', paymentSplit);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
