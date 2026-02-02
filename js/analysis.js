document.addEventListener('DOMContentLoaded', function() {
    
    // Store chart instances to destroy them properly on refresh
    let charts = {
        daily: null,
        job: null,
        travelState: null,
        travelCounty: null,
        profit: null,
        stateActivity: null,
        countyActivity: null
    };

    // Elements
    const rangeSelect = document.getElementById('range-preset');
    const customInputs = document.getElementById('custom-date-inputs');
    const startInput = document.getElementById('analysis-start');
    const endInput = document.getElementById('analysis-end');
    const updateBtn = document.getElementById('btn-refresh-analysis');
    const userSelect = document.getElementById('analysis-target-user'); 

    // --- Tabs Logic ---
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Deactivate all
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            
            // Activate clicked
            e.target.classList.add('active');
            const target = e.target.dataset.tab;
            document.getElementById(target).classList.add('active');
        });
    });

    // --- Dynamic Update Listeners ---
    
    // Period Selector
    rangeSelect.addEventListener('change', () => {
        if (rangeSelect.value === 'custom') {
            customInputs.classList.remove('hidden');
        } else {
            customInputs.classList.add('hidden');
            loadStats(); // Auto-update for presets
        }
    });

    // Custom Dates
    startInput.addEventListener('change', loadStats);
    endInput.addEventListener('change', loadStats);

    // User Dropdown (if accessible)
    if (userSelect) {
        userSelect.addEventListener('change', loadStats);
    }

    // Manual Button
    if (updateBtn) {
        updateBtn.addEventListener('click', loadStats);
    }

    // Initial Load
    loadStats();

    // --- Core Data Function ---
    function loadStats() {
        const period = rangeSelect.value;
        let queryParams = '?period=' + period;
        
        if (period === 'custom') {
            const s = startInput.value;
            const e = endInput.value;
            if(!s || !e) return; // Wait for both dates
            queryParams += '&start=' + s + '&end=' + e;
        }

        if (userSelect) {
            queryParams += '&target_user=' + userSelect.value;
        } else {
            // Fallback for non-admins if hidden field exists or default
            queryParams += '&target_user=self';
        }

        const url = OC.generateUrl('/apps/stech_timesheet/api/analysis/stats') + queryParams;
        
        fetch(url, {
            headers: { 'requesttoken': OC.requestToken, 'OCS-APIRequest': 'true' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                OC.dialogs.alert(data.error, 'Error');
                return;
            }
            updateUI(data);
        })
        .catch(err => console.error("Analysis Load Error:", err));
    }

    function updateUI(data) {
        // 1. Update Top Stats Cards
        document.getElementById('stat-total-hours').innerText = data.total_hours;
        document.getElementById('stat-reg-hours').innerText = data.stats.regular_hours;
        document.getElementById('stat-pto-hours').innerText = data.stats.pto_hours;
        document.getElementById('stat-overtime-hours').innerText = data.overtime_hours;
        
        // 2. Overview Chart (Line)
        renderOverviewChart(data.trend);

        // 3. Travel Tab (Stats + Mini Charts)
        updateTravelStats(data.travel);
        renderTravelCharts(data.states, data.counties); // Using aggregate state/county data for distribution

        // 4. Job Breakdown (Donut + Table) - Check permission implicitly by element existence
        if (document.getElementById('chart-jobs')) {
            renderJobCharts(data.jobs, data.total_hours);
        }

        // 5. Job Profitability (Bar)
        if (document.getElementById('chart-profitability')) {
            renderProfitabilityChart(data.jobs);
        }

        // 6. State Activity (Bar)
        renderStateActivityChart(data.states);

        // 7. County Activity (Bar)
        renderCountyActivityChart(data.counties);
    }

    function updateTravelStats(travel) {
        document.getElementById('val-total-miles').innerText = travel.total_miles;
        document.getElementById('val-per-diem').innerText = travel.per_diem_days;
        document.getElementById('val-overnight').innerText = travel.overnight_stays;
        document.getElementById('val-expenses').innerText = '$' + travel.total_expenses;
    }

    // --- Chart Rendering Helpers ---

    function getThemeColors() {
        const isDark = document.body.classList.contains('theme--dark');
        return {
            grid: isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)',
            text: isDark ? '#ddd' : '#666'
        };
    }

    // 1. Overview: Line Chart
    function renderOverviewChart(trend) {
        const ctx = document.getElementById('chart-daily').getContext('2d');
        if (charts.daily) charts.daily.destroy();
        const theme = getThemeColors();

        charts.daily = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [{
                    label: 'Hours Worked',
                    data: trend.values,
                    borderColor: '#0082c9', // Nextcloud Blue
                    backgroundColor: 'rgba(0, 130, 201, 0.2)',
                    fill: true,
                    tension: 0.3, // Curve the lines
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text } },
                    x: { grid: { display: false }, ticks: { color: theme.text } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // 2. Travel Distribution: Mini Doughnuts
    function renderTravelCharts(states, counties) {
        // State Distribution
        const ctxS = document.getElementById('chart-travel-state');
        if(ctxS) {
            if (charts.travelState) charts.travelState.destroy();
            charts.travelState = new Chart(ctxS, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(states),
                    datasets: [{
                        data: Object.values(states),
                        backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF']
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { legend: { position: 'right', labels: { color: getThemeColors().text, boxWidth: 10 } } } 
                }
            });
        }

        // County Distribution
        const ctxC = document.getElementById('chart-travel-county');
        if(ctxC) {
            if (charts.travelCounty) charts.travelCounty.destroy();
            charts.travelCounty = new Chart(ctxC, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(counties),
                    datasets: [{
                        data: Object.values(counties),
                        backgroundColor: ['#FF9F40', '#9966FF', '#4BC0C0', '#36A2EB', '#FF6384']
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { legend: { position: 'right', labels: { color: getThemeColors().text, boxWidth: 10 } } } 
                }
            });
        }
    }

    // 3. Job Breakdown: Doughnut + Table
    function renderJobCharts(jobs, total) {
        const ctx = document.getElementById('chart-jobs');
        if (charts.job) charts.job.destroy();
        
        const labels = jobs.map(j => j.name);
        const values = jobs.map(j => j.hours);
        const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED', '#8AC249'];

        charts.job = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { color: getThemeColors().text } } }
            }
        });

        // Update Table
        const tbody = document.getElementById('job-table-body');
        tbody.innerHTML = '';
        if(jobs.length === 0) tbody.innerHTML = '<tr><td colspan="3">No job data found.</td></tr>';
        
        jobs.forEach(j => {
            const pct = total > 0 ? ((j.hours / total) * 100).toFixed(1) : 0;
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${j.name}</td><td>${j.hours}</td><td>${pct}%</td>`;
            tbody.appendChild(tr);
        });
    }

    // 4. Job Profitability: Bar Chart
    function renderProfitabilityChart(jobs) {
        const ctx = document.getElementById('chart-profitability');
        if (charts.profit) charts.profit.destroy();
        const theme = getThemeColors();

        charts.profit = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: jobs.map(j => j.name),
                datasets: [{
                    label: 'Total Hours Clocked',
                    data: jobs.map(j => j.hours),
                    backgroundColor: '#4BC0C0',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text }, title: {display: true, text: 'Hours', color: theme.text} },
                    x: { grid: { display: false }, ticks: { color: theme.text } }
                }
            }
        });
    }

    // 5. State Activity: Bar Chart
    function renderStateActivityChart(states) {
        const ctx = document.getElementById('chart-state-activity');
        if (charts.stateActivity) charts.stateActivity.destroy();
        const theme = getThemeColors();

        charts.stateActivity = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(states),
                datasets: [{
                    label: 'Job Volume (Visits)',
                    data: Object.values(states),
                    backgroundColor: '#36A2EB',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text, stepSize: 1 } },
                    x: { grid: { display: false }, ticks: { color: theme.text } }
                }
            }
        });
    }

    // 6. County Activity: Horizontal Bar Chart
    function renderCountyActivityChart(counties) {
        const ctx = document.getElementById('chart-county-activity');
        if (charts.countyActivity) charts.countyActivity.destroy();
        const theme = getThemeColors();

        charts.countyActivity = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(counties),
                datasets: [{
                    label: 'Job Volume (Visits)',
                    data: Object.values(counties),
                    backgroundColor: '#9966FF',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: 'y', // Horizontal bars are better for long county names
                scales: {
                    x: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text, stepSize: 1 } },
                    y: { grid: { display: false }, ticks: { color: theme.text } }
                }
            }
        });
    }

});