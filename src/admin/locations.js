import { StechAPI } from '../api.js';

export const LocationAdmin = {
    allStates: [],
    currentCounties: [],
    selectedStateAbbr: null,

    async loadStates() {
        this.allStates = await StechAPI.request('get', '/api/admin/states');
        this.renderStates();
    },

    renderStates() {
        const term = (document.getElementById('state-search-input')?.value || '').toLowerCase();
        const status = document.querySelector('input[name="state-status"]:checked')?.value || 'enabled';
        const list = document.getElementById('state-list');
        if (!list) return;
        list.innerHTML = '';

        this.allStates.filter(s => {
            const en = s.is_enabled == 1;
            const matchesSearch = (s.state_name || '').toLowerCase().includes(term);
            const matchesStatus = (status === 'all' || (status === 'enabled' && en) || (status === 'disabled' && !en));
            return matchesSearch && matchesStatus;
        }).forEach(s => {
            const item = document.createElement('div');
            item.className = `list-item ${s.state_abbr === this.selectedStateAbbr ? 'active-selection' : ''}`;
            item.innerHTML = `
                <span style="flex:1; cursor:pointer;">${s.state_name}</span>
                <label class="admin-switch"><input type="checkbox" ${s.is_enabled == 1 ? 'checked' : ''}><span class="admin-slider"></span></label>
            `;
            item.querySelector('span').addEventListener('click', () => this.selectState(s));
            item.querySelector('input').addEventListener('change', () => this.toggleState(s.id));
            list.appendChild(item);
        });
    },

    async selectState(s) {
        this.selectedStateAbbr = s.state_abbr;
        this.renderStates();
        document.getElementById('county-header').innerText = 'Counties: ' + s.state_name;
        document.getElementById('county-search-input').disabled = false;
        this.loadCounties(s.state_abbr);
    },

    async loadCounties(abbr) {
        this.currentCounties = await StechAPI.request('get', `/api/admin/counties/${abbr}`);
        this.renderCounties();
    },

    renderCounties() {
        const term = (document.getElementById('county-search-input')?.value || '').toLowerCase();
        const status = document.querySelector('input[name="county-status"]:checked')?.value || 'enabled';
        const list = document.getElementById('county-list');
        if (!list) return;
        list.innerHTML = '';

        this.currentCounties.filter(c => {
            const en = c.is_enabled == 1;
            const matchesSearch = (c.county_name || '').toLowerCase().includes(term);
            const matchesStatus = (status === 'all' || (status === 'enabled' && en) || (status === 'disabled' && !en));
            return matchesSearch && matchesStatus;
        }).forEach(c => {
            const item = document.createElement('div');
            item.className = 'list-item';
            item.innerHTML = `
                <span style="flex:1;">${c.county_name}</span>
                <label class="admin-switch"><input type="checkbox" ${c.is_enabled == 1 ? 'checked' : ''}><span class="admin-slider"></span></label>
            `;
            item.querySelector('input').addEventListener('change', () => this.toggleCounty(c.id));
            list.appendChild(item);
        });
    },

    async toggleState(id) {
        await StechAPI.request('post', `/api/admin/states/${id}/toggle`);
        this.loadStates();
    },

    async toggleCounty(id) {
        await StechAPI.request('post', `/api/admin/counties/${id}/toggle`);
        if (this.selectedStateAbbr) this.loadCounties(this.selectedStateAbbr);
    }
};