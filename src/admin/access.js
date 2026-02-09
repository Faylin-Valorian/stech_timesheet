import { StechAPI } from '../api.js';

export const AccessAdmin = {
    groups: [],
    rules: {},

    /**
     * Load Groups and existing Access Rules from the server
     */
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
            if (window.OCP && window.OCP.Toast) {
                window.OCP.Toast.error('Failed to load access control settings.');
            }
        }
    },

    /**
     * Render the checkboxes for each access section
     */
    render() {
        // Define the mapping between your HTML container IDs and the Database Rule Keys
        const mapping = {
            // General Admin Access
            'list-access-admin-global': 'admin_global_access',
            'list-access-archive': 'view_archive_toggle',

            // Admin Sidebar Permissions
            'list-access-admin-access': 'admin_access',
            'list-access-admin-users': 'admin_users',
            'list-access-admin-payroll': 'admin_payroll',
            'list-access-admin-holidays': 'admin_holidays',
            'list-access-admin-jobs': 'admin_jobs',
            'list-access-admin-locations': 'admin_locations',

            // Analysis Features
            'list-access-analysis-tab': 'analysis_tab',
            'list-access-analysis-others': 'analysis_view_others',
            'list-access-analysis-travel': 'analysis_travel',
            'list-access-analysis-financial': 'analysis_financial',
            'list-access-analysis-location': 'analysis_location',
            'list-access-analysis-jobs': 'analysis_job_breakdown'
        };

        // Loop through each section and render groups
        for (const [elementId, ruleKey] of Object.entries(mapping)) {
            const container = document.getElementById(elementId);
            if (!container) continue; // Skip if element doesn't exist in HTML

            container.innerHTML = ''; // Clear current list
            const allowedGroups = this.rules[ruleKey] || [];

            if (this.groups.length === 0) {
                container.innerHTML = '<div style="opacity:0.6; padding:10px;">No groups found in Nextcloud.</div>';
                continue;
            }

            this.groups.forEach(group => {
                const gid = group.gid;
                const name = group.displayName;

                // Identify Admin group
                const isAdmin = (gid === 'admin');

                // Admin is ALWAYS visually checked
                const isChecked = isAdmin || allowedGroups.includes(gid);
                
                const div = document.createElement('div');
                div.className = 'toggle-wrapper';
                
                // Add locked styling and disabled state for Admin
                div.innerHTML = `
                    <label class="toggle-button" style="display:flex; justify-content:space-between; align-items:center; ${isAdmin ? 'cursor:not-allowed; opacity:0.9;' : ''}">
                        <span>${name} ${isAdmin ? '<small>(Locked)</small>' : ''}</span>
                        <input type="checkbox" 
                               class="toggle-checkbox" 
                               data-gid="${gid}"
                               ${isChecked ? 'checked' : ''}
                               ${isAdmin ? 'disabled' : ''}>
                    </label>
                `;

                // Only add click listener if NOT admin (prevent changes)
                if (!isAdmin) {
                    const checkbox = div.querySelector('input');
                    checkbox.addEventListener('change', (e) => {
                        this.toggleRule(ruleKey, gid, e.target.checked);
                        
                        // Visual feedback
                        const btn = div.querySelector('.toggle-button');
                        if(e.target.checked) {
                            btn.style.backgroundColor = 'var(--color-primary-element)';
                            btn.style.color = 'var(--color-primary-text)';
                        } else {
                            btn.style.backgroundColor = '';
                            btn.style.color = '';
                        }
                    });
                }

                // Set initial visual state
                const btn = div.querySelector('.toggle-button');
                if(isChecked) {
                    btn.style.backgroundColor = 'var(--color-primary-element)';
                    btn.style.color = 'var(--color-primary-text)';
                }

                container.appendChild(div);
            });
        }
    },

    /**
     * Save the change immediately when clicked
     */
    async toggleRule(ruleKey, gid, isAdded) {
        let currentList = this.rules[ruleKey] || [];

        // 1. Handle the specific group toggle
        if (isAdded) {
            if (!currentList.includes(gid)) {
                currentList.push(gid);
            }
        } else {
            currentList = currentList.filter(g => g !== gid);
        }

        // 2. CRITICAL FIX: Ensure 'admin' is ALWAYS in the list
        // Even if the user didn't click admin, we force it here before saving.
        if (!currentList.includes('admin')) {
            currentList.push('admin');
        }

        // Update local state
        this.rules[ruleKey] = currentList;

        // Send to backend
        try {
            await StechAPI.saveAccessRule(ruleKey, currentList);
        } catch (e) {
            console.error('Save failed', e);
            if (window.OCP && window.OCP.Toast) window.OCP.Toast.error('Error saving rule');
        }
    }
};