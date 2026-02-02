document.addEventListener('DOMContentLoaded', function() {
    
    // --- Global State ---
    let charts = {
        daily: null,
        job: null,
        travelState: null,
        travelCounty: null,
        gauge: null
        // Removed maps from here, storing separately
    };
    
    // Leaflet Instances
    let mapState = null;
    let mapCounty = null;

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
                
                // Leaflet Resize Fix: Maps need to invalidate size when tab opens
                if(target === 'tab-state' && mapState) setTimeout(() => mapState.invalidateSize(), 200);
                if(target === 'tab-county' && mapCounty) setTimeout(() => mapCounty.invalidateSize(), 200);
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
                    if(cachedData) renderLeafletCountyMap(cachedData.counties, abbr);
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
        // Cards & Travel
        document.getElementById('stat-total-hours').innerText = data.total_hours;
        document.getElementById('stat-reg-hours').innerText = data.stats.regular_hours;
        document.getElementById('stat-pto-hours').innerText = data.stats.pto_hours;
        document.getElementById('stat-overtime-hours').innerText = data.overtime_hours;
        document.getElementById('val-total-miles').innerText = data.travel.total_miles;
        document.getElementById('val-per-diem').innerText = data.travel.per_diem_days;
        document.getElementById('val-overnight').innerText = data.travel.overnight_stays;
        document.getElementById('val-expenses').innerText = '$' + data.travel.total_expenses;

        // Render Basic Charts
        renderOverviewChart(data.trend);
        renderTravelCharts(data.states, data.counties); 
        if (document.getElementById('chart-jobs')) renderJobCharts(data.jobs, data.total_hours);
        if (document.getElementById('chart-profitability-gauge')) {
            const currentJobFilter = jobSearch ? jobSearch.value : 'All Jobs';
            updateGauge(data.jobs, currentJobFilter);
        }

        // Render Maps (Leaflet Logic)
        if(!usTopology) {
            // DIRECT LOCAL PATH
            const mapUrl = OC.webroot + '/apps/stech_timesheet/js/us-atlas.json';
            fetch(mapUrl)
                .then(r => {
                    if(!r.ok) throw new Error("File not found");
                    return r.json();
                })
                .then(topo => {
                    usTopology = topo;
                    renderLeafletStateMap(data.states);
                    if(stateSearch && stateSearch.value) {
                         // ... find abbr logic ...
                         const opts = document.getElementById('state-list').options;
                         let abbr = '';
                         for(let i=0; i<opts.length; i++) if(opts[i].value === stateSearch.value) abbr = opts[i].getAttribute('data-value');
                         renderLeafletCountyMap(data.counties, abbr);
                    }
                })
                .catch(e => console.error("Map Load Error:", e));
        } else {
            renderLeafletStateMap(data.states);
            if(stateSearch && stateSearch.value) {
                 const opts = document.getElementById('state-list').options;
                 let abbr = '';
                 for(let i=0; i<opts.length; i++) if(opts[i].value === stateSearch.value) abbr = opts[i].getAttribute('data-value');
                 renderLeafletCountyMap(data.counties, abbr);
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
    //  4. CHART RENDERERS (Standard)
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

        // 2. Set Visual Scale
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
                
                // Ratio Logic:
                // Left (Red) = Loss
                // Middle (Yellow) = Low Margin
                // Right (Green) = High Margin
                
                let ratio = 0;
                
                if (revenue > 0) {
                    const margin = profit / revenue; 
                    if(margin < 0) {
                        // Loss -> Red Section (0.0 - 0.33)
                        let lossSeverity = Math.min(Math.abs(margin), 0.5) / 0.5; 
                        ratio = 0.33 - (lossSeverity * 0.33); 
                    } else {
                        // Profit -> Yellow/Green Section (0.33 - 1.0)
                        let success = Math.min(margin, 0.5) / 0.5; 
                        ratio = 0.33 + (success * 0.67);
                    }
                } else {
                    ratio = profit < 0 ? 0.1 : 0.5;
                }
                if (ratio < 0) ratio = 0;
                if (ratio > 1) ratio = 1;

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
    //  5. LEAFLET MAP RENDERERS (Interactive & Zoomable)
    // =========================================================

    // --- Helper: Color Scale (Grey -> Red) ---
    function getMapColor(d) {
        return d > 50 ? '#800026' :
               d > 20 ? '#BD0026' :
               d > 10 ? '#E31A1C' :
               d > 5  ? '#FC4E2A' :
               d > 2  ? '#FD8D3C' :
               d > 0  ? '#FEB24C' :
                        '#EEEEEE'; // Grey for 0
    }

    // --- Helper: Style Function ---
    function getStyle(value) {
        return {
            fillColor: getMapColor(value),
            weight: 1,
            opacity: 1,
            color: 'white',
            dashArray: '3',
            fillOpacity: 0.7
        };
    }

    // --- A. US State Map ---
    function renderLeafletStateMap(stateData) {
        if (!L || !topojson || !usTopology) return;

        const containerId = 'map-state-container';
        
        // Initialize Map if needed
        if (!mapState) {
            mapState = L.map(containerId, {
                center: [37.8, -96],
                zoom: 4,
                scrollWheelZoom: false // Enable only on focus/click if desired, or true
            });
            // Optional: Add a clean base tile layer (or leave blank for pure "hotbed" look)
            // L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(mapState);
        }

        // Clear previous layers
        mapState.eachLayer(layer => mapState.removeLayer(layer));

        // Convert TopoJSON -> GeoJSON
        const geojson = topojson.feature(usTopology, usTopology.objects.states);

        // Add Layer with Data
        const layer = L.geoJson(geojson, {
            style: function(feature) {
                const name = feature.properties.name;
                const value = stateData[name] || 0;
                return getStyle(value);
            },
            onEachFeature: function(feature, layer) {
                const name = feature.properties.name;
                const value = stateData[name] || 0;
                
                // Tooltip
                layer.bindTooltip(`<strong>${name}</strong><br>Visits: ${value}`, {
                    direction: 'top', sticky: true
                });

                // Interaction
                layer.on({
                    mouseover: (e) => {
                        const l = e.target;
                        l.setStyle({ weight: 2, color: '#666', dashArray: '', fillOpacity: 0.9 });
                        l.bringToFront();
                    },
                    mouseout: (e) => {
                        layer.resetStyle(e.target);
                    },
                    click: (e) => {
                        // Optional: Click state to filter county map?
                        // For now just zoom
                        mapState.fitBounds(e.target.getBounds());
                    }
                });
            }
        }).addTo(mapState);
    }

    // --- B. County Map (Zoom to State + Collision Fix) ---
    function renderLeafletCountyMap(countyData, stateAbbr) {
        if (!L || !topojson || !usTopology) return;

        // Hide placeholder
        document.getElementById('county-map-placeholder').style.display = 'none';
        
        // Initialize Map
        if (!mapCounty) {
            mapCounty = L.map('map-county-container', {
                center: [37.8, -96],
                zoom: 4
            });
        }
        
        // Clear previous
        mapCounty.eachLayer(layer => mapCounty.removeLayer(layer));

        // 1. Resolve State Logic (Name & FIPS)
        let selectedStateName = '';
        let selectedStateFips = '';

        const opts = document.getElementById('state-list').options;
        for(let i=0; i<opts.length; i++) {
            if(opts[i].getAttribute('data-value') === stateAbbr) {
                selectedStateName = opts[i].value; 
                break;
            }
        }
        if(!selectedStateName) selectedStateName = stateAbbr;

        // Find FIPS ID
        const allStates = usTopology.objects.states.geometries;
        const targetStateGeo = allStates.find(s => s.properties.name === selectedStateName);
        if(targetStateGeo) selectedStateFips = targetStateGeo.id;

        // 2. Filter Geometry
        // Get ALL counties
        let allCounties = topojson.feature(usTopology, usTopology.objects.counties).features;
        
        // Filter: Only counties matching State FIPS (e.g., "01" for AL)
        const filteredFeatures = selectedStateFips 
            ? allCounties.filter(f => f.id.startsWith(selectedStateFips))
            : []; // If no state selected, show nothing (or all)

        if (filteredFeatures.length === 0) return;

        // 3. Render
        const layer = L.geoJson(filteredFeatures, {
            style: function(feature) {
                const name = feature.properties.name;
                // Strict Key: "StateName|CountyName"
                const dbKey = selectedStateName + '|' + name;
                const value = countyData[dbKey] || 0;
                
                return getStyle(value);
            },
            onEachFeature: function(feature, layer) {
                const name = feature.properties.name;
                const dbKey = selectedStateName + '|' + name;
                const value = countyData[dbKey] || 0;

                layer.bindTooltip(`<strong>${name} County</strong><br>Visits: ${value}`, {
                    direction: 'top', sticky: true
                });

                layer.on({
                    mouseover: (e) => {
                        const l = e.target;
                        l.setStyle({ weight: 2, color: '#666', dashArray: '', fillOpacity: 0.9 });
                        l.bringToFront();
                    },
                    mouseout: (e) => {
                        layer.resetStyle(e.target);
                    }
                });
            }
        }).addTo(mapCounty);

        // 4. Auto-Zoom to State
        mapCounty.fitBounds(layer.getBounds());
    }

}); // END DOMContentLoaded