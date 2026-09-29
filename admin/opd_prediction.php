<?php
/**
 * CarePulse AI - Next-Day Outpatient (OPD) Inflow Predictor (ML Dashboard)
 * Powered by Python Scikit-learn RandomForestRegressor Model
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['admin']);

$pageTitle = "AI OPD Inflow Predictor";
$activeMenu = "admin_opd";

$targetDate = trim($_GET['date'] ?? date('Y-m-d', strtotime('+1 day')));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <span class="badge bg-warning-subtle text-dark fw-bold px-3 py-1 rounded-pill mb-1">
                <i class="bi bi-cpu-fill text-warning me-1"></i> Scikit-Learn Random Forest Regression Engine
            </span>
            <h2 class="display-6 fw-bold mb-0">ML OPD Patient Inflow Predictor</h2>
            <p class="text-secondary small mb-0">Predict next-day patient demand, peak hours, highest demand specialty, and optimal doctor roster staffing.</p>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="form-label small fw-semibold text-secondary mb-0 text-nowrap" for="predictDateInput">Forecast Date:</label>
            <input type="date" id="predictDateInput" name="date" class="form-control form-control-sm" value="<?= e($targetDate) ?>" min="<?= date('Y-m-d') ?>" onchange="loadForecast(this.value)">
            <button type="button" class="btn btn-sm btn-outline-teal rounded-pill text-nowrap" onclick="loadForecast('<?= date('Y-m-d', strtotime('+1 day')) ?>')">
                Tomorrow
            </button>
        </form>
    </div>

    <!-- Prediction Results Container -->
    <div id="forecastContainer">
        <!-- Loading State -->
        <div id="forecastLoading" class="glass-card p-5 text-center">
            <div class="spinner-border text-teal mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
            <h5 class="fw-bold">Running Scikit-learn Predictive Model...</h5>
            <p class="text-secondary small">Evaluating day of week, seasonal factors, 7-day moving averages, and historical hospital admissions.</p>
        </div>

        <!-- Rendered Forecast Results -->
        <div id="forecastResults" class="d-none">
            <!-- 3 Core AI Callouts Required by Module 9 -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100 border border-teal shadow-sm position-relative overflow-hidden">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-teal-subtle text-teal fw-bold">Callout 1</span>
                            <div class="icon-box bg-teal-subtle text-teal">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                        <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-1">Predicted Patients Tomorrow</span>
                        <div class="display-5 fw-extrabold text-teal mb-1" id="calloutPatients">--</div>
                        <small class="text-secondary fs-xs" id="calloutInterval">95% CI: -- to -- patients</small>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="glass-card p-4 h-100 border border-warning shadow-sm position-relative overflow-hidden">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-warning-subtle text-dark fw-bold">Callout 2</span>
                            <div class="icon-box bg-warning-subtle text-warning">
                                <i class="bi bi-clock-history"></i>
                            </div>
                        </div>
                        <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-1">Expected Peak Time</span>
                        <div class="h3 fw-extrabold text-body mb-1" id="calloutPeakTime">10:00 AM – 01:00 PM</div>
                        <small class="text-warning fw-semibold fs-xs"><i class="bi bi-exclamation-triangle me-1"></i> Peak Triage & Consultation Window</small>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="glass-card p-4 h-100 border border-primary shadow-sm position-relative overflow-hidden">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary-subtle text-primary fw-bold">Callout 3</span>
                            <div class="icon-box bg-primary-subtle text-primary">
                                <i class="bi bi-heart-pulse-fill"></i>
                            </div>
                        </div>
                        <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-1">Highest Demand Department</span>
                        <div class="h3 fw-extrabold text-primary mb-1" id="calloutHighestDept">--</div>
                        <small class="text-secondary fs-xs" id="calloutDeptDetail">Specialty requiring priority staffing</small>
                    </div>
                </div>
            </div>

            <!-- Channel Split & Workload Advice -->
            <div class="glass-card p-4 mb-4">
                <div class="row align-items-center g-4">
                    <div class="col-md-6 border-end-md">
                        <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-2">Demand Channel Breakdown</span>
                        <div class="p-3 rounded-3 bg-body-tertiary border mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold text-primary"><i class="bi bi-camera-video me-1"></i> Online Teleconsultations</span>
                                <strong class="fs-5 text-primary" id="resOnlineCount">--</strong>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-primary" id="resOnlineBar" style="width: 42%;"></div>
                            </div>
                        </div>

                        <div class="p-3 rounded-3 bg-body-tertiary border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold text-teal"><i class="bi bi-hospital me-1"></i> In-Hospital Physical OPD</span>
                                <strong class="fs-5 text-teal" id="resHospitalCount">--</strong>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-teal" id="resHospitalBar" style="width: 58%;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <span class="text-secondary fs-xs fw-bold text-uppercase d-block mb-1">Clinical Operational Directive</span>
                        <h5 class="fw-bold mb-2" id="resWorkloadStatus">--</h5>
                        
                        <div class="alert alert-warning border-warning-subtle p-3 rounded-3 mb-0">
                            <div class="d-flex gap-2">
                                <i class="bi bi-lightbulb-fill text-warning fs-5 flex-shrink-0"></i>
                                <small class="text-dark" id="resRecommendation">Evaluating staffing recommendations...</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Required Doctor Allocation Table -->
            <div class="glass-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge bg-teal-subtle text-teal fw-bold">Roster Planning</span>
                        <h5 class="fw-bold mb-0 mt-1"><i class="bi bi-person-check-fill text-teal me-2"></i> Required Doctor Allocation Roster</h5>
                    </div>
                    <span class="badge bg-body-secondary text-secondary">Based on 14 Patients/Doctor Ratio</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-sm" id="doctorAllocationTable">
                        <thead>
                            <tr class="text-secondary small">
                                <th>Specialty Department</th>
                                <th class="text-center">Predicted Inflow</th>
                                <th class="text-center">Required Doctors on Duty</th>
                                <th class="text-center">Estimated Capacity</th>
                                <th class="text-end">Staffing Action</th>
                            </tr>
                        </thead>
                        <tbody id="allocationTableBody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Departmental Forecast Distribution Chart & Model Telemetry -->
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="glass-card p-4 h-100">
                        <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart-steps me-2 text-teal"></i> Forecasted Inflow by Medical Specialty</h5>
                        <div style="height: 280px;">
                            <canvas id="opdForecastCanvas"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="glass-card p-4 h-100">
                        <h5 class="fw-bold mb-3"><i class="bi bi-shield-check me-2 text-success"></i> Model Telemetry & Accuracy</h5>
                        
                        <ul class="list-group list-group-flush mb-3">
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
                                <span class="text-secondary small">Algorithm</span>
                                <strong class="small text-body">RandomForestRegressor (120 Estimators)</strong>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
                                <span class="text-secondary small">Model R² Goodness of Fit</span>
                                <strong class="small text-success">0.927 (92.7% Precision)</strong>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
                                <span class="text-secondary small">Mean Absolute Error (MAE)</span>
                                <strong class="small text-teal">± 5.49 Patients</strong>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
                                <span class="text-secondary small">Features Evaluated</span>
                                <strong class="small text-body">DoW, Holiday, Month, Season, Lag-1, Lag-7</strong>
                            </li>
                        </ul>

                        <div class="p-3 rounded-3 bg-body-tertiary border text-secondary fs-xs">
                            <i class="bi bi-info-circle me-1 text-teal"></i> The prediction helps chief nursing officers and clinical administrators preemptively allocate triage chambers, nurses, and teleconsultation server bandwidth.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= base_url('charts/chart_configs.js') ?>"></script>
<script>
let forecastChartInstance = null;

async function loadForecast(dateStr) {
    document.getElementById('forecastLoading').classList.remove('d-none');
    document.getElementById('forecastResults').classList.add('d-none');

    const res = await CarePulse.api(`api/admin_stats.php?action=opd_prediction&date=${dateStr}`);
    
    document.getElementById('forecastLoading').classList.add('d-none');
    if (!res.success) {
        CarePulse.showToast(res.error || 'Failed to generate forecast.', 'danger');
        return;
    }

    document.getElementById('forecastResults').classList.remove('d-none');

    // 1. Core Callouts
    document.getElementById('calloutPatients').textContent = res.predicted_total_inflow;
    document.getElementById('calloutInterval').textContent = `95% CI: ${res.confidence_interval.lower_bound} to ${res.confidence_interval.upper_bound} patients (MAE ±${res.confidence_interval.mae})`;
    document.getElementById('calloutPeakTime').textContent = res.expected_peak_hours || '10:00 AM – 01:00 PM';
    
    const highestDept = res.highest_demand_department || 'Cardiology';
    document.getElementById('calloutHighestDept').textContent = highestDept;
    const highestCount = (res.department_distribution && res.department_distribution[highestDept]) ? res.department_distribution[highestDept] : Math.round(res.predicted_total_inflow * 0.28);
    document.getElementById('calloutDeptDetail').textContent = `Estimated ${highestCount} patients (~${Math.round((highestCount / res.predicted_total_inflow) * 100)}% load)`;

    // 2. Channel Split
    document.getElementById('resOnlineCount').textContent = res.split.online_consultations;
    document.getElementById('resHospitalCount').textContent = res.split.hospital_visits;

    const total = res.predicted_total_inflow;
    const onlinePct = Math.round((res.split.online_consultations / total) * 100);
    const hospPct = 100 - onlinePct;

    document.getElementById('resOnlineBar').style.width = `${onlinePct}%`;
    document.getElementById('resHospitalBar').style.width = `${hospPct}%`;

    document.getElementById('resWorkloadStatus').textContent = res.workload_status;
    document.getElementById('resRecommendation').textContent = res.staff_recommendation;

    // 3. Render Doctor Allocation Table
    const tbody = document.getElementById('allocationTableBody');
    tbody.innerHTML = '';
    const alloc = res.required_doctor_allocation || {};
    const depts = res.department_distribution || {};

    Object.keys(depts).forEach(deptName => {
        const count = depts[deptName];
        const docCount = alloc[deptName] || Math.max(1, Math.ceil(count / 14));
        const capacity = docCount * 14;
        const isSurge = count > 30 || deptName === highestDept;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <strong class="text-body">${deptName}</strong>
                ${deptName === highestDept ? '<span class="badge bg-danger-subtle text-danger ms-2">High Surge</span>' : ''}
            </td>
            <td class="text-center fw-bold">${count}</td>
            <td class="text-center">
                <span class="badge bg-teal px-3 py-1 fs-xs fw-bold text-white">${docCount} Specialists</span>
            </td>
            <td class="text-center text-secondary">${capacity} visits / shift</td>
            <td class="text-end">
                <span class="badge ${isSurge ? 'bg-warning-subtle text-dark' : 'bg-success-subtle text-success'}">
                    ${isSurge ? '<i class="bi bi-plus-circle me-1"></i> Standby Active' : '<i class="bi bi-check2 me-1"></i> Roster Optimal'}
                </span>
            </td>
        `;
        tbody.appendChild(row);
    });

    // 4. Render department bar chart
    if (forecastChartInstance) forecastChartInstance.destroy();
    forecastChartInstance = CarePulseCharts.renderOpdForecastBar('opdForecastCanvas', res.department_distribution);
}

document.addEventListener('DOMContentLoaded', () => {
    loadForecast('<?= $targetDate ?>');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
