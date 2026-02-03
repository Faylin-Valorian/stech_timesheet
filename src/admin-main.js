import { StechAPI } from './api.js';
import { AnalysisCharts } from './analysis/charts.js';
import { AnalysisMaps } from './analysis/maps.js';
import { AnalysisGauges } from './analysis/gauges.js';

document.addEventListener('DOMContentLoaded', () => {
    const rangeSelect = document.getElementById('range-preset');
    const userHidden = document.getElementById('analysis-target-user');
    const userSearch = document.getElementById('user-search');
    const jobHidden = document.getElementById('analysis-job-filter');
    const jobSearch = document.getElementById('job-search');
    const stateSearch = document.getElementById('state-search');
    const stateHidden = document.getElementById('analysis-state-filter');

    let cachedData = null;

    initTabs();
    initSearchBehaviors();
    loadFilters();
    loadStats();

    function initTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.tab-btn, .tab-pane').forEach(el => el.classList.remove('active'));
                e.target.classList.add('active');
                const targetId = e.target.dataset.tab;
                document.getElementById(targetId).classList.add('active');
                
                if (targetId === 'tab-travel') {
                    AnalysisMaps.refresh('state');
                    AnalysisMaps.refresh('county');
                }
            });
        });
    }

    function initSearchBehaviors() {
        const setupAutoClear = (inputEl, hiddenEl, defaultVal) => {
            if (!inputEl) return;
            inputEl.addEventListener('click', () => { if (inputEl.value !== '') inputEl.value = ''; });
        };

        setupAutoClear(userSearch, userHidden, 'self');
        userSearch?.addEventListener('input', function() {
            const opt = Array.from(document.getElementById('user-list').options).find(o => o.value === this.value);
            if (opt) {
                userHidden.value = opt.getAttribute('data-value');
                loadStats();
            }
        });

        setupAutoClear(jobSearch, jobHidden, 'all');
        jobSearch?.addEventListener('input', function() {
            const opt = Array.from(document.getElementById('job-list').options).find(o => o.value === this.value);
            if (opt) {
                jobHidden.value = opt.getAttribute('data-value');
                if (cachedData) AnalysisGauges.update(cachedData.jobs, this.value);
            }
        });

        // Trigger isolation logic when state is selected
        stateSearch?.addEventListener('input', function() {
            const opt = Array.from(document.getElementById('state-list').options).find(o => o.value === this.value);
            if (opt) {
                stateHidden.value = opt.getAttribute('data-value');
                AnalysisMaps.initAndRender(cachedData.states, cachedData.counties, stateHidden.value);
            }
        });
        setupAutoClear(stateSearch, stateHidden, 'full');
    }

    async function loadFilters() {
        const data = await StechAPI.request('get', '/api/analysis/filters');
        populateDatalist('user-list', data.users, 'displayname', 'uid');
        populateDatalist('job-list', data.jobs, 'job_name', 'job_id');
        populateDatalist('state-list', data.states, 'state_name', 'state_abbr');
    }

    function populateDatalist(id, items, textKey, valKey) {
        const list = document.getElementById(id);
        if (!list) return;
        list.innerHTML = items.map(i => `<option value="${i[textKey]}" data-value="${i[valKey]}"></option>`).join('');
    }

    async function loadStats() {
        const period = rangeSelect.value;
        let query = `?period=${period}&target_user=${userHidden.value || 'self'}`;
        if (period === 'custom') {
            query += `&start=${document.getElementById('analysis-start').value}&end=${document.getElementById('analysis-end').value}`;
        }

        try {
            const data = await StechAPI.request('get', '/api/analysis/stats' + query);
            cachedData = data;
            updateUI(data);
        } catch (err) { console.error("Stats Load Error", err); }
    }

    function updateUI(data) {
        document.getElementById('stat-total-hours').innerText = data.total_hours;
        document.getElementById('stat-reg-hours').innerText = data.stats.regular_hours;
        document.getElementById('stat-pto-hours').innerText = data.stats.pto_hours;
        document.getElementById('stat-overtime-hours').innerText = data.stats.overtime_hours || 0;

        // Populate Travel values from the data.travel object
        document.getElementById('val-total-miles').innerText = data.travel?.total_miles || 0;
        document.getElementById('val-per-diem').innerText = data.travel?.per_diem_days || 0;
        document.getElementById('val-overnight').innerText = data.travel?.overnight_stays || 0;
        document.getElementById('val-expenses').innerText = '$' + (data.travel?.total_expenses || '0.00');

        AnalysisCharts.renderOverview(data.trend);
        AnalysisCharts.renderJobTable(data.jobs, data.total_hours);
        AnalysisGauges.update(data.jobs, jobSearch?.value || 'All Jobs');
        
        // Pass data to maps with isolation support
        AnalysisMaps.initAndRender(data.states, data.counties, stateHidden.value);
    }

    document.getElementById('btn-refresh-analysis')?.addEventListener('click', loadStats);
    rangeSelect.addEventListener('change', () => {
        document.getElementById('custom-date-inputs').classList.toggle('hidden', rangeSelect.value !== 'custom');
        if (rangeSelect.value !== 'custom') loadStats();
    });
});