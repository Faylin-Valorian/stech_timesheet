document.addEventListener('DOMContentLoaded', function() {
    
    // --- Global State ---
    let charts = {
        daily: null,
        job: null,
        travelState: null,
        travelCounty: null,
        gauge: null,
        stateMap: null,
        countyMap: null
    };

    let cachedData = null; 
    let usTopology = null; // Stores the local map data

    // --- Elements ---
    const rangeSelect = document.getElementById('range-preset');
    const customInputs = document.getElementById('custom-date-inputs');
    const startInput = document.getElementById('analysis-start');
    const endInput = document.getElementById('analysis-end');
    const updateBtn = document.getElementById('btn-refresh-analysis');
    
    // Search Inputs
    const userSearch = document.getElementById('user-search');
    const userHidden = document.getElementById('analysis-target-user');
    const jobSearch = document.getElementById('job-search');
    const jobHidden = document.getElementById('analysis-job-filter');
    const stateSearch = document.getElementById('state-search');
    const stateHidden = document.getElementById('analysis-state-filter');

    // --- Init ---
    initTabs();
    loadFilters(); 
    loadStats();   

    // --- Tab Logic ---
    function initTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                
                e.target.classList.add('active');
                const target = e.target.dataset.tab;
                document.getElementById(target).classList.add('active');
                
                // Resize maps if needed when tab becomes visible
                if(target === 'tab-state' && charts.stateMap) charts.stateMap.resize();
                if(target === 'tab-county' && charts.countyMap) charts.countyMap.resize();
            });
        });
    }

    // --- Data Loading: Filters ---
    function loadFilters() {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/analysis/filters'), { 
            headers: { 'requesttoken': OC.requestToken } 
        })
        .then(r => r.json())
        .then(data => {
            if (document.getElementById('user-list')) {
                const list = document.getElementById('user-list');
                data.users.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.displayname;
                    opt.setAttribute('data-value', u.uid);
                    list.appendChild(opt);
                });
            }
            if (document.getElementById('job-list')) {
                const list = document.getElementById('job-list');
                data.jobs.forEach(j => {
                    const opt = document.createElement('option');
                    opt.value = j.job_name;
                    opt.setAttribute('data-value', j.job_id);
                    list.appendChild(opt);
                });
            }
            if (document.getElementById('state-list')) {
                const list = document.getElementById('state-list');
                data.states.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.state_name;
                    opt.setAttribute('data-value', s.state_abbr);
                    list.appendChild(opt);
                });
            }
        });
    }

    // --- Event Listeners ---
    rangeSelect.addEventListener('change', () => {
        if (rangeSelect.value === 'custom') customInputs.classList.remove('hidden');
        else { customInputs.classList.add('hidden'); loadStats(); }
    });
    startInput.addEventListener('change', loadStats);
    endInput.addEventListener('change', loadStats);
    if (updateBtn) updateBtn.addEventListener('click', loadStats);

    // User Search
    if (userSearch) {
        userSearch.addEventListener('input', (e) => {
            const val = e.target.value;
            const opts = document.getElementById('user-list').options;
            for (let i = 0; i < opts.length; i++) {
                if (opts[i].value === val) {
                    userHidden.value = opts[i].getAttribute('data-value');
                    loadStats(); 
                    break;
                }
            }
        });
    }

    // Job Search (Local Filter for Gauge)
    if (jobSearch) {
        jobSearch.addEventListener('input', (e) => {
            const val = e.target.value;
            if(val === "") {
                jobHidden.value = 'all';
                if(cachedData) updateGauge(cachedData.jobs, 'All Jobs');
                return;
            }
            const opts = document.getElementById('job-list').options;
            for (let i = 0; i < opts.length; i++) {
                if (opts[i].value === val) {
                    jobHidden.value = opts[i].getAttribute('data-value');
                    if(cachedData) updateGauge(cachedData.jobs, val);
                    break;
                }
            }
        });
    }

    // State Search (Local Filter for County Map)
    if (stateSearch) {
        stateSearch.addEventListener('input', (e) => {
            const val = e.target.value;
            const opts = document.getElementById('state-list').options;
            for (let i = 0; i < opts.length; i++) {
                if (opts[i].value === val) {
                    const abbr = opts[i].getAttribute('data-value');
                    stateHidden.value = abbr;
                    if(cachedData) renderCountyMap(cachedData.counties, abbr);
                    break;
                }
            }
        });
    }

    // --- Main Data Loader ---
    function loadStats() {
        const period = rangeSelect.value;
        let queryParams = '?period=' + period;
        
        if (period === 'custom') {
            const s = startInput.value;
            const e = endInput.value;
            if(!s || !e) return;
            queryParams += '&start=' + s + '&end=' + e;
        }

        if (userHidden) queryParams += '&target_user=' + userHidden.value;
        else queryParams += '&target_user=self';

        const url = OC.generateUrl('/apps/stech_timesheet/api/analysis/stats') + queryParams;
        
        fetch(url, { headers: { 'requesttoken': OC.requestToken, 'OCS-APIRequest': 'true' } })
        .then(r => r.json())
        .then(data => {
            if (data.error) { OC.dialogs.alert(data.error, 'Error'); return; }
            cachedData = data; 
            updateUI(data);
        })
        .catch(err => console.error("Analysis Load Error:", err));
    }

    function updateUI(data) {
        // Cards
        document.getElementById('stat-total-hours').innerText = data.total_hours;
        document.getElementById('stat-reg-hours').innerText = data.stats.regular_hours;
        document.getElementById('stat-pto-hours').innerText = data.stats.pto_hours;
        document.getElementById('stat-overtime-hours').innerText = data.overtime_hours;
        
        // Travel
        document.getElementById('val-total-miles').innerText = data.travel.total_miles;
        document.getElementById('val-per-diem').innerText = data.travel.per_diem_days;
        document.getElementById('val-overnight').innerText = data.travel.overnight_stays;
        document.getElementById('val-expenses').innerText = '$' + data.travel.total_expenses;

        // Render Basic Charts
        renderOverviewChart(data.trend);
        renderTravelCharts(data.states, data.counties); 
        
        if (document.getElementById('chart-jobs')) {
            renderJobCharts(data.jobs, data.total_hours);
        }

        // Render Profitability Gauge
        const currentJobFilter = jobSearch ? jobSearch.value : 'All Jobs';
        if (document.getElementById('chart-profitability-gauge')) {
            updateGauge(data.jobs, currentJobFilter);
        }

        // Render Maps (Local Loading)
        if(!usTopology) {
            // [FIX] Load from LOCAL app folder to satisfy CSP
            fetch(OC.generateUrl('/apps/stech_timesheet/js/us-atlas.json'))
                .then(r => r.json())
                .then(topo => {
                    usTopology = topo;
                    renderStateMap(data.states);
                    
                    // Render County if State selected
                    if(stateSearch && stateSearch.value) {
                         const opts = document.getElementById('state-list').options;
                         let abbr = '';
                         for(let i=0; i<opts.length; i++) {
                             if(opts[i].value === stateSearch.value) abbr = opts[i].getAttribute('data-value');
                         }
                         renderCountyMap(data.counties, abbr);
                    }
                })
                .catch(e => console.error("Failed to load local US Map Topology:", e));
        } else {
            renderStateMap(data.states);
            if(stateSearch && stateSearch.value) {
                 const opts = document.getElementById('state-list').options;
                 let abbr = '';
                 for(let i=0; i<opts.length; i++) {
                     if(opts[i].value === stateSearch.value) abbr = opts[i].getAttribute('data-value');
                 }
                 renderCountyMap(data.counties, abbr);
            }
        }
    }

    function getTheme() {
        const isDark = document.body.classList.contains('theme--dark');
        return { 
            text: isDark ? '#ddd' : '#666',
            grid: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)'
        };
    }

    // =========================================================
    //  4. CHART RENDERERS (BASIC)
    // =========================================================

    // --- A. Overview Line Chart ---
    function renderOverviewChart(trend) {
        const ctx = document.getElementById('chart-daily').getContext('2d');
        if (charts.daily) charts.daily.destroy();
        const theme = getTheme();

        charts.daily = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [{
                    label: 'Hours Worked',
                    data: trend.values,
                    borderColor: '#0082c9',
                    backgroundColor: 'rgba(0, 130, 201, 0.2)',
                    fill: true,
                    tension: 0.3, // Curve the line
                    pointRadius: 3,
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

    // --- B. Travel Distribution (Mini Doughnuts) ---
    function renderTravelCharts(states, counties) {
        // State Pie
        const ctxS = document.getElementById('chart-travel-state');
        if (ctxS) {
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
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { color: getTheme().text, boxWidth: 10 } } }
                }
            });
        }
        // County Pie
        const ctxC = document.getElementById('chart-travel-county');
        if (ctxC) {
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
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { color: getTheme().text, boxWidth: 10 } } }
                }
            });
        }
    }

    // --- C. Job Breakdown (Donut + Table) ---
    function renderJobCharts(jobs, total) {
        const ctx = document.getElementById('chart-jobs');
        if (charts.job) charts.job.destroy();
        const theme = getTheme();

        charts.job = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: jobs.map(j => j.name),
                datasets: [{
                    data: jobs.map(j => j.hours),
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40']
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { color: theme.text } } }
            }
        });

        // Update Table
        const tbody = document.getElementById('job-table-body');
        tbody.innerHTML = '';
        if(jobs.length === 0) tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">No data available</td></tr>';
        
        jobs.forEach(j => {
            const pct = total > 0 ? ((j.hours / total) * 100).toFixed(1) : 0;
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${j.name}</td><td>${j.hours.toFixed(2)}</td><td>${pct}%</td>`;
            tbody.appendChild(tr);
        });
    }

    // =========================================================
    //  5. ADVANCED RENDERERS (GAUGE & MAPS)
    // =========================================================

    // --- D. Profitability Gauge (Reversed: Green -> Red) ---
    function updateGauge(jobs, filterName) {
        const ctx = document.getElementById('chart-profitability-gauge');
        if (!ctx) return;
        
        let currentValue = 0;
        let budgetLimit = 0;
        let label = "";

        // 1. Calculate Metric
        if (filterName === 'All Jobs' || filterName === 'all' || filterName === '') {
            // Overview: Sum of all hours vs Arbitrary Baseline (e.g. 160h)
            currentValue = jobs.reduce((acc, curr) => acc + curr.hours, 0);
            budgetLimit = 160 * (jobs.length > 0 ? 1 : 0); 
            label = currentValue.toFixed(1) + " Total Hrs";
        } else {
            // Specific Job
            const job = jobs.find(x => x.name === filterName);
            if (job) {
                // Financial Mode?
                if (job.hourly_cost > 0 && job.budget > 0) {
                    currentValue = job.hours * job.hourly_cost;
                    budgetLimit = job.budget;
                    label = "$" + currentValue.toFixed(2) + " / $" + budgetLimit.toFixed(2);
                } else {
                    // Hours Mode (Fallback)
                    currentValue = job.hours;
                    budgetLimit = (job.budget > 0) ? job.budget : 80; 
                    label = currentValue.toFixed(1) + " Hrs";
                }
            }
        }

        if (budgetLimit <= 0) budgetLimit = Math.max(currentValue * 1.5, 100);

        document.getElementById('gauge-value-display').innerText = label;

        if (charts.gauge) charts.gauge.destroy();

        // Custom Needle Logic
        const needlePlugin = {
            id: 'needle',
            afterDatasetDraw(chart) {
                const { ctx, chartArea: { width, height } } = chart;
                ctx.save();
                
                // Calculate Ratio
                let ratio = currentValue / budgetLimit;
                if (ratio > 1) ratio = 1; 
                
                // Reversed Logic: Green is Left (0), Red is Right (1)
                // Angle range: PI (Left) to 2*PI (Right)
                const angle = Math.PI + (ratio * Math.PI);
                
                const cx = width / 2;
                const cy = chart._metasets[0].data[0].y;

                // Draw Needle
                ctx.translate(cx, cy);
                ctx.rotate(angle);
                ctx.beginPath();
                ctx.moveTo(0, -2);
                ctx.lineTo(height - (ctx.canvas.offsetTop + 40), 0);
                ctx.lineTo(0, 2);
                ctx.fillStyle = getTheme().text;
                ctx.fill();

                // Draw Pivot
                ctx.rotate(-angle);
                ctx.beginPath();
                ctx.arc(0, 0, 5, 0, 10);
                ctx.fillStyle = getTheme().text;
                ctx.fill();
                ctx.restore();
            }
        };

        charts.gauge = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Profitable', 'Warning', 'Over Budget'],
                datasets: [{
                    // Sections: Green (Left/Start) -> Red (Right/End)
                    // Green: 0% to 60% of budget
                    // Yellow: 60% to 85%
                    // Red: 85% to 100%
                    data: [budgetLimit * 0.6, budgetLimit * 0.25, budgetLimit * 0.15],
                    backgroundColor: ['#46ba6f', '#ffd60a', '#e9322d'], 
                    borderWidth: 0,
                    needleValue: currentValue
                }]
            },
            options: {
                rotation: -90,
                circumference: 180,
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            },
            plugins: [needlePlugin]
        });
    }

    // --- E. Maps (US & County Hotbeds) ---
    function renderStateMap(stateData) {
        // [Safety Check] - Prevent crash if libs missing
        if (typeof ChartGeo === 'undefined') {
            console.warn('ChartGeo/TopoJSON not loaded. Skipping maps.');
            return;
        }

        const ctx = document.getElementById('chart-state-map').getContext('2d');
        if (charts.stateMap) charts.stateMap.destroy();

        const states = ChartGeo.topojson.feature(usTopology, usTopology.objects.states).features;
        
        // Map Data by Name
        const data = states.map(d => ({
            feature: d,
            value: stateData[d.properties.name] || 0
        }));

        charts.stateMap = new Chart(ctx, {
            type: 'choropleth',
            data: {
                labels: states.map(d => d.properties.name),
                datasets: [{
                    label: 'Visits',
                    data: data,
                    backgroundColor: (ctx) => {
                        const v = ctx.raw ? ctx.raw.value : 0;
                        if (v === 0) return '#eee';
                        // Blue Scale (Light -> Dark)
                        return `rgba(0, 130, 201, ${Math.min(0.2 + (v / 10), 1)})`;
                    },
                }]
            },
            options: {
                showOutline: true,
                showGraticule: false,
                plugins: { legend: { display: false } },
                scales: { xy: { projection: 'albersUsa' } }
            }
        });
    }

    function renderCountyMap(countyData, stateAbbr) {
        if (typeof ChartGeo === 'undefined') return;

        document.getElementById('county-map-placeholder').style.display = 'none';
        
        const ctx = document.getElementById('chart-county-map').getContext('2d');
        if (charts.countyMap) charts.countyMap.destroy();

        const counties = ChartGeo.topojson.feature(usTopology, usTopology.objects.counties).features;
        
        // Data Matching Logic: "StateName|CountyName"
        const data = counties.map(d => {
            const countyName = d.properties.name;
            let val = 0;
            
            // Check if this county name exists in our filtered data
            for (const [key, visits] of Object.entries(countyData)) {
                // key format: "Texas|Harris"
                if (key.includes('|' + countyName)) {
                    val = visits;
                    break;
                }
            }
            return { feature: d, value: val };
        });

        charts.countyMap = new Chart(ctx, {
            type: 'choropleth',
            data: {
                labels: counties.map(d => d.properties.name),
                datasets: [{
                    label: 'Visits',
                    data: data,
                    backgroundColor: (ctx) => {
                        const v = ctx.raw ? ctx.raw.value : 0;
                        // Red Scale (Light -> Dark)
                        return v > 0 ? `rgba(233, 50, 45, ${Math.min(0.4 + (v / 5), 1)})` : '#eee'; 
                    }
                }]
            },
            options: {
                showOutline: true,
                plugins: { legend: { display: false } },
                scales: { xy: { projection: 'albersUsa' } } 
            }
        });
    }

});