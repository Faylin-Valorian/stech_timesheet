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
    let usTopology = null; 

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
                
                if(target === 'tab-state' && charts.stateMap) charts.stateMap.resize();
                if(target === 'tab-county' && charts.countyMap) charts.countyMap.resize();
            });
        });
    }

    // --- Data Loading: Filters ---
    function loadFilters() {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/analysis/filters'), { headers: { 'requesttoken': OC.requestToken } })
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

        // Render Gauge
        const currentJobFilter = jobSearch ? jobSearch.value : 'All Jobs';
        if (document.getElementById('chart-profitability-gauge')) {
            updateGauge(data.jobs, currentJobFilter);
        }

        // Render Maps (Local Loading FIXED)
        if(!usTopology) {
            // [FIX] Direct static path without index.php routing
            const mapUrl = OC.webroot + '/apps/stech_timesheet/js/us-atlas.json';
            
            fetch(mapUrl)
                .then(r => {
                    if(!r.ok) throw new Error("HTTP " + r.status + " - " + r.statusText);
                    return r.json();
                })
                .then(topo => {
                    usTopology = topo;
                    renderStateMap(data.states);
                    
                    if(stateSearch && stateSearch.value) {
                         const opts = document.getElementById('state-list').options;
                         let abbr = '';
                         for(let i=0; i<opts.length; i++) {
                             if(opts[i].value === stateSearch.value) abbr = opts[i].getAttribute('data-value');
                         }
                         renderCountyMap(data.counties, abbr);
                    }
                })
                .catch(e => {
                    console.error("Failed to load local US Map:", e);
                    // Provide visual feedback in the map container
                    const mapContainer = document.getElementById('chart-state-map').parentNode;
                    mapContainer.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#888;">Map Data Not Found.<br>Please ensure js/us-atlas.json exists.</div>';
                });
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
    //  4. CHART RENDERERS
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
                    tension: 0.3, 
                    pointRadius: 3,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text } },
                    x: { grid: { display: false }, ticks: { color: theme.text } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // --- B. Travel Distribution ---
    function renderTravelCharts(states, counties) {
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

    // --- C. Job Breakdown ---
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

    // --- D. Profitability Gauge (Net Profit: Red -> Green) ---
    function updateGauge(jobs, filterName) {
        const ctx = document.getElementById('chart-profitability-gauge');
        if (!ctx) return;
        
        let revenue = 0;
        let expenses = 0; 
        let laborCost = 0;
        let profit = 0;
        let label = "";
        
        // 1. Calculate Financials
        if (filterName === 'All Jobs' || filterName === 'all' || filterName === '') {
            jobs.forEach(j => {
                revenue += j.revenue;
                expenses += j.budget; 
                laborCost += (j.hours * j.hourly_cost);
            });
            profit = revenue - expenses - laborCost;
            label = "$" + profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " Net Profit";
        } else {
            const job = jobs.find(x => x.name === filterName);
            if (job) {
                revenue = job.revenue;
                expenses = job.budget;
                laborCost = job.hours * job.hourly_cost;
                profit = revenue - expenses - laborCost;
                label = "$" + profit.toLocaleString() + " Net Profit";
            }
        }

        // 2. Set Gauge Visual Scale (Max Revenue or Baseline)
        // If Revenue is 0 (non-billable), set a baseline so the chart isn't empty
        let scaleMax = revenue > 0 ? revenue : (Math.abs(profit) > 0 ? Math.abs(profit) * 1.5 : 1000);

        // 3. Update Text Display
        const displayEl = document.getElementById('gauge-value-display');
        displayEl.innerHTML = `
            <div style="text-align:center">
                <div style="font-size:24px; font-weight:bold; color:${profit >= 0 ? '#2ecc71' : '#e74c3c'}">${label}</div>
                <div style="font-size:12px; color:#888; margin-top:5px;">
                    Rev: $${revenue.toLocaleString()} | Exp: $${expenses.toLocaleString()} | Labor: $${laborCost.toLocaleString()}
                </div>
            </div>
        `;

        if (charts.gauge) charts.gauge.destroy();

        // 4. Custom Needle Logic
        const needlePlugin = {
            id: 'needle',
            afterDatasetDraw(chart) {
                const { ctx, chartArea: { width, height } } = chart;
                ctx.save();
                
                // --- Needle Position Logic ---
                // Left (Red) = Loss or 0 Profit
                // Middle (Yellow) = Break-even to low margin
                // Right (Green) = High Margin
                
                let ratio = 0;
                
                if (revenue > 0) {
                    // Margin % Calculation
                    const margin = profit / revenue; // e.g., 0.20 for 20%
                    
                    if(margin < 0) {
                        // Loss: Map -100% to 0% range -> Gauge 0.0 to 0.33 (Red Section)
                        // Clamp loss at -50% for visual sanity
                        let lossSeverity = Math.min(Math.abs(margin), 0.5) / 0.5; // 0 to 1 scale of "badness"
                        ratio = 0.33 - (lossSeverity * 0.33); 
                    } else {
                        // Profit: Map 0% to 50% range -> Gauge 0.33 to 1.0 (Yellow/Green)
                        // 0% Margin = 0.33
                        // 50%+ Margin = 1.0
                        let success = Math.min(margin, 0.5) / 0.5; 
                        ratio = 0.33 + (success * 0.67);
                    }
                } else {
                    // No revenue? If profit is negative (costs only), point Red.
                    ratio = profit < 0 ? 0.1 : 0.5;
                }

                // Clamp
                if (ratio < 0) ratio = 0;
                if (ratio > 1) ratio = 1;

                // Convert to Angle (PI to 2PI)
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
                labels: ['Loss', 'Break Even', 'Profitable'],
                datasets: [{
                    // Sections: Red (Left), Yellow (Middle), Green (Right)
                    data: [33, 33, 34], 
                    backgroundColor: ['#e9322d', '#ffd60a', '#46ba6f'], 
                    borderWidth: 0
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

    // =========================================================
    //  5. MAP RENDERERS (US & COUNTY)
    // =========================================================

    function renderStateMap(stateData) {
        // [Safety Check] - Prevent crash if libs missing or data not loaded
        if (typeof ChartGeo === 'undefined' || !usTopology) {
            console.warn('Map dependencies missing or Topology not loaded.');
            return;
        }

        const ctx = document.getElementById('chart-state-map').getContext('2d');
        if (charts.stateMap) charts.stateMap.destroy();

        // extract states features from TopoJSON
        const states = ChartGeo.topojson.feature(usTopology, usTopology.objects.states).features;
        
        // Map DB Data (Key: State Name) to Map Features (Feature.properties.name)
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
                        if (v === 0) return '#eee'; // Grey for no data
                        // Blue Scale (Light -> Dark)
                        return `rgba(0, 130, 201, ${Math.min(0.2 + (v / 10), 1)})`;
                    },
                    borderColor: '#999',
                    borderWidth: 0.5
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
        // [Safety Check]
        if (typeof ChartGeo === 'undefined' || !usTopology) return;

        // Hide placeholder text now that we are rendering
        const placeholder = document.getElementById('county-map-placeholder');
        if (placeholder) placeholder.style.display = 'none';
        
        const ctx = document.getElementById('chart-county-map').getContext('2d');
        if (charts.countyMap) charts.countyMap.destroy();

        const counties = ChartGeo.topojson.feature(usTopology, usTopology.objects.counties).features;
        
        // Data Matching Logic: 
        // DB Key format: "StateName|CountyName" (e.g., "Texas|Harris")
        // Map Feature Name: "Harris"
        // We match if the DB Key *contains* the county name.
        
        const data = counties.map(d => {
            const countyName = d.properties.name;
            let val = 0;
            
            // Loop through our DB data to find a match for this county
            for (const [key, visits] of Object.entries(countyData)) {
                // key format: "Texas|Harris"
                // Check if key contains "|CountyName" to ensure we match the county part
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
                    },
                    borderColor: '#ddd',
                    borderWidth: 0.2
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