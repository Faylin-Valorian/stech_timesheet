import { StechAPI } from '../../../api.js';
import { OverviewFeature } from '../overview/overview.js';
import { TravelFeature } from '../travel/travel.js';
import { JobBreakdownFeature } from '../jobbreakdown/jobbreakdown.js';
import { JobProfitabilityFeature } from '../jobprofitability/jobprofitability.js';

export const DashboardFeature = {
    currentTab: 'overview',
    
    init() {
        if (!document.querySelector('.analysis-container')) return;

        this.setupFilters();
        this.setupTabs();
        this.checkImpersonation();
        
        // Initial Load
        this.triggerLoad();
    },

    setupFilters() {
        // Date Range Toggle
        const rangeSelect = document.getElementById('range-preset');
        rangeSelect.addEventListener('change', () => {
            const isCustom = rangeSelect.value === 'custom';
            document.getElementById('custom-date-inputs').classList.toggle('hidden', !isCustom);
        });

        // Update Button
        document.getElementById('btn-refresh-analysis').addEventListener('click', () => this.triggerLoad());
    },

    setupTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                // UI Toggle
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                
                e.target.classList.add('active');
                const tabId = e.target.dataset.tab; // 'overview', 'travel', 'breakdown', 'profitability'
                
                const pane = document.getElementById(`tab-${tabId}`);
                if (pane) pane.classList.add('active');
                
                this.currentTab = tabId;
                
                // Specific resizing triggers for Charts/Maps
                if (tabId === 'travel') TravelFeature.resizeMap();
                
                this.triggerLoad();
            });
        });
    },

    checkImpersonation() {
        const impID = sessionStorage.getItem('stech_impersonate');
        if (impID) {
            document.getElementById('impersonation-banner').classList.remove('hidden');
            document.getElementById('impersonation-name').innerText = impID;
            
            // Sync dropdown if exists
            const userSel = document.getElementById('user-selector');
            if (userSel) userSel.value = 'self'; // Logic handled in getParams
            
            document.getElementById('btn-end-impersonation').onclick = () => {
                sessionStorage.removeItem('stech_impersonate');
                window.location.reload();
            };
        }
    },

    getParams() {
        const range = document.getElementById('range-preset').value;
        const userSel = document.getElementById('user-selector');
        
        let target = 'self';
        // If admin selector exists, use it. If 'self' AND impersonating, use impersonation ID.
        if (userSel) {
            if (userSel.value === 'all') target = 'all';
            else target = sessionStorage.getItem('stech_impersonate') || 'self';
        } else {
            target = sessionStorage.getItem('stech_impersonate') || 'self';
        }

        let params = `period=${range}&target_user=${target}`;
        if (range === 'custom') {
            params += `&start=${document.getElementById('analysis-start').value}`;
            params += `&end=${document.getElementById('analysis-end').value}`;
        }
        return params;
    },

    triggerLoad() {
        const params = this.getParams();

        switch(this.currentTab) {
            case 'overview': OverviewFeature.load(params); break;
            case 'travel': TravelFeature.load(params); break;
            case 'breakdown': JobBreakdownFeature.load(params); break;
            case 'profitability': JobProfitabilityFeature.load(params); break;
        }
    }
};