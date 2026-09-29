/**
 * CarePulse AI - Chart.js Visualizations & Analytics Configurations
 * Responsive, dark/light theme aware charts for Hospital Management.
 */

window.CarePulseCharts = {
    // 1. 30-Day OPD Patient Inflow & Wait Time Line Chart
    renderHistoricalChart(canvasId, statsData) {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !statsData || statsData.length === 0) return null;

        const labels = statsData.map(d => d.stat_date.substring(5)); // MM-DD
        const patientTotals = statsData.map(d => d.total_patients);
        const onlineTotals = statsData.map(d => d.online_consultations);
        const hospitalTotals = statsData.map(d => d.hospital_visits);

        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Patients',
                        data: patientTotals,
                        borderColor: '#0d9488',
                        backgroundColor: 'rgba(13, 148, 136, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3
                    },
                    {
                        label: 'Online Consultations',
                        data: onlineTotals,
                        borderColor: '#0284c7',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.35,
                        pointRadius: 2
                    },
                    {
                        label: 'In-Hospital Visits',
                        data: hospitalTotals,
                        borderColor: '#10b981',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.35,
                        pointRadius: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: { beginAtZero: false, grid: { color: 'rgba(226, 232, 240, 0.4)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    },

    // 2. Department Patient Volume Distribution Doughnut
    renderDepartmentDoughnut(canvasId, deptStats) {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !deptStats) return null;

        const labels = deptStats.map(d => d.department_name);
        const data = deptStats.map(d => d.appointment_count);

        const colors = [
            '#0d9488', '#0284c7', '#10b981', '#f59e0b',
            '#8b5cf6', '#ec4899', '#06b6d4', '#64748b'
        ];

        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                },
                cutout: '65%'
            }
        });
    },

    // 3. Payment Methods Split Doughnut/Pie
    renderPaymentSplitChart(canvasId, splitData) {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !splitData) return null;

        const labels = splitData.map(d => d.payment_method.toUpperCase());
        const data = splitData.map(d => parseFloat(d.method_revenue || 0));

        return new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: ['#10b981', '#0284c7', '#f59e0b', '#8b5cf6'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    },

    // 4. Monthly Revenue & Inflow Bar Chart
    renderMonthlyRevenueBar(canvasId, monthlyData) {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !monthlyData || monthlyData.length === 0) return null;

        const labels = monthlyData.map(m => m.month);
        const revenues = monthlyData.map(m => m.revenue);
        const patientCounts = monthlyData.map(m => m.patients);

        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Hospital Revenue (₹)',
                        data: revenues,
                        backgroundColor: '#0d9488',
                        borderRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Patients Handled',
                        data: patientCounts,
                        backgroundColor: '#0284c7',
                        borderRadius: 6,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            callback: value => '₹' + (value >= 1000 ? (value / 1000) + 'k' : value)
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    },

    // 5. Patient Volume vs Doctor Availability Capacity Area Chart
    renderCapacityAreaChart(canvasId, capacityData) {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !capacityData || capacityData.length === 0) return null;

        const labels = capacityData.map(d => d.date);
        const patientTotals = capacityData.map(d => d.patients);
        const capacityThreshold = capacityData.map(d => d.capacity);

        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Daily Patient Inflow (Area)',
                        data: patientTotals,
                        borderColor: '#0d9488',
                        backgroundColor: 'rgba(13, 148, 136, 0.25)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2
                    },
                    {
                        label: 'Total Doctor Roster Capacity',
                        data: capacityThreshold,
                        borderColor: '#ef4444',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        fill: false,
                        tension: 0,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: { beginAtZero: false, grid: { color: 'rgba(226, 232, 240, 0.4)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    },

    // 6. ML OPD Departmental Inflow Forecast Bar Chart
    renderOpdForecastBar(canvasId, deptForecast) {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !deptForecast) return null;

        const labels = Object.keys(deptForecast);
        const data = Object.values(deptForecast);

        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Predicted Tomorrow Patient Inflow',
                    data: data,
                    backgroundColor: '#0d9488',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true },
                    x: { grid: { display: false } }
                }
            }
        });
    }
};
