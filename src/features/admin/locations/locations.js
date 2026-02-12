import { StechAPI } from '../../../api.js';

export const LocationFeature = {
    selectedStateAbbr: null,

    async init() {
        if (!document.getElementById('admin-location-settings')) return;
        await this.loadStates();
    },

    async loadStates() {
        try {
            const states = await StechAPI.request('get', '/api/admin/location/states');
            this.renderStates(states);
        } catch (err) { console.error("Failed to load states", err); }
    },

    renderStates(states) {
        const tbody = document.getElementById('state-table-body');
        tbody.innerHTML = '';

        states.forEach(s => {
            const isEnabled = parseInt(s.is_enabled) === 1;
            const row = document.createElement('tr');
            row.className = this.selectedStateAbbr === s.state_abbr ? 'selected-row' : '';
            
            row.innerHTML = `
                <td><strong>${s.state_name} (${s.state_abbr})</strong></td>
                <td class="text-right">
                    <button class="icon-toggle btn-toggle-s ${isEnabled ? 'active' : ''}" title="Toggle State"></button>
                    <button class="icon-public btn-view-counties" title="View Counties"></button>
                </td>
            `;

            row.querySelector('.btn-view-counties').onclick = () => this.loadCounties(s.state_abbr, s.state_name);
            row.querySelector('.btn-toggle-s').onclick = (e) => {
                e.stopPropagation();
                this.toggleState(s.id);
            };

            tbody.appendChild(row);
        });
    },

    async loadCounties(abbr, name) {
        this.selectedStateAbbr = abbr;
        document.getElementById('county-list-title').innerText = `Counties in ${name}`;
        
        // Visual feedback: refresh state table to show selection
        this.loadStates();

        try {
            const counties = await StechAPI.request('get', `/api/admin/location/counties/${abbr}`);
            this.renderCounties(counties);
        } catch (err) { console.error("Failed to load counties", err); }
    },

    renderCounties(counties) {
        const tbody = document.getElementById('county-table-body');
        tbody.innerHTML = '';

        if (counties.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center">No counties found.</td></tr>';
            return;
        }

        counties.forEach(c => {
            const isEnabled = parseInt(c.is_enabled) === 1;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${c.county_name}</td>
                <td class="text-right">
                    <button class="icon-toggle btn-toggle-c ${isEnabled ? 'active' : ''}" title="Toggle County"></button>
                </td>
            `;

            row.querySelector('.btn-toggle-c').onclick = () => this.toggleCounty(c.id);
            tbody.appendChild(row);
        });
    },

    async toggleState(id) {
        try {
            await StechAPI.request('post', `/api/admin/location/state/toggle/${id}`);
            this.loadStates();
        } catch (err) { console.error("Toggle state failed", err); }
    },

    async toggleCounty(id) {
        try {
            await StechAPI.request('post', `/api/admin/location/county/toggle/${id}`);
            // Refresh current county list
            const currentTitle = document.getElementById('county-list-title').innerText;
            const stateName = currentTitle.replace('Counties in ', '');
            this.loadCounties(this.selectedStateAbbr, stateName);
        } catch (err) { console.error("Toggle county failed", err); }
    }
};