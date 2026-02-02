document.addEventListener('DOMContentLoaded', function() {
    
    // Chart Instances
    let dailyChart = null;
    let jobChart = null;

    // Elements
    const rangeSelect = document.getElementById('range-preset');
    const customInputs = document.getElementById('custom-date-inputs');
    const startInput = document.getElementById('analysis-start');
    const endInput = document.getElementById('analysis-end');
    const updateBtn = document.getElementById('btn-refresh-analysis');
    const userSelect = document.getElementById('analysis-target-user'); // May not exist if not admin

    // Tabs Logic
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            
            e.target.classList.add('active');
            const target = e.target.dataset.tab;
            document.getElementById(target).classList.add('active');
        });
    });

    // Date Range Logic
    rangeSelect.addEventListener('change', () => {
        if (rangeSelect.value === 'custom') {
            customInputs.classList.remove('hidden');
        } else {
            customInputs.classList.add('hidden');
        }
    });

    updateBtn.addEventListener('click', loadStats);

    // Initial Load
    loadStats();

    function loadStats() {
        const period = rangeSelect.value;
        let queryParams = '?period=' + period;
        
        if (period === 'custom') {
            const s = startInput.value;
            const e = endInput.value;
            if(!s || !e) { OC.dialogs.alert('Please select both start and end dates.', 'Missing Dates'); return; }
            queryParams += '&start=' + s + '&end=' + e;
        }

        // Admin override user selection
        if (userSelect) {
            queryParams += '&target_user=' + userSelect.value;
        } else {
            // Check if global target user exists (from main page context)
            const globalTarget = document.getElementById('global-target-user');
            if(globalTarget) queryParams += '&target_user=' + globalTarget.value;
        }

        // API Call
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
        // 1. Update Stats Cards
        document.getElementById('stat-total-hours').innerText = data.total_hours;
        document.getElementById('stat-reg-hours').innerText = data.stats.regular_hours;
        document.getElementById('stat-pto-hours').innerText = data.stats.pto_hours;
        document.getElementById('stat-overtime-hours').innerText = data.overtime_hours;

        // 2. Update Travel Stats
        document.getElementById('val-total-miles').innerText = data.travel.total_miles;
        document.getElementById('val-per-diem').innerText = data.travel.per_diem_days;
        document.getElementById('val-overnight').innerText = data.travel.overnight_stays;
        document.getElementById('val-expenses').innerText = '$' + data.travel.total_expenses;

        // 3. Render Daily Chart (Overview)
        renderDailyChart(data.trend);

        // 4. Render Job Chart (Breakdown) - Only if data exists
        if (data.jobs && data.jobs.length > 0) {
            renderJobChart(data.jobs);
            renderJobTable(data.jobs, data.total_hours);
        }

        // 5. Render Location Table
        renderLocationTable(data.travel.locations);
    }

    function renderDailyChart(trendData) {
        const ctx = document.getElementById('chart-daily').getContext('2d');
        
        if (dailyChart) dailyChart.destroy();

        // Theme colors (detect dark mode if possible, or use neutral)
        const isDarkMode = document.body.classList.contains('theme--dark');
        const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        const textColor = isDarkMode ? '#ddd' : '#666';

        dailyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: trendData.labels,
                datasets: [{
                    label: 'Hours Worked',
                    data: trendData.values,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { color: textColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    function renderJobChart(jobs) {
        const ctx = document.getElementById('chart-jobs');
        if (!ctx) return; // Element might not exist if permission denied

        if (jobChart) jobChart.destroy();

        const labels = jobs.map(j => j.name);
        const values = jobs.map(j => j.hours);
        const colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
        ];

        jobChart = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { color: '#888' } }
                }
            }
        });
    }

    function renderJobTable(jobs, total) {
        const tbody = document.getElementById('job-table-body');
        if (!tbody) return;
        tbody.innerHTML = '';

        jobs.forEach(j => {
            const pct = total > 0 ? ((j.hours / total) * 100).toFixed(1) : 0;
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${j.name}</td><td>${j.hours}</td><td>${pct}%</td>`;
            tbody.appendChild(tr);
        });
    }

    function renderLocationTable(locs) {
        const tbody = document.getElementById('location-table-body');
        tbody.innerHTML = '';
        locs.forEach(l => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${l.state}</td><td>${l.county}</td><td>${l.visits}</td>`;
            tbody.appendChild(tr);
        });
    }

});