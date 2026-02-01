document.addEventListener('DOMContentLoaded', function() {
    
    // --- State ---
    let charts = {}; // Store Chart instances to destroy/update them
    
    // --- Navigation ---
    const navDashboard = document.getElementById('nav-dashboard');
    const navJobs = document.getElementById('nav-jobs');
    const viewDashboard = document.getElementById('view-dashboard');
    const viewJobs = document.getElementById('view-jobs');

    function switchView(viewName) {
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
    }

    navDashboard.addEventListener('click', () => switchView('dashboard'));
    navJobs.addEventListener('click', () => switchView('jobs'));

    // --- Data Fetching ---
    function fetchData() {
        const period = document.getElementById('period-selector').value;
        const userSelect = document.getElementById('user-selector');
        const targetUid = userSelect ? userSelect.value : 'self';

        const url = OC.generateUrl('/apps/stech_timesheet/api/analysis/stats') + 
                    `?period=${period}&target_uid=${targetUid}`;

        fetch(url, { headers: { 'requesttoken': OC.requestToken } })
            .then(r => r.json())
            .then(data => {
                updateMetrics(data);
                renderCharts(data);
            })
            .catch(err => console.error("Analysis Error:", err));
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

    function renderCharts(data) {
        // 1. Trend Chart (Line)
        renderChart('chart-trend', 'line', {
            labels: data.trend.labels,
            datasets: [{
                label: 'Hours Worked',
                data: data.trend.values,
                borderColor: '#0082c9',
                backgroundColor: 'rgba(0, 130, 201, 0.1)',
                fill: true,
                tension: 0.3
            }]
        });

        // 2. Work vs Leave (Doughnut)
        renderChart('chart-leave', 'doughnut', {
            labels: ['Regular Work', 'Vacation/PTO'],
            datasets: [{
                data: [data.stats.regular_hours, data.stats.pto_hours],
                backgroundColor: ['#0082c9', '#d9534f']
            }]
        });

        // 3. Jobs Simple (Pie)
        const jobLabels = data.jobs.map(j => j.name).slice(0, 5);
        const jobValues = data.jobs.map(j => j.hours).slice(0, 5);
        renderChart('chart-jobs-simple', 'pie', {
            labels: jobLabels,
            datasets: [{
                data: jobValues,
                backgroundColor: ['#0082c9', '#46ba6f', '#ffd60a', '#d9534f', '#6c757d']
            }]
        });

        // 4. Jobs Detailed (Bar)
        renderChart('chart-jobs-detailed', 'bar', {
            labels: data.jobs.map(j => j.name),
            datasets: [{
                label: 'Hours',
                data: data.jobs.map(j => j.hours),
                backgroundColor: '#46ba6f'
            }]
        }, { indexAxis: 'y' }); // Horizontal Bar
    }

    function renderChart(canvasId, type, data, options = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        if(charts[canvasId]) {
            charts[canvasId].destroy();
        }

        charts[canvasId] = new Chart(ctx, {
            type: type,
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                ...options
            }
        });
    }

    // Initial Load
    fetchData();
});