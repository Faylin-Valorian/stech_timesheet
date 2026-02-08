import { StechAPI } from '../api.js';

export const AccessAdmin = {
    groups: [],
    rules: {},

    async load() {
        try {
            const [groups, rules] = await Promise.all([
                StechAPI.getAdminGroups(),
                StechAPI.getAdminAccess()
            ]);
            this.groups = groups;
            this.rules = rules;
            this.render();
        } catch (e) {
            console.error('Error loading access data', e);
        }
    },

    render() {
        // STRICT MAPPING to HTML IDs
        const mapping = {
            // Main Page
            'list-access-admin-global': 'admin_global_access',
            'list-access-analysis-tab': 'analysis_tab',
            'list-access-archive': 'view_archive_toggle', // Assuming you create this container

            // Admin Sidebar
            'list-access-admin-users': 'admin_users',
            'list-access-admin-payroll': 'admin_payroll',
            'list-access-admin-holidays': 'admin_holidays',
            'list-access-admin-jobs': 'admin_jobs',
            'list-access-admin-locations': 'admin_locations',
            'list-access-admin-access': 'admin_access',

            // Analysis Features
            'list-access-analysis-others': 'analysis_view_others',
            'list-access-analysis-travel': 'analysis_travel',
            'list-access-analysis-financial': 'analysis_financial',
            'list-access-analysis-location': 'analysis_location'
        };

        for (const [elementId, ruleKey] of Object.entries(mapping)) {
            const container = document.getElementById(elementId);
            if (!container) continue; 

            container.innerHTML = ''; 
            const allowedGroups = this.rules[ruleKey] || [];

            this.groups.forEach(group => {
                const gid = group.gid;
                const isAdmin = (gid === 'admin');
                const isChecked = isAdmin || allowedGroups.includes(gid);
                
                const div = document.createElement('div');
                div.className = 'toggle-wrapper';
                div.innerHTML = `
                    <label class="toggle-button" style="${isAdmin ? 'opacity:0.7; pointer-events:none;' : ''}">
                        <span>${group.displayName}</span>
                        <input type="checkbox" ${isChecked ? 'checked' : ''} ${isAdmin ? 'disabled' : ''}>
                    </label>
                `;

                if (!isAdmin) {
                    const checkbox = div.querySelector('input');
                    checkbox.addEventListener('change', (e) => {
                        this.toggleRule(ruleKey, gid, e.target.checked);
                        this.updateVisual(div.querySelector('.toggle-button'), e.target.checked);
                    });
                }
                this.updateVisual(div.querySelector('.toggle-button'), isChecked);
                container.appendChild(div);
            });
        }
    },

    updateVisual(btn, checked) {
        if(checked) {
            btn.style.backgroundColor = 'var(--color-primary-element)';
            btn.style.color = 'var(--color-primary-text)';
        } else {
            btn.style.backgroundColor = '';
            btn.style.color = '';
        }
    },

    async toggleRule(ruleKey, gid, isAdded) {
        let currentList = this.rules[ruleKey] || [];
        if (isAdded) {
            if (!currentList.includes(gid)) currentList.push(gid);
        } else {
            currentList = currentList.filter(g => g !== gid);
        }
        this.rules[ruleKey] = currentList;
        await StechAPI.saveAccessRule(ruleKey, currentList);
    }
};