document.addEventListener('DOMContentLoaded', function() {
    
    // --- State & Chart Instances ---
    let charts = {
        daily: null,
        job: null,
        travelState: null,
        travelCounty: null,
        gauge: null,
        stateMap: null,
        countyMap: null
    };

    let cachedData = null; // Store data to allow frontend filtering (Gauge/Maps) without re-fetching
    let usTopology = null; // Cache US map data

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

    // --- Initialization ---
    initTabs();
    loadFilters(); // Populate Dropdowns
    loadStats();   // Load Initial Data

    // --- Tab Logic ---
    function initTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                e.target.classList.add('active');
                const target = e.target.dataset.tab;
                document.getElementById(target).classList.add('active');
                
                // Resize charts if needed when tab becomes visible
                if(target === 'tab-state' && charts.stateMap) charts.stateMap.resize();
                if(target === 'tab-county' && charts.countyMap) charts.countyMap.resize();
            });
        });
    }

    // --- 1. Populate Searchable Dropdowns ---
    function loadFilters() {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/analysis/filters'), {
            headers: { 'requesttoken': OC.requestToken }
        })
        .then(r => r.json())
        .then(data => {
            // Users
            if (document.getElementById('user-list')) {
                const list = document.getElementById('user-list');
                data.users.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.displayname;
                    opt.setAttribute('data-value', u.uid);
                    list.appendChild(opt);
                });
            }
            // Jobs
            if (document.getElementById('job-list')) {
                const list = document.getElementById('job-list');
                data.jobs.forEach(j => {
                    const opt = document.createElement('option');
                    opt.value = j.job_name; // Search by Name
                    opt.setAttribute('data-value', j.job_id); // ID reference
                    list.appendChild(opt);
                });
            }
            // States
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

    // --- 2. Event Listeners ---

    // Period
    rangeSelect.addEventListener('change', () => {
        if (rangeSelect.value === 'custom') customInputs.classList.remove('hidden');
        else { customInputs.classList.add('hidden'); loadStats(); }
    });
    startInput.addEventListener('change', loadStats);
    endInput.addEventListener('change', loadStats);
    if (updateBtn) updateBtn.addEventListener('click', loadStats);

    // Search Input Handler (User)
    if (userSearch) {
        userSearch.addEventListener('input', (e) => {
            const val = e.target.value;
            const opts = document.getElementById('user-list').options;
            for (let i = 0; i < opts.length; i++) {
                if (opts[i].value === val) {
                    userHidden.value = opts[i].getAttribute('data-value');
                    loadStats(); // Re-fetch data for new user
                    break;
                }
            }
        });
    }

    // Search Input Handler (Job - Frontend Filter)
    if (jobSearch) {
        jobSearch.addEventListener('input', (e) => {
            const val = e.target.value;
            const opts = document.getElementById('job-list').options;
            let found = false;
            
            if(val === "") { jobHidden.value = 'all'; updateGauge(cachedData.jobs); return; }

            for (let i = 0; i < opts.length; i++) {
                if (opts[i].value === val) {
                    jobHidden.value = opts[i].getAttribute('data-value'); // ID or Name
                    found = true;
                    // Update Gauge Only (No fetch needed)
                    if(cachedData) updateGauge(cachedData.jobs, opts[i].value);
                    break;
                }
            }
        });
    }

    // Search Input Handler (State - Map Filter)
    if (stateSearch) {
        stateSearch.addEventListener('input', (e) => {
            const val = e.target.value;
            const opts = document.getElementById('state-list').options;
            for (let i = 0; i < opts.length; i++) {
                if (opts[i].value === val) {
                    stateHidden.value = opts[i].getAttribute('data-value'); // ABBR
                    // Load County Map for this state
                    if(cachedData) renderCountyMap(cachedData.counties, opts[i].value);
                    break;
                }
            }
        });
    }


    // --- 3. Main Data Loader ---
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
            cachedData = data; // Cache for sub-filters
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

        // Charts
        renderOverviewChart(data.trend);
        renderTravelCharts(data.states, data.counties); 
        
        if (document.getElementById('chart-jobs')) renderJobCharts(data.jobs, data.total_hours);
        if (document.getElementById('chart-profitability-gauge')) updateGauge(data.jobs, jobSearch ? jobSearch.value : 'All Jobs');
        
        // Maps
        // We load US topology once
        if(!usTopology) {
            fetch('https://unpkg.com/us-atlas/counties-10m.json')
                .then(r => r.json())
                .then(topo => {
                    usTopology = topo;
                    renderStateMap(data.states);
                    // If a state is already selected, render county
                    if(stateHidden && stateHidden.value) renderCountyMap(data.counties, stateHidden.value);
                });
        } else {
            renderStateMap(data.states);
            if(stateHidden && stateHidden.value) renderCountyMap(data.counties, stateHidden.value);
        }
    }

    // --- 4. Chart Renderers ---

    function getTheme() {
        const isDark = document.body.classList.contains('theme--dark');
        return { 
            text: isDark ? '#ddd' : '#666',
            grid: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)'
        };
    }

    function renderOverviewChart(trend) {
        const ctx = document.getElementById('chart-daily').getContext('2d');
        if (charts.daily) charts.daily.destroy();
        charts.daily = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [{
                    label: 'Hours', data: trend.values, borderColor: '#0082c9', backgroundColor: 'rgba(0,130,201,0.2)', fill: true, tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { grid: { display:false }, ticks:{ color:getTheme().text } }, y: { grid:{ color:getTheme().grid }, ticks:{ color:getTheme().text } } } }
        });
    }

    function renderTravelCharts(states, counties) {
        // State Pie
        const ctxS = document.getElementById('chart-travel-state');
        if(ctxS) {
            if(charts.travelState) charts.travelState.destroy();
            charts.travelState = new Chart(ctxS, {
                type: 'doughnut', data: { labels: Object.keys(states), datasets: [{ data: Object.values(states), backgroundColor: ['#36A2EB','#FF6384','#FFCE56','#4BC0C0'] }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position:'right', labels:{ color:getTheme().text, boxWidth:10 } } } }
            });
        }
        // County Pie
        const ctxC = document.getElementById('chart-travel-county');
        if(ctxC) {
            if(charts.travelCounty) charts.travelCounty.destroy();
            charts.travelCounty = new Chart(ctxC, {
                type: 'doughnut', data: { labels: Object.keys(counties), datasets: [{ data: Object.values(counties), backgroundColor: ['#FF9F40','#9966FF','#4BC0C0','#36A2EB'] }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position:'right', labels:{ color:getTheme().text, boxWidth:10 } } } }
            });
        }
    }

    function renderJobCharts(jobs, total) {
        const ctx = document.getElementById('chart-jobs');
        if(charts.job) charts.job.destroy();
        charts.job = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: jobs.map(j=>j.name), datasets: [{ data: jobs.map(j=>j.hours), backgroundColor: ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position:'right', labels:{ color:getTheme().text } } } }
        });
        
        const tbody = document.getElementById('job-table-body');
        tbody.innerHTML = '';
        jobs.forEach(j => {
            const pct = total > 0 ? ((j.hours/total)*100).toFixed(1) : 0;
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${j.name}</td><td>${j.hours}</td><td>${pct}%</td>`;
            tbody.appendChild(tr);
        });
    }

    // --- 5. Custom Gauge Logic ---
    function updateGauge(jobs, filterName = 'All Jobs') {
        const ctx = document.getElementById('chart-profitability-gauge');
        if(!ctx) return;
        
        let value = 0;
        let max = 160; // Default budget baseline (4 weeks * 40h) - adjustable logic
        
        // Calculate Total or Specific
        if (filterName === 'All Jobs' || filterName === '') {
            value = jobs.reduce((acc, curr) => acc + curr.hours, 0);
            max = Math.max(value * 1.2, 160); // Dynamic scale for total
        } else {
            const j = jobs.find(x => x.name === filterName);
            value = j ? j.hours : 0;
            // Dynamic budget logic: could come from DB, here we assume 80h buffer or dynamic
            max = Math.max(value * 1.5, 80); 
        }

        // Update Text
        document.getElementById('gauge-value-display').innerText = value.toFixed(1) + " Hrs";

        if(charts.gauge) charts.gauge.destroy();

        // Needle Plugin
        const needlePlugin = {
            id: 'needle',
            afterDatasetDraw(chart, args, options) {
                const { ctx, config, data, chartArea: { top, bottom, left, right, width, height } } = chart;
                ctx.save();
                
                // Calculate Angle
                const needleValue = value;
                const dataTotal = max; 
                const angle = Math.PI + (1 / dataTotal * needleValue * Math.PI);
                
                const cx = width / 2;
                const cy = chart._metasets[0].data[0].y;
                
                // Draw Needle
                ctx.translate(cx, cy);
                ctx.rotate(angle);
                ctx.beginPath();
                ctx.moveTo(0, -2);
                ctx.lineTo(height - (ctx.canvas.offsetTop + 40), 0); // Length based on radius
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
                labels: ['Good', 'Warning', 'Danger'],
                datasets: [{
                    data: [max*0.6, max*0.25, max*0.15], // 60% Green, 25% Yellow, 15% Red
                    backgroundColor: ['#46ba6f', '#ffd60a', '#e9322d'],
                    borderWidth: 0,
                    needleValue: value
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

    // --- 6. Map Logic (ChartGeo) ---
    function renderStateMap(stateData) {
        const ctx = document.getElementById('chart-state-map').getContext('2d');
        if(charts.stateMap) charts.stateMap.destroy();

        // Convert data { 'TX': 10 } to array for ChartGeo
        // TopoJSON uses State Names mostly, we need to map ABBR -> Name if needed or match features
        // usTopology.objects.states
        const states = ChartGeo.topojson.feature(usTopology, usTopology.objects.states).features;
        
        // Map Data to Features
        // Note: This relies on Name matching. State data from DB needs to match TopoJSON names.
        // Assuming DB has Full Names "Texas". If Abbr, we need a lookup.
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
                        // Color Scale: Light Blue to Dark Blue
                        const v = ctx.raw ? ctx.raw.value : 0;
                        if(v === 0) return '#eee';
                        const opacity = Math.min(0.3 + (v / 20), 1); // Dynamic Opacity
                        return `rgba(0, 130, 201, ${opacity})`; 
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
        // Hide placeholder
        document.getElementById('county-map-placeholder').style.display = 'none';
        
        const ctx = document.getElementById('chart-county-map').getContext('2d');
        if(charts.countyMap) charts.countyMap.destroy();

        // Filter Counties by State
        // US Atlas Counties have IDs starting with State FIPS. 
        // We need to map State Abbr to FIPS (Simple lookup or filter by geometry if using geojson)
        // Simplified: Filter features where ID starts with State ID.
        // Need State FIPS lookup. For now, we will render ALL counties but only highlight the ones in the list.
        // Better: Zoom to state. 'albersUsa' doesn't zoom easily.
        // Alternative: Just render, data will highlight the hot spots.
        
        const counties = ChartGeo.topojson.feature(usTopology, usTopology.objects.counties).features;
        
        // Map DB Data "State|County" -> TopoJSON Name match
        // DB Key: "Texas|Harris"
        // TopoJSON: properties.name = "Harris"
        // We need to be careful about duplicate county names (Orange County CA vs FL).
        // Strict matching is complex without FIPS codes in DB.
        // Approximation: Match Name AND ensure parent state matches (requires hierarchy lookup).
        
        // VISUAL SHORTCUT: Just map raw county names from the filtered subset if possible.
        // Since we only passed data for ONE state (via stateSearch filter in JS logic?), 
        // countyData keys should just be "CountyName" or "State|County".
        
        // Let's rely on the passed countyData which is keyed by "State|County".
        // We map features.
        const data = counties.map(d => {
            // Find if this county exists in our data
            // We need the State Name of this county feature to construct the key.
            // us-atlas doesn't give State Name in county properties easily.
            // Fallback: Check if ANY key in countyData ends with "|"+d.properties.name
            // This might cause collisions but is best without FIPS.
            
            const name = d.properties.name;
            let val = 0;
            for (const [k, v] of Object.entries(countyData)) {
                // k = "Texas|Harris"
                // Check if k starts with SelectedState and ends with CountyName
                const [s, c] = k.split('|'); // Assuming "State Name|County Name"
                // Match State Name from Dropdown (stateSearch.value is State Name usually)
                // If stateAbbr passed is Abbr, we need name.
                // Assuming visual matching:
                if (c === name) {
                     // Refine: Check if state matches. 
                     // Hard without FIPS. For now, just map value.
                     val = v;
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
                        return v > 0 ? `rgba(233, 50, 45, ${Math.min(0.4 + (v/10), 1)})` : '#eee'; // Red Scale
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