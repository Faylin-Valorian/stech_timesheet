import { StechAPI } from '../../../api.js';
import { generateUrl } from '@nextcloud/router';

export const UserFeature = {
    allUsers: [],
    availableGroups: [],

    async init() {
        if (!document.getElementById('admin-user-settings')) return;
        
        // Load Data in Parallel
        const [users, groups, rules] = await Promise.all([
            this.fetchUsers(),
            this.fetchGroups(),
            this.fetchRules()
        ]);

        this.allUsers = users || [];
        this.availableGroups = groups || [];
        
        this.renderUserTable(this.allUsers);
        this.renderAccessControl(rules || {});
        
        this.setupSearch();
    },

    async fetchUsers() { return await StechAPI.request('get', '/api/admin/user/list'); },
    async fetchGroups() { return await StechAPI.request('get', '/api/admin/access/groups'); },
    async fetchRules() { return await StechAPI.request('get', '/api/admin/access/rules'); },

    // --- USER MANAGEMENT ---
    renderUserTable(users) {
        const tbody = document.getElementById('user-table-body');
        tbody.innerHTML = '';

        users.forEach(u => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div class="avatar-placeholder">${u.displayName.charAt(0)}</div>
                        <div>
                            <strong>${u.displayName}</strong><br>
                            <span style="font-size:0.8em; opacity:0.7;">${u.uid}</span>
                        </div>
                    </div>
                </td>
                <td>${u.email || '-'}</td>
                <td>${u.lastLogin ? new Date(u.lastLogin * 1000).toLocaleDateString() : 'Never'}</td>
                <td><span class="pill ${u.isEnabled ? 'success' : 'error'}">${u.isEnabled ? 'Active' : 'Disabled'}</span></td>
                <td class="text-right">
                    <button class="primary-button small btn-impersonate" title="View Timesheet">View</button>
                    <button class="icon-toggle btn-toggle-u" title="Toggle Access"></button>
                </td>
            `;

            row.querySelector('.btn-impersonate').onclick = () => this.impersonate(u.uid);
            row.querySelector('.btn-toggle-u').onclick = () => this.toggleUser(u.uid);
            
            tbody.appendChild(row);
        });
    },

    setupSearch() {
        document.getElementById('user-search').addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const filtered = this.allUsers.filter(u => 
                u.displayName.toLowerCase().includes(term) || 
                u.uid.toLowerCase().includes(term) ||
                (u.email && u.email.toLowerCase().includes(term))
            );
            this.renderUserTable(filtered);
        });
    },

    impersonate(uid) {
        // Set session storage for persistence across reloads
        sessionStorage.setItem('stech_impersonate', uid);
        // Redirect to main timesheet page
        window.location.href = generateUrl('/apps/stech_timesheet?target_user=' + uid);
    },

    async toggleUser(uid) {
        try {
            await StechAPI.request('post', '/api/admin/user/toggle', { uid });
            this.allUsers = await this.fetchUsers(); // Refresh data
            this.renderUserTable(this.allUsers);
        } catch (err) { console.error(err); }
    },

    // --- ACCESS CONTROL ---
    renderAccessControl(rules) {
        const container = document.getElementById('access-control-container');
        const permissions = [
            { key: 'admin_global_access', label: 'Global Admin Access' },
            { key: 'admin_payroll', label: 'Payroll Management' },
            { key: 'admin_holidays', label: 'Holiday Management' },
            { key: 'admin_locations', label: 'Location Settings' },
            { key: 'admin_jobs', label: 'Job Codes & Budgets' },
            { key: 'admin_users', label: 'User Management' },
            { key: 'analysis_view', label: 'View Analysis Dashboard' }
        ];

        container.innerHTML = '';

        permissions.forEach(perm => {
            const assigned = rules[perm.key] || [];
            
            const div = document.createElement('div');
            div.className = 'access-row';
            div.innerHTML = `
                <label>${perm.label}</label>
                <div class="multi-select-wrapper" id="ms-${perm.key}"></div>
            `;
            
            container.appendChild(div);
            
            // Render checkboxes for groups
            this.renderMultiSelect(div.querySelector('.multi-select-wrapper'), perm.key, assigned);
        });
    },

    renderMultiSelect(wrapper, ruleKey, assignedGroups) {
        // Simple checklist for now
        this.availableGroups.forEach(g => {
            const label = document.createElement('label');
            label.className = 'checkbox-inline';
            const isChecked = assignedGroups.includes(g.gid);
            
            label.innerHTML = `
                <input type="checkbox" value="${g.gid}" ${isChecked ? 'checked' : ''}>
                ${g.displayName}
            `;
            
            label.querySelector('input').addEventListener('change', () => {
                this.saveRule(ruleKey, wrapper);
            });
            
            wrapper.appendChild(label);
        });
    },

    async saveRule(key, wrapper) {
        const selected = [];
        wrapper.querySelectorAll('input:checked').forEach(cb => selected.push(cb.value));
        
        try {
            await StechAPI.request('post', '/api/admin/access/save', {
                rule_key: key,
                allowed_groups: selected
            });
        } catch (err) { console.error("Failed to save permission", err); }
    }
};