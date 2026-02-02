document.addEventListener('DOMContentLoaded', function() {
    
    // --- Global State ---
    let charts = {
        daily: null,
        job: null,
        travelState: null,
        travelCounty: null,
        gauge: null
    };
    
    // Leaflet Instances
    let mapState = null;
    let mapCounty = null;

    let cachedData = null; 
    let usTopology = null; 
    
    // State FIPS Lookup (Created dynamically)
    let fipsToStateName = {}; 
    let stateBounds = {}; // Cache for zooming

    // --- Elements ---
    const rangeSelect = document.getElementById('range-preset');
    const customInputs = document.getElementById('custom-date-inputs');
    const startInput = document.getElementById('analysis-start');
    const endInput = document.getElementById('analysis-end');
    const updateBtn = document.getElementById('btn-refresh-analysis');
    
    const userSearch = document.getElementById('user-search');
    const userHidden = document.getElementById('analysis-target-user');
    const jobSearch = document.getElementById('job-search');
    const jobHidden = document.getElementById('analysis-job-filter');
    const stateSearch = document.getElementById('state-search');
    const stateHidden = document.getElementById('analysis-state-filter');

    // --- Init ---
    initTabs();
    initSearchInputs(); 
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
                
                // Resize Maps
                if(target === 'tab-state' && mapState) setTimeout(() => mapState.invalidateSize(), 200);
                if(target === 'tab-county' && mapCounty) setTimeout(() => mapCounty.invalidateSize(), 200);
            });
        });
    }

    // --- Search Input Logic (Aggressive Clear) ---
    function initSearchInputs() {
        const setupClear = (el, hiddenInput, triggerLoad = true) => {
            if(!el) return;
            
            // Clear on click to show full list
            const clearFn = function() {
                if (this.value !== '') {
                    this.value = '';
                    if (hiddenInput) hiddenInput.value = (triggerLoad && hiddenInput.id !== 'analysis-target-user') ? 'all' : ''; 
                    
                    // Trigger input event to reset datalist filtering
                    const event = new Event('input', { bubbles: true });
                    this.dispatchEvent(event);
                }
            };

            el.addEventListener('click', clearFn);
            // Optional: clear on focus too, if desired
            // el.addEventListener('focus', clearFn);
        };

        setupClear(userSearch, userHidden, true);
        setupClear(jobSearch, jobHidden, true);
        
        // State is special: clearing it shouldn't trigger reload, just map reset
        if(stateSearch) {
            stateSearch.addEventListener('click', function() {
                this.value = '';
                stateHidden.value = '';
                // Reset map to full view
                if(cachedData) renderLeafletCountyMap(cachedData.counties, null);
            });
        }
    }

    // --- Data Loading: Filters ---
    function loadFilters() {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/analysis/filters'), { headers: { 'requesttoken': OC.requestToken } })
        .then(r => r.json())
        .then(data => {
            const populate = (id, items, valKey, attrKey) => {
                const list = document.getElementById(id);
                if(!list) return;
                list.innerHTML = ''; // Clear existing
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

    // --- Event Listeners ---
    rangeSelect.addEventListener('change', () => {
        if (rangeSelect.value === 'custom') customInputs.classList.remove('hidden');
        else { customInputs.classList.add('hidden'); loadStats(); }
    });
    startInput.addEventListener('change', loadStats);
    endInput.addEventListener('change', loadStats);
    if (updateBtn) updateBtn.addEventListener('click', loadStats);

    // Input Listeners (Selection Logic)
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
                    // Just zoom/pan map, don't reload stats
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
        document.getElementById('stat-total-hours').innerText = data.total_hours;
        document.getElementById('stat-reg-hours').innerText = data.stats.regular_hours;
        document.getElementById('stat-pto-hours').innerText = data.stats.pto_hours;
        const ot = data.stats.overtime_hours !== undefined ? data.stats.overtime_hours : 0;
        document.getElementById('stat-overtime-hours').innerText = ot;

        if(document.getElementById('val-total-miles')) {
            document.getElementById('val-total-miles').innerText = data.travel.total_miles;
            document.getElementById('val-per-diem').innerText = data.travel.per_diem_days;
            document.getElementById('val-overnight').innerText = data.travel.overnight_stays;
            document.getElementById('val-expenses').innerText = '$' + data.travel.total_expenses;
        }

        renderOverviewChart(data.trend);
        renderTravelCharts(data.states, data.counties); 
        
        if (document.getElementById('chart-jobs')) renderJobCharts(data.jobs, data.total_hours);
        if (document.getElementById('chart-profitability-gauge')) {
            const currentJobFilter = jobSearch ? jobSearch.value : 'All Jobs';
            updateGauge(data.jobs, currentJobFilter);
        }

        // --- MAP LOADER ---
        if(!usTopology) {
            const mapUrl = OC.webroot + '/apps/stech_timesheet/js/us-atlas.json';
            fetch(mapUrl)
                .then(r => r.json())
                .then(topo => {
                    usTopology = topo;
                    
                    // 1. Build FIPS -> State Name Map (CRITICAL for Chambers County fix)
                    const states = topojson.feature(topo, topo.objects.states).features;
                    states.forEach(s => {
                        fipsToStateName[s.id] = s.properties.name;
                    });

                    renderLeafletStateMap(data.states);
                    
                    // Check if state is already selected
                    let abbr = null;
                    if(stateSearch && stateSearch.value) {
                         const opts = document.getElementById('state-list').options;
                         for(let i=0; i<opts.length; i++) if(opts[i].value === stateSearch.value) abbr = opts[i].getAttribute('data-value');
                    }
                    renderLeafletCountyMap(data.counties, abbr);
                })
                .catch(e => console.error("Map Load Error:", e));
        } else {
            renderLeafletStateMap(data.states);
            let abbr = null;
            if(stateSearch && stateSearch.value) {
                 const opts = document.getElementById('state-list').options;
                 for(let i=0; i<opts.length; i++) if(opts[i].value === stateSearch.value) abbr = opts[i].getAttribute('data-value');
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
    //  4. CHART RENDERERS
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
    //  5. LEAFLET MAP RENDERERS (Fixed)
    // =========================================================

    function getMapColor(d) {
        return d > 50 ? '#800026' :
               d > 20 ? '#BD0026' :
               d > 10 ? '#E31A1C' :
               d > 5  ? '#FC4E2A' :
               d > 2  ? '#FD8D3C' :
               d > 0  ? '#FEB24C' :
                        '#EEEEEE'; 
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

    function renderLeafletStateMap(stateData) {
        if (!L || !topojson || !usTopology) return;
        const containerId = 'map-state-container';
        if (!mapState) {
            mapState = L.map(containerId, { center: [37.8, -96], zoom: 4, scrollWheelZoom: false });
        }

        mapState.eachLayer(layer => mapState.removeLayer(layer));
        const geojson = topojson.feature(usTopology, usTopology.objects.states);

        // Store layer ref for resetStyle
        const geoJsonLayer = L.geoJson(geojson, {
            style: function(feature) {
                const name = feature.properties.name;
                const value = stateData[name] || 0;
                return getStyle(value);
            },
            onEachFeature: function(feature, layer) {
                const name = feature.properties.name;
                const value = stateData[name] || 0;
                
                // Save bounds for Zooming to this state later
                stateBounds[name] = layer.getBounds();

                layer.bindTooltip(`<strong>${name}</strong><br>Visits: ${value}`, { direction: 'top', sticky: true });
                layer.on({
                    mouseover: (e) => {
                        e.target.setStyle({ weight: 2, color: '#666', dashArray: '', fillOpacity: 0.9 });
                        e.target.bringToFront();
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

    function renderLeafletCountyMap(countyData, stateAbbr) {
        if (!L || !topojson || !usTopology) return;
        document.getElementById('county-map-placeholder').style.display = 'none';
        
        if (!mapCounty) {
            mapCounty = L.map('map-county-container', { center: [37.8, -96], zoom: 4 });
        }
        
        mapCounty.eachLayer(layer => mapCounty.removeLayer(layer));

        // 1. Resolve Selection
        let selectedStateName = null;
        if (stateAbbr) {
            const opts = document.getElementById('state-list').options;
            for(let i=0; i<opts.length; i++) {
                if(opts[i].getAttribute('data-value') === stateAbbr) {
                    selectedStateName = opts[i].value; 
                    break;
                }
            }
            if(!selectedStateName) selectedStateName = stateAbbr;
        }

        // 2. Load ALL Features (Do NOT filter)
        const allCounties = topojson.feature(usTopology, usTopology.objects.counties).features;

        // 3. Render
        const geoJsonLayer = L.geoJson(allCounties, {
            style: function(feature) {
                const name = feature.properties.name; // "Chambers"
                
                // DATA MATCHING LOGIC
                // We must use FIPS to find the correct state for this county feature
                const fips = feature.id; // "01017"
                let value = 0;

                if (fips) {
                    const stateFips = fips.substring(0, 2); // "01"
                    const stateName = fipsToStateName[stateFips]; // "Alabama"
                    
                    if (stateName) {
                        const dbKey = stateName + '|' + name; // "Alabama|Chambers"
                        value = countyData[dbKey] || 0;
                    }
                }

                return getStyle(value);
            },
            onEachFeature: function(feature, layer) {
                const name = feature.properties.name;
                const fips = feature.id;
                let stateName = "Unknown";
                let value = 0;

                if(fips && fipsToStateName[fips.substring(0,2)]) {
                    stateName = fipsToStateName[fips.substring(0,2)];
                    const dbKey = stateName + '|' + name;
                    value = countyData[dbKey] || 0;
                }

                layer.bindTooltip(`<strong>${name} County, ${stateName}</strong><br>Visits: ${value}`, { direction: 'top', sticky: true });
                layer.on({
                    mouseover: (e) => {
                        e.target.setStyle({ weight: 2, color: '#666', dashArray: '', fillOpacity: 0.9 });
                        e.target.bringToFront();
                    },
                    mouseout: (e) => {
                        geoJsonLayer.resetStyle(e.target);
                    }
                });
            }
        }).addTo(mapCounty);

        // 4. Smart Pan/Zoom
        if (selectedStateName && stateBounds[selectedStateName]) {
            // If state selected, zoom to cached bounds of that state
            mapCounty.fitBounds(stateBounds[selectedStateName]);
        } else {
            // Full US View
            mapCounty.setView([37.8, -96], 4);
        }
    }

});