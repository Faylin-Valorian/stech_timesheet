import { StechAPI } from './api.js';
import { AnalysisCharts } from './analysis/charts.js';
import { AnalysisMaps } from './analysis/maps.js';
import { AnalysisGauges } from './analysis/gauges.js';

document.addEventListener('DOMContentLoaded', () => {
    // UI Elements
    const rangeSelect = document.getElementById('range-preset');
    
    // PATCH: New Simple Dropdown Logic
    const userSelect = document.getElementById('user-selector'); 
    const userHidden = document.getElementById('analysis-target-user');
    
    const jobHidden = document.getElementById('analysis-job-filter');
    const jobSearch = document.getElementById('job-search');
    
    // PATCH: Banner Elements
    const banner = document.getElementById('impersonation-banner');
    const bannerName = document.getElementById('impersonation-name');

    let cachedData = null;

    // --- 1. Initialization ---
    
    // Check Impersonation on Load
    const impersonateID = sessionStorage.getItem('stech_impersonate');
    if (impersonateID) {
        // Show banner immediately
        // PATCH: Ensure display matches flex style for centering
        if (banner) banner.style.display = 'flex'; 
        // Set hidden value to the impersonated ID (so "Myself" = "Impersonated User")
        userHidden.value = impersonateID; 
    } else {
        userHidden.value = 'self';
    }

    // PATCH: Listener for Close Impersonation
    document.getElementById('btn-end-impersonation-analysis')?.addEventListener('click', () => {
        sessionStorage.removeItem('stech_impersonate');
        // Update URL to remove parameter and reload
        const url = new URL(window.location.href);
        url.searchParams.delete('target_user');
        window.location.href = url.toString();
    });

    initTabs();
    initSearchBehaviors();
    loadFilters(); // Will fetch name for banner if needed
    loadStats();

    // --- 2. Tab Logic ---
    function initTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.tab-btn, .tab-pane').forEach(el => el.classList.remove('active'));
                e.target.classList.add('active');
                const targetId = e.target.dataset.tab;
                const targetPane = document.getElementById(targetId);
                if (targetPane) targetPane.classList.add('active');
                
                if (targetId === 'tab-travel' && cachedData) {
                    setTimeout(() => {
                        AnalysisMaps.refresh();
                        // Reset map to full US view whenever tab is clicked
                        AnalysisMaps.initAndRender(cachedData.states, cachedData.counties);
                    }, 150);
                }
            });
        });
    }

    // --- 3. Search & Filter Logic ---
    function initSearchBehaviors() {
        
        // PATCH: Simple User Dropdown Change Logic
        userSelect?.addEventListener('change', () => {
            const val = userSelect.value;
            if (val === 'self') {
                // If impersonating, use that ID. Otherwise, 'self'.
                userHidden.value = impersonateID || 'self';
            } else {
                // Everyone
                userHidden.value = 'all';
            }
            loadStats();
        });

        // Job Search (Keep Auto-Clear)
        const setupAutoClear = (inputEl, hiddenEl, defaultVal) => {
            if (!inputEl) return;
            inputEl.addEventListener('click', () => { if (inputEl.value !== '') inputEl.value = ''; });
            inputEl.addEventListener('change', () => {
                if (inputEl.value === '') {
                    if (defaultVal === 'all') inputEl.value = "All Jobs";
                    hiddenEl.value = defaultVal;
                    if (defaultVal === 'all' && cachedData) AnalysisGauges.update(cachedData.jobs, 'all');
                }
            });
        };

        setupAutoClear(jobSearch, jobHidden, 'all');
        jobSearch?.addEventListener('input', function() {
            if (this.value === "All Jobs") {
                jobHidden.value = 'all';
                if (cachedData) AnalysisGauges.update(cachedData.jobs, 'all');
                return;
            }
            const opt = Array.from(document.getElementById('job-list').options).find(o => o.value === this.value);
            if (opt) {
                jobHidden.value = opt.getAttribute('data-value');
                if (cachedData) AnalysisGauges.update(cachedData.jobs, this.value);
            }
        });
    }

    // --- 4. Data Loading ---
    async function loadFilters() {
        try {
            const data = await StechAPI.request('get', '/api/analysis/filters');
            
            // PATCH: Resolve Banner Name
            if (impersonateID) {
                const target = data.users.find(u => u.uid === impersonateID);
                if (target) bannerName.innerText = target.displayname;
                else bannerName.innerText = impersonateID;
            }

            // Populate Job List
            const jobList = document.getElementById('job-list');
            if (jobList) {
                let html = `<option value="All Jobs" data-value="all"></option>`;
                html += data.jobs.map(j => `<option value="${j.job_name}" data-value="${j.job_id}"></option>`).join('');
                jobList.innerHTML = html;
            }
        } catch (err) {
            console.error("Filter Load Error", err);
        }
    }

    async function loadStats() {
        const period = rangeSelect.value;
        let query = `?period=${period}&target_user=${userHidden.value}`;
        
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

    function updateUI(data) {
        document.getElementById('stat-total-hours').innerText = data.total_hours;
        document.getElementById('stat-reg-hours').innerText = data.stats.regular_hours;
        document.getElementById('stat-pto-hours').innerText = data.stats.pto_hours;
        document.getElementById('stat-overtime-hours').innerText = data.stats.overtime_hours || 0;

        document.getElementById('val-total-miles').innerText = Math.round(data.travel?.total_miles || 0);
        document.getElementById('val-per-diem').innerText = data.travel?.per_diem_days || 0;
        document.getElementById('val-overnight').innerText = data.travel?.overnight_stays || 0;
        document.getElementById('val-expenses').innerText = '$' + parseFloat(data.travel?.total_expenses || 0).toFixed(2);

        // Always render charts if containers exist (decoupled logic)
        if (document.getElementById('chart-daily')) {
            AnalysisCharts.renderOverview(data.trend);
        }
        
        if (document.getElementById('job-table-body')) {
            AnalysisCharts.renderJobTable(data.jobs, data.total_hours);
        }
        
        if (document.getElementById('chart-profitability-gauge')) {
            const currentJob = (jobSearch && jobSearch.value && jobSearch.value !== 'All Jobs') ? jobSearch.value : 'all';
            AnalysisGauges.update(data.jobs, currentJob);
        }
        
        // Render Single Map
        if (document.getElementById('map-main-container')) {
            AnalysisMaps.initAndRender(data.states, data.counties);
        }
    }

    document.getElementById('btn-refresh-analysis')?.addEventListener('click', loadStats);
    
    rangeSelect.addEventListener('change', () => {
        const isCustom = rangeSelect.value === 'custom';
        document.getElementById('custom-date-inputs').classList.toggle('hidden', !isCustom);
        if (!isCustom) loadStats();
    });
});