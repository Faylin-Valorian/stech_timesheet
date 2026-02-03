import { StechAPI } from '../api.js';

export const AccessAdmin = {
    systemGroups: [],
    accessRules: {},

    /**
     * Load group and rule data from the API
     */
    async load() {
        try {
            const [groups, rules] = await Promise.all([
                StechAPI.request('get', '/api/admin/groups'),
                StechAPI.request('get', '/api/admin/access')
            ]);
            this.systemGroups = groups || [];
            this.accessRules = rules || {};
            
            // Re-render all 6 access toggle sections
            const ruleKeys = [
                'admin_panel', 'analysis_tab', 'analysis_view_others', 
                'analysis_travel', 'analysis_financial', 'analysis_location'
            ];
            ruleKeys.forEach(key => this.renderToggles(`list-${key}-groups`, key));
        } catch (err) {
            console.error("Access Control Load Error:", err);
        }
    },

    /**
     * Renders the toggle switches for a specific rule category
     */
    renderToggles(containerId, ruleKey) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '';
        
        const allowed = this.accessRules[ruleKey] || [];

        this.systemGroups.forEach(group => {
            const isAdmin = group.toLowerCase() === 'admin';
            const row = document.createElement('div');
            row.className = 'list-item';
            row.innerHTML = `
                <span style="flex:1; font-weight:bold;">${group} ${isAdmin ? '<span style="opacity:0.5; font-size:0.85em;">(Owner)</span>' : ''}</span>
                <label class="admin-switch">
                    <input type="checkbox" value="${group}" ${allowed.includes(group) || isAdmin ? 'checked' : ''} ${isAdmin ? 'disabled' : ''}>
                    <span class="admin-slider"></span>
                </label>
            `;
            // Save immediately on change
            row.querySelector('input').addEventListener('change', () => this.save(ruleKey, containerId));
            container.appendChild(row);
        });
    },

    /**
     * Sends the updated group list to the server
     */
    async save(ruleKey, containerId) {
        const container = document.getElementById(containerId);
        const selected = Array.from(container.querySelectorAll('input:checked')).map(cb => cb.value);

        try {
            await StechAPI.request('post', '/api/admin/access', {
                rule_key: ruleKey,
                allowed_groups: selected
            });
        } catch (err) {
            OC.dialogs.error('Failed to update access rules.', 'Permission Error');
        }
    }
};