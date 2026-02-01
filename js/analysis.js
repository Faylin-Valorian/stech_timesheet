document.addEventListener('DOMContentLoaded', function() {
    
    // --- State ---
    let charts = {}; 
    let currentData = null; // Store fetched data
    let currentView = 'dashboard';

    // --- Navigation ---
    const navDashboard = document.getElementById('nav-dashboard');
    const navJobs = document.getElementById('nav-jobs');
    const viewDashboard = document.getElementById('view-dashboard');
    const viewJobs = document.getElementById('view-jobs');

    function switchView(viewName) {
        currentView = viewName;
        viewDashboard.classList.add('hidden');
        viewJobs.classList.add('hidden');
        navDashboard.classList.remove('active');
        navJobs.classList.remove('active');

        if(viewName === 'dashboard') {
            viewDashboard.classList.remove('hidden');
            navDashboard.classList.add('active');
        } else {
            viewJobs.classList.remove('hidden');
            navJobs.classList.add('active');
        }
        
        // Render charts for the active view ONLY
        if(currentData) renderVisibleCharts();
    }

    navDashboard.addEventListener('click', () => switchView('dashboard'));
    navJobs.addEventListener('click', () => switchView('jobs'));

    // --- Data Fetching ---
    function fetchData() {
        if (typeof Chart === 'undefined') {
            alert("Error: Chart.js library not loaded. Check internet connection or CSP settings.");
            return;
        }

        const period = document.getElementById('period-selector').value;
        const userSelect = document.getElementById('user-selector');
        const targetUid = userSelect ? userSelect.value : 'self';

        const url = OC.generateUrl('/apps/stech_timesheet/api/analysis/stats') + 
                    `?period=${period}&target_uid=${targetUid}`;

        // Show loading state (optional)
        document.getElementById('metric-total-hours').innerText = '...';

        fetch(url, { headers: { 'requesttoken': OC.requestToken } })
            .then(r => r.json())
            .then(data => {
                currentData = data;
                updateMetrics(data);
                renderVisibleCharts();
            })
            .catch(err => {
                console.error("Analysis Error:", err);
                alert("Failed to load analysis data.");
            });
    }

    // --- Admin: Load User List ---
    if(document.getElementById('user-selector')) {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users'), { headers: { 'requesttoken': OC.requestToken } })
            .then(r => r.json())
            .then(users => {
                const sel = document.getElementById('user-selector');
                users.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.uid;
                    opt.innerText = u.displayname;
                    sel.appendChild(opt);
                });
            });
    }

    document.getElementById('btn-refresh').addEventListener('click', fetchData);
    document.getElementById('period-selector').addEventListener('change', fetchData);
    if(document.getElementById('user-selector')) {
        document.getElementById('user-selector').addEventListener('change', fetchData);
    }

    // --- Rendering ---
    function updateMetrics(data) {
        document.getElementById('metric-total-hours').innerText = data.total_hours.toFixed(2);
        document.getElementById('metric-days-worked').innerText = data.days_worked;
        document.getElementById('metric-overtime').innerText = data.overtime_hours.toFixed(2);
    }

    function renderVisibleCharts() {
        if(!currentData) return;

        if (currentView === 'dashboard') {
            renderTrendChart(currentData);
            renderTopJobsChart(currentData);
        } else if (currentView === 'jobs') {
            renderDetailedJobsChart(currentData);
        }
    }

    function renderTrendChart(data) {
        const ctx = document.getElementById('chart-trend');
        if(!ctx) return;
        
        destroyChart('chart-trend');

        charts['chart-trend'] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.trend.labels,
                datasets: [{
                    label: 'Hours Worked',
                    data: data.trend.values,
                    borderColor: '#0082c9',
                    backgroundColor: 'rgba(0, 130, 201, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    function renderTopJobsChart(data) {
        const ctx = document.getElementById('chart-jobs-simple');
        if(!ctx) return;
        
        destroyChart('chart-jobs-simple');

        // Handle empty data
        if (!data.jobs || data.jobs.length === 0) {
            // Optional: Draw a "No Data" placeholder or just return
            return;
        }

        const jobLabels = data.jobs.map(j => j.name).slice(0, 5);
        const jobValues = data.jobs.map(j => j.hours).slice(0, 5);

        charts['chart-jobs-simple'] = new Chart(ctx, {
            type: 'doughnut', // Changed to Doughnut for better look
            data: {
                labels: jobLabels,
                datasets: [{
                    data: jobValues,
                    backgroundColor: ['#0082c9', '#46ba6f', '#ffd60a', '#d9534f', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    function renderDetailedJobsChart(data) {
        const ctx = document.getElementById('chart-jobs-detailed');
        if(!ctx) return;

        destroyChart('chart-jobs-detailed');

        const names = data.jobs.map(j => j.name);
        const hours = data.jobs.map(j => j.hours);

        charts['chart-jobs-detailed'] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: names,
                datasets: [{
                    label: 'Total Hours',
                    data: hours,
                    backgroundColor: '#46ba6f',
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y', // Horizontal Bar
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    function destroyChart(id) {
        if(charts[id]) {
            charts[id].destroy();
            charts[id] = null;
        }
    }

    // Initial Load
    fetchData();
});