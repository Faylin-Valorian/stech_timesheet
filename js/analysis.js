document.addEventListener('DOMContentLoaded', function() {
    
    // --- Global State ---
    let charts = { daily: null, job: null, travelState: null, travelCounty: null, gauge: null };
    let mapState = null;
    let mapCounty = null;

    let cachedData = null; 
    let usTopology = null; 
    
    // Lookup Maps
    let fipsToStateName = {}; 
    let stateBounds = {}; 

    // --- Elements ---
    const rangeSelect = document.getElementById('range-preset');
    const customInputs = document.getElementById('custom-date-inputs');
    const startInput = document.getElementById('analysis-start');
    const endInput = document.getElementById('analysis-end');
    const updateBtn = document.getElementById('btn-refresh-analysis');
    
    // Inputs
    const userSearch = document.getElementById('user-search');
    const userHidden = document.getElementById('analysis-target-user');
    const jobSearch = document.getElementById('job-search');
    const jobHidden = document.getElementById('analysis-job-filter');
    const stateSearch = document.getElementById('state-search');
    const stateHidden = document.getElementById('analysis-state-filter');

    // --- Init ---
    initTabs();
    initSearchBehaviors(); // New Unified Logic
    loadFilters(); 
    loadStats();   

    // --- 1. Tab Logic ---
    function initTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                
                e.target.classList.add('active');
                const target = e.target.dataset.tab;
                document.getElementById(target).classList.add('active');
                
                // Resize Leaflet maps when tab becomes visible
                if(target === 'tab-state' && mapState) setTimeout(() => mapState.invalidateSize(), 200);
                if(target === 'tab-county' && mapCounty) setTimeout(() => mapCounty.invalidateSize(), 200);
            });
        });
    }

    // --- 2. Unified Search Behavior (Points 1, 2, 3, 4) ---
    function initSearchBehaviors() {
        
        // Helper: Clear input on click to show full datalist
        const attachClickToClear = (inputEl, hiddenEl, defaultHiddenVal) => {
            if(!inputEl) return;
            inputEl.addEventListener('click', function() {
                // Save current valid value before clearing? 
                // No, user wants to see full list.
                if(this.value !== '') {
                    this.value = ''; // Clear text
                    // Reset hidden value temporarily or completely?
                    // We don't trigger load immediately on click, only on selection/input
                }
            });
            
            // Handle "Blur" (Clicking away) - Restore default if empty
            inputEl.addEventListener('change', function() {
                if(this.value === '') {
                    if(defaultHiddenVal !== null) {
                        // Point 3: Default to "Myself" if empty
                        if(inputEl.id === 'user-search') {
                            this.value = "Myself"; // Visual
                            hiddenEl.value = "self";
                            loadStats();
                        } else if(inputEl.id === 'job-search') {
                            hiddenEl.value = "all";
                            if(cachedData) updateGauge(cachedData.jobs, 'All Jobs');
                        }
                    }
                }
            });
        };

        // A. User Search (Fixes "Myself", "All", and Defaulting)
        if(userSearch) {
            attachClickToClear(userSearch, userHidden, 'self');
            
            userSearch.addEventListener('input', function() {
                const val = this.value;
                const opts = document.getElementById('user-list').options;
                let found = false;
                
                // 1. Check exact match in datalist
                for(let i=0; i<opts.length; i++) {
                    if(opts[i].value === val) {
                        userHidden.value = opts[i].getAttribute('data-value'); // 'self', 'all', or UID
                        loadStats();
                        found = true;
                        break;
                    }
                }
                
                // 2. If user clears box completely, set to self (Point 3)
                if(val === "") {
                    userHidden.value = 'self';
                    // Optional: Wait for blur to reload or reload now? 
                    // Let's wait for selection or blur to avoid flashing
                }
            });
        }

        // B. Job Search (Point 4 - Profitability Tab)
        if(jobSearch) {
            attachClickToClear(jobSearch, jobHidden, 'all');
            
            jobSearch.addEventListener('input', function() {
                const val = this.value;
                const opts = document.getElementById('job-list').options;
                
                if(val === "") {
                    jobHidden.value = 'all';
                    if(cachedData) updateGauge(cachedData.jobs, 'All Jobs');
                    return;
                }

                for(let i=0; i<opts.length; i++) {
                    if(opts[i].value === val) {
                        jobHidden.value = opts[i].getAttribute('data-value');
                        if(cachedData) updateGauge(cachedData.jobs, val);
                        break;
                    }
                }
            });
        }

        // C. State Search (Point 2 - County Map)
        if(stateSearch) {
            // No default hidden value needed (defaults to full map)
            stateSearch.addEventListener('click', function() {
                if(this.value !== '') {
                    this.value = '';
                    stateHidden.value = '';
                    // Reset map to full view immediately on clear
                    if(cachedData) renderLeafletCountyMap(cachedData.counties, null);
                }
            });

            stateSearch.addEventListener('input', function() {
                const val = this.value;
                const opts = document.getElementById('state-list').options;
                
                for(let i=0; i<opts.length; i++) {
                    if(opts[i].value === val) {
                        const abbr = opts[i].getAttribute('data-value');
                        stateHidden.value = abbr;
                        if(cachedData) renderLeafletCountyMap(cachedData.counties, abbr);
                        break;
                    }
                }
            });
        }
    }

    // --- 3. Data Loading ---
    function loadFilters() {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/analysis/filters'), { headers: { 'requesttoken': OC.requestToken } })
        .then(r => r.json())
        .then(data => {
            const populate = (id, items, valKey, attrKey) => {
                const list = document.getElementById(id);
                if(!list) return;
                list.innerHTML = ''; 
                items.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item[valKey];
                    opt.setAttribute('data-value', item[attrKey]);
                    list.appendChild(opt);
                });
            };
            populate('user-list', data.users, 'displayname', 'uid');
            populate('job-list', data.jobs, 'job_name', 'job_id');
            populate('state-list', data.states, 'state_name', 'state_abbr');
        });
    }

    // --- 4. Main Stats Loader ---
    function loadStats() {
        const period = rangeSelect.value;
        let queryParams = '?period=' + period;
        
        if (period === 'custom') {
            const s = startInput.value; const e = endInput.value;
            if(!s || !e) return;
            queryParams += '&start=' + s + '&end=' + e;
        }

        // Ensure we send 'self', 'all', or UID
        // If userHidden is empty (shouldn't be due to logic above), default to self
        const target = userHidden && userHidden.value ? userHidden.value : 'self';
        queryParams += '&target_user=' + target;

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

    // Event Listeners for Range
    rangeSelect.addEventListener('change', () => {
        if (rangeSelect.value === 'custom') customInputs.classList.remove('hidden');
        else { customInputs.classList.add('hidden'); loadStats(); }
    });
    startInput.addEventListener('change', loadStats);
    endInput.addEventListener('change', loadStats);
    if (updateBtn) updateBtn.addEventListener('click', loadStats);

    // =========================================================
    //  5. UI UPDATER
    // =========================================================
    function updateUI(data) {
        // A. Update Cards
        document.getElementById('stat-total-hours').innerText = data.total_hours;
        document.getElementById('stat-reg-hours').innerText = data.stats.regular_hours;
        document.getElementById('stat-pto-hours').innerText = data.stats.pto_hours;
        const ot = data.stats.overtime_hours !== undefined ? data.stats.overtime_hours : 0;
        document.getElementById('stat-overtime-hours').innerText = ot;

        // B. Update Travel Summary
        if(document.getElementById('val-total-miles')) {
            document.getElementById('val-total-miles').innerText = data.travel.total_miles;
            document.getElementById('val-per-diem').innerText = data.travel.per_diem_days;
            document.getElementById('val-overnight').innerText = data.travel.overnight_stays;
            document.getElementById('val-expenses').innerText = '$' + data.travel.total_expenses;
        }

        // C. Render Standard Charts
        renderOverviewChart(data.trend);
        renderTravelCharts(data.states, data.counties); 
        
        if (document.getElementById('chart-jobs')) renderJobCharts(data.jobs, data.total_hours);
        
        // D. Update Profitability Gauge (Point 4 Fix)
        if (document.getElementById('chart-profitability-gauge')) {
            const currentJobFilter = jobSearch ? jobSearch.value : 'All Jobs';
            // Handle the case where the input might be empty (default to 'All Jobs')
            const filterToUse = currentJobFilter === '' ? 'All Jobs' : currentJobFilter;
            updateGauge(data.jobs, filterToUse);
        }

        // E. Render Maps (With FIPS Setup)
        // If topology isn't loaded, fetch it first.
        if(!usTopology) {
            const mapUrl = OC.webroot + '/apps/stech_timesheet/js/us-atlas.json';
            fetch(mapUrl)
                .then(r => {
                    if(!r.ok) throw new Error("File not found");
                    return r.json();
                })
                .then(topo => {
                    usTopology = topo;
                    
                    // --- FIPS Lookup Creation (Critical for Point 5) ---
                    // We map "01" -> "Alabama", "48" -> "Texas", etc.
                    const states = topojson.feature(topo, topo.objects.states).features;
                    states.forEach(s => {
                        fipsToStateName[s.id] = s.properties.name;
                    });

                    // Initial Render
                    renderLeafletStateMap(data.states);
                    
                    // Check if a state is currently selected in the filter
                    let abbr = null;
                    if(stateSearch && stateSearch.value) {
                         const opts = document.getElementById('state-list').options;
                         for(let i=0; i<opts.length; i++) {
                             if(opts[i].value === stateSearch.value) abbr = opts[i].getAttribute('data-value');
                         }
                    }
                    renderLeafletCountyMap(data.counties, abbr);
                })
                .catch(e => console.error("Map Load Error:", e));
        } else {
            // Topology already exists, just render
            renderLeafletStateMap(data.states);
            
            let abbr = null;
            if(stateSearch && stateSearch.value) {
                 const opts = document.getElementById('state-list').options;
                 for(let i=0; i<opts.length; i++) {
                     if(opts[i].value === stateSearch.value) abbr = opts[i].getAttribute('data-value');
                 }
            }
            renderLeafletCountyMap(data.counties, abbr);
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
    //  6. CHART RENDERERS (Standard)
    // =========================================================

    function renderOverviewChart(trend) {
        const ctx = document.getElementById('chart-daily').getContext('2d');
        if (charts.daily) charts.daily.destroy();
        const theme = getTheme();
        charts.daily = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [{
                    label: 'Hours Worked', data: trend.values,
                    borderColor: '#0082c9', backgroundColor: 'rgba(0, 130, 201, 0.2)',
                    fill: true, tension: 0.3, pointRadius: 3
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

    function updateGauge(jobs, filterName) {
        const ctx = document.getElementById('chart-profitability-gauge');
        if (!ctx) return;
        
        let revenue = 0, expenses = 0, laborCost = 0, profit = 0, label = "";
        
        // Match logic: 'All Jobs', 'all', or empty string means calculate total
        if (filterName === 'All Jobs' || filterName === 'all' || filterName === '') {
            jobs.forEach(j => {
                revenue += j.revenue; expenses += j.budget; laborCost += (j.hours * j.hourly_cost);
            });
            profit = revenue - expenses - laborCost;
            label = "$" + profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " Net Profit";
        } else {
            const job = jobs.find(x => x.name === filterName);
            if (job) {
                revenue = job.revenue; expenses = job.budget; laborCost = job.hours * job.hourly_cost;
                profit = revenue - expenses - laborCost;
                label = "$" + profit.toLocaleString() + " Net Profit";
            }
        }

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

        const needlePlugin = {
            id: 'needle',
            afterDatasetDraw(chart) {
                const { ctx, chartArea: { width, height } } = chart;
                ctx.save();
                let ratio = 0;
                if (revenue > 0) {
                    const margin = profit / revenue; 
                    if(margin < 0) {
                        let lossSeverity = Math.min(Math.abs(margin), 0.5) / 0.5; 
                        ratio = 0.33 - (lossSeverity * 0.33); 
                    } else {
                        let success = Math.min(margin, 0.5) / 0.5; 
                        ratio = 0.33 + (success * 0.67);
                    }
                } else {
                    ratio = profit < 0 ? 0.1 : 0.5;
                }
                if (ratio < 0) ratio = 0; if (ratio > 1) ratio = 1;

                const angle = Math.PI + (ratio * Math.PI);
                const cx = width / 2;
                const cy = chart._metasets[0].data[0].y;

                ctx.translate(cx, cy);
                ctx.rotate(angle);
                ctx.beginPath();
                ctx.moveTo(0, -2);
                ctx.lineTo(height - (ctx.canvas.offsetTop + 40), 0);
                ctx.lineTo(0, 2);
                ctx.fillStyle = getTheme().text;
                ctx.fill();
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
                    data: [33, 33, 34], backgroundColor: ['#e9322d', '#ffd60a', '#46ba6f'], borderWidth: 0
                }]
            },
            options: {
                rotation: -90, circumference: 180, responsive: true, maintainAspectRatio: false, cutout: '70%',
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            },
            plugins: [needlePlugin]
        });
    }

    // =========================================================
    //  7. LEAFLET MAP RENDERERS (Strict Matching & Auto-Zoom)
    // =========================================================

    // --- Helper: Color Scale ---
    function getMapColor(d) {
        return d > 50 ? '#800026' :
               d > 20 ? '#BD0026' :
               d > 10 ? '#E31A1C' :
               d > 5  ? '#FC4E2A' :
               d > 2  ? '#FD8D3C' :
               d > 0  ? '#FEB24C' :
                        '#EEEEEE'; // Grey for 0
    }

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

    // --- A. STATE MAP ---
    function renderLeafletStateMap(stateData) {
        if (!L || !topojson || !usTopology) return;

        const containerId = 'map-state-container';
        if (!mapState) {
            mapState = L.map(containerId, {
                center: [37.8, -96],
                zoom: 4,
                scrollWheelZoom: false 
            });
        }

        // Clear previous layers
        mapState.eachLayer(layer => mapState.removeLayer(layer));

        const geojson = topojson.feature(usTopology, usTopology.objects.states);

        const geoJsonLayer = L.geoJson(geojson, {
            style: function(feature) {
                const name = feature.properties.name;
                const value = stateData[name] || 0;
                return getStyle(value);
            },
            onEachFeature: function(feature, layer) {
                const name = feature.properties.name;
                const value = stateData[name] || 0;
                
                // CACHE BOUNDS: Save this state's bounds for the County Map to use later
                stateBounds[name] = layer.getBounds();

                layer.bindTooltip(`<strong>${name}</strong><br>Visits: ${value}`, {
                    direction: 'top', sticky: true
                });

                layer.on({
                    mouseover: (e) => {
                        const l = e.target;
                        l.setStyle({ weight: 2, color: '#666', dashArray: '', fillOpacity: 0.9 });
                        l.bringToFront();
                    },
                    mouseout: (e) => {
                        geoJsonLayer.resetStyle(e.target);
                    },
                    click: (e) => {
                        mapState.fitBounds(e.target.getBounds());
                    }
                });
            }
        }).addTo(mapState);
    }

    // --- B. COUNTY MAP (The Fix) ---
    function renderLeafletCountyMap(countyData, stateAbbr) {
        if (!L || !topojson || !usTopology) return;

        document.getElementById('county-map-placeholder').style.display = 'none';
        
        if (!mapCounty) {
            mapCounty = L.map('map-county-container', {
                center: [37.8, -96],
                zoom: 4
            });
        }
        
        mapCounty.eachLayer(layer => mapCounty.removeLayer(layer));

        // 1. Resolve Selection Name (e.g. "TX" -> "Texas")
        let selectedStateName = null;
        if (stateAbbr) {
            const opts = document.getElementById('state-list').options;
            for(let i=0; i<opts.length; i++) {
                if(opts[i].getAttribute('data-value') === stateAbbr) {
                    selectedStateName = opts[i].value; 
                    break;
                }
            }
        }

        // 2. Load ALL Features (Full Map)
        // We do NOT filter features anymore. We load everything.
        const allCounties = topojson.feature(usTopology, usTopology.objects.counties).features;

        const geoJsonLayer = L.geoJson(allCounties, {
            style: function(feature) {
                const name = feature.properties.name; // e.g., "Chambers"
                const fips = feature.id; // e.g., "01017" (Alabama) or "48071" (Texas)
                
                let value = 0;

                // STRICT MATCHING: Use FIPS to determine State Name
                if (fips) {
                    const stateFips = fips.substring(0, 2); // First 2 digits are State ID
                    const stateName = fipsToStateName[stateFips]; // Lookup "Alabama"
                    
                    if (stateName) {
                        // Construct exact key: "Alabama|Chambers"
                        const dbKey = stateName + '|' + name;
                        value = countyData[dbKey] || 0;
                    }
                }
                
                return getStyle(value);
            },
            onEachFeature: function(feature, layer) {
                const name = feature.properties.name;
                const fips = feature.id;
                
                // Determine State Name for Tooltip
                let stateLabel = "Unknown State";
                let value = 0;

                if (fips) {
                    const stateFips = fips.substring(0, 2);
                    const stateName = fipsToStateName[stateFips];
                    if(stateName) {
                        stateLabel = stateName;
                        const dbKey = stateName + '|' + name;
                        value = countyData[dbKey] || 0;
                    }
                }

                layer.bindTooltip(`<strong>${name} County, ${stateLabel}</strong><br>Visits: ${value}`, {
                    direction: 'top', sticky: true
                });

                layer.on({
                    mouseover: (e) => {
                        const l = e.target;
                        l.setStyle({ weight: 2, color: '#666', dashArray: '', fillOpacity: 0.9 });
                        l.bringToFront();
                    },
                    mouseout: (e) => {
                        geoJsonLayer.resetStyle(e.target);
                    }
                });
            }
        }).addTo(mapCounty);

        // 3. ZOOM LOGIC
        // If a state is selected, pan/zoom to THAT state's bounds (using cached bounds from State Map)
        if (selectedStateName && stateBounds[selectedStateName]) {
            mapCounty.fitBounds(stateBounds[selectedStateName]);
        } else {
            // Otherwise, reset to full US view
            mapCounty.setView([37.8, -96], 4);
        }
    }

}); // END DOMContentLoaded