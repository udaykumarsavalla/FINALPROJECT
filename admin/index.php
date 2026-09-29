<?php
/**
 * CarePulse AI - Hospital Executive & Admin Command Center
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['admin']);

$pageTitle = "Hospital Executive Command Center";
$activeMenu = "admin_dashboard";

// 1. KPI Counts
$totalPatients = (int)Database::fetchOne("SELECT COUNT(*) as c FROM users WHERE role = 'patient'")['c'];
$totalDoctors = (int)Database::fetchOne("SELECT COUNT(*) as c FROM doctor_profiles")['c'];
$totalAppointments = (int)Database::fetchOne("SELECT COUNT(*) as c FROM appointments")['c'];
$totalRevenue = (float)Database::fetchOne("SELECT COALESCE(SUM(amount), 0) as s FROM payments WHERE payment_status = 'successful'")['s'];

// 2. Doctor Workload
$doctorWorkload = Database::fetchAll("
    SELECT u.name as doctor_name, dep.name as department_name, d.specialization, d.rating,
           COUNT(a.id) as total_appointments,
           SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
           SUM(CASE WHEN a.status = 'confirmed' THEN 1 ELSE 0 END) as pending_count
    FROM doctor_profiles d
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON d.department_id = dep.id
    LEFT JOIN appointments a ON d.id = a.doctor_id
    GROUP BY d.id
    ORDER BY total_appointments DESC
");

// 3. Department Statistics
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

// 4. Historical 30-Day OPD Stats for Chart.js
$historicalStats = Database::fetchAll("
    SELECT stat_date, total_patients, online_consultations, hospital_visits, avg_wait_time_mins, total_revenue
    FROM opd_daily_stats
    ORDER BY stat_date ASC
");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-lg-5 py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <span class="badge bg-teal-subtle text-teal fw-bold px-3 py-1 rounded-pill mb-1">
                <i class="bi bi-shield-check me-1"></i> Hospital Administration & Clinical Governance
            </span>
            <h2 class="display-6 fw-bold mb-0">Executive Dashboard</h2>
            <p class="text-secondary small mb-0">Live hospital telemedicine telemetry, doctor caseloads, revenue ledger, and predictive AI.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('admin/opd_prediction.php') ?>" class="btn btn-warning rounded-pill px-4 py-2 fw-bold text-dark shadow-sm">
                <i class="bi bi-cpu-fill me-1"></i> AI OPD Inflow Predictor
            </a>
            <a href="<?= base_url('admin/analytics.php') ?>" class="btn btn-teal text-white rounded-pill px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-bar-chart-fill me-1"></i> Detailed Analytics
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Registered Patients</span>
                        <div class="metric-number text-body"><?= number_format($totalPatients) ?></div>
                        <small class="text-success fs-xs"><i class="bi bi-arrow-up-right"></i> +12% this month</small>
                    </div>
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Consultations Scheduled</span>
                        <div class="metric-number text-teal"><?= number_format($totalAppointments) ?></div>
                        <small class="text-teal fs-xs"><i class="bi bi-check2-all"></i> Online & Walk-in</small>
                    </div>
                    <div class="icon-box bg-teal-subtle text-teal">
                        <i class="bi bi-calendar2-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Active Specialists</span>
                        <div class="metric-number text-info"><?= $totalDoctors ?></div>
                        <small class="text-secondary fs-xs">Across 8 Departments</small>
                    </div>
                    <div class="icon-box bg-info-subtle text-info">
                        <i class="bi bi-hospital-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-semibold">Telehealth Revenue</span>
                        <div class="metric-number text-success">₹<?= number_format($totalRevenue, 0) ?></div>
                        <small class="text-success fs-xs"><i class="bi bi-cash-stack"></i> 100% Settled</small>
                    </div>
                    <div class="icon-box bg-success-subtle text-success">
                        <i class="bi bi-currency-rupee"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Machine Learning Inflow Callout Banner -->
    <div class="glass-card p-4 mb-4 border border-warning shadow-lg bg-warning-subtle text-dark">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-box bg-warning text-dark rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                    <i class="bi bi-cpu fs-3"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">AI Next-Day Patient Inflow Predictor Model</h5>
                    <p class="small text-muted mb-0">Scikit-learn Random Forest model trained on 365 operational records with 92.7% R² accuracy.</p>
                </div>
            </div>
            <a href="<?= base_url('admin/opd_prediction.php') ?>" class="btn btn-dark rounded-pill px-4 py-2 fw-bold text-nowrap">
                Run Tomorrow Forecast <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <!-- Chart.js 30-Day Historical Trend & Department Distribution -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-graph-up me-2 text-teal"></i> 30-Day Outpatient Trajectory</h5>
                    <span class="badge bg-teal-subtle text-teal">Online vs In-Hospital</span>
                </div>
                <div style="height: 310px;">
                    <canvas id="historicalChartCanvas"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="glass-card p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart me-2 text-primary"></i> Department Patient Share</h5>
                <div style="height: 290px;">
                    <canvas id="deptDoughnutCanvas"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Doctor Workload & Department Statistics Tables -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-person-lines-fill me-2 text-teal"></i> Doctor Workload & Queue Caseload</h5>
                    <a href="<?= base_url('admin/doctors.php') ?>" class="text-teal small text-decoration-none fw-semibold">Manage Doctors</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small">
                                <th>Specialist</th>
                                <th>Department</th>
                                <th class="text-center">Total Appts</th>
                                <th class="text-center">Completed</th>
                                <th class="text-center">Pending</th>
                                <th class="text-end">Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctorWorkload as $doc): ?>
                                <tr>
                                    <td>
                                        <strong class="text-body d-block"><?= e($doc['doctor_name']) ?></strong>
                                        <small class="text-secondary"><?= e($doc['specialization']) ?></small>
                                    </td>
                                    <td><span class="badge bg-body-secondary text-secondary"><?= e($doc['department_name']) ?></span></td>
                                    <td class="text-center fw-bold"><?= $doc['total_appointments'] ?></td>
                                    <td class="text-center text-success fw-bold"><?= $doc['completed_count'] ?></td>
                                    <td class="text-center text-warning fw-bold"><?= $doc['pending_count'] ?></td>
                                    <td class="text-end text-warning fw-bold">
                                        <i class="bi bi-star-fill"></i> <?= number_format($doc['rating'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-building me-2 text-success"></i> Department Revenue & Volume</h5>

                <div class="space-y-3">
                    <?php foreach ($departmentStats as $ds): ?>
                        <div class="p-3 rounded-3 bg-body-tertiary border mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-body">
                                    <i class="bi <?= e($ds['icon']) ?> text-teal me-1"></i> <?= e($ds['department_name']) ?>
                                </span>
                                <strong class="text-teal">₹<?= number_format($ds['department_revenue'], 0) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between text-secondary fs-xs">
                                <span>Total Appointments: <strong><?= $ds['appointment_count'] ?></strong></span>
                                <span>Billing Active</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
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

    CarePulseCharts.renderHistoricalChart('historicalChartCanvas', historicalData);
    CarePulseCharts.renderDepartmentDoughnut('deptDoughnutCanvas', deptStats);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
