import { StechAPI } from './api.js';
import { AnalysisCharts } from './analysis/charts.js';
import { AnalysisMaps } from './analysis/maps.js';
import { AnalysisGauges } from './analysis/gauges.js';

/**
 * Entry Point for Time Analysis Dashboard
 * Orchestrates data fetching, UI updates, and unified search behaviors.
 */
document.addEventListener('DOMContentLoaded', () => {
    // UI Elements
    const rangeSelect = document.getElementById('range-preset');
    const userHidden = document.getElementById('analysis-target-user');
    const userSearch = document.getElementById('user-search');
    const jobHidden = document.getElementById('analysis-job-filter');
    const jobSearch = document.getElementById('job-search');
    const stateSearch = document.getElementById('state-search');
    const stateHidden = document.getElementById('analysis-state-filter');

    let cachedData = null;

    // --- 1. Initialization ---
    initTabs();
    initSearchBehaviors();
    loadFilters();
    loadStats();

    // --- 2. Tab Logic ---
    function initTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                // UI: Toggle active classes
                document.querySelectorAll('.tab-btn, .tab-pane').forEach(el => el.classList.remove('active'));
                e.target.classList.add('active');
                
                const targetId = e.target.dataset.tab;
                const targetPane = document.getElementById(targetId);
                if (targetPane) {
                    targetPane.classList.add('active');
                }
                
                // Logic: Refresh Leaflet maps when the Travel Activity tab becomes visible
                // This ensures maps calculate their container size correctly after the pane displays.
                if (targetId === 'tab-travel') {
                    AnalysisMaps.refresh('state');
                    AnalysisMaps.refresh('county');
                }
            });
        });
    }

    // --- 3. Unified Search Logic ---
    function initSearchBehaviors() {
        /**
         * Clears input on click and handles default values on change
         */
        const setupAutoClear = (inputEl, hiddenEl, defaultVal) => {
            if (!inputEl) return;
            inputEl.addEventListener('click', () => { 
                if (inputEl.value !== '') inputEl.value = ''; 
            });
            inputEl.addEventListener('change', () => {
                if (inputEl.value === '' && defaultVal === 'self') {
                    inputEl.value = "Myself";
                    hiddenEl.value = "self";
                    loadStats();
                }
            });
        };

        // User Search
        setupAutoClear(userSearch, userHidden, 'self');
        userSearch?.addEventListener('input', function() {
            const opt = Array.from(document.getElementById('user-list').options).find(o => o.value === this.value);
            if (opt) {
                userHidden.value = opt.getAttribute('data-value');
                loadStats();
            }
        });

        // Job Search
        setupAutoClear(jobSearch, jobHidden, 'all');
        jobSearch?.addEventListener('input', function() {
            const opt = Array.from(document.getElementById('job-list').options).find(o => o.value === this.value);
            if (opt) {
                jobHidden.value = opt.getAttribute('data-value');
                if (cachedData) {
                    AnalysisGauges.update(cachedData.jobs, this.value);
                }
            }
        });

        // State Search (Isolated Pan Logic)
        stateSearch?.addEventListener('input', function() {
            const opt = Array.from(document.getElementById('state-list').options).find(o => o.value === this.value);
            
            // If text is cleared, reset to the Full US Map view
            if (this.value === "") {
                stateHidden.value = 'full';
                AnalysisMaps.initAndRender(cachedData.states, cachedData.counties, 'full');
            } else if (opt) {
                stateHidden.value = opt.getAttribute('data-value');
                // Trigger the map re-rendering with isolation filter
                AnalysisMaps.initAndRender(cachedData.states, cachedData.counties, stateHidden.value);
            }
        });
        setupAutoClear(stateSearch, stateHidden, 'full');
    }

    // --- 4. Data Orchestration ---

    /**
     * Loads available filter options (Users, Jobs, States) from the API
     */
    async function loadFilters() {
        try {
            const data = await StechAPI.request('get', '/api/analysis/filters');
            populateDatalist('user-list', data.users, 'displayname', 'uid');
            populateDatalist('job-list', data.jobs, 'job_name', 'job_id');
            populateDatalist('state-list', data.states, 'state_name', 'state_abbr');
        } catch (err) {
            console.error("Filter Load Error", err);
        }
    }

    function populateDatalist(id, items, textKey, valKey) {
        const list = document.getElementById(id);
        if (!list) return;
        list.innerHTML = items.map(i => `<option value="${i[textKey]}" data-value="${i[valKey]}"></option>`).join('');
    }

    /**
     * Fetches reporting data based on the current date range and user filters
     */
    async function loadStats() {
        const period = rangeSelect.value;
        let query = `?period=${period}&target_user=${userHidden.value || 'self'}`;
        
        if (period === 'custom') {
            const start = document.getElementById('analysis-start').value;
            const end = document.getElementById('analysis-end').value;
            query += `&start=${start}&end=${end}`;
        }

        try {
            const data = await StechAPI.request('get', '/api/analysis/stats' + query);
            cachedData = data;
            updateUI(data);
        } catch (err) { 
            console.error("Stats Load Error", err); 
        }
    }

    /**
     * Updates all UI components with the fresh data object
     */
    function updateUI(data) {
        // Overview Summary Cards
        document.getElementById('stat-total-hours').innerText = data.total_hours;
        document.getElementById('stat-reg-hours').innerText = data.stats.regular_hours;
        document.getElementById('stat-pto-hours').innerText = data.stats.pto_hours;
        document.getElementById('stat-overtime-hours').innerText = data.stats.overtime_hours || 0;

        // Travel Activity Summary - Explicit mapping from backend
        document.getElementById('val-total-miles').innerText = data.travel?.total_miles || 0;
        document.getElementById('val-per-diem').innerText = data.travel?.per_diem_days || 0;
        document.getElementById('val-overnight').innerText = data.travel?.overnight_stays || 0;
        
        // Formatted currency display for expenses
        const expenses = parseFloat(data.travel?.total_expenses || 0).toFixed(2);
        document.getElementById('val-expenses').innerText = '$' + expenses;

        // Visualizations
        AnalysisCharts.renderOverview(data.trend);
        AnalysisCharts.renderJobTable(data.jobs, data.total_hours);
        
        if (jobSearch) {
            AnalysisGauges.update(data.jobs, jobSearch.value || 'All Jobs');
        }
        
        // Maps: Pass state/county data and current filter for rendering
        AnalysisMaps.initAndRender(data.states, data.counties, stateHidden.value);
    }

    // --- 5. Global Event Listeners ---
    document.getElementById('btn-refresh-analysis')?.addEventListener('click', loadStats);
    
    rangeSelect.addEventListener('change', () => {
        const isCustom = rangeSelect.value === 'custom';
        document.getElementById('custom-date-inputs').classList.toggle('hidden', !isCustom);
        if (!isCustom) loadStats();
    });
});