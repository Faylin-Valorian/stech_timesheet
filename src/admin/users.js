import { StechAPI } from '../api.js';

export const UserAdmin = {
    allUsers: [],

    async load() {
        this.allUsers = await StechAPI.request('get', '/api/admin/users');
        this.render();
    },

    render() {
        const term = (document.getElementById('user-search-input')?.value || '').toLowerCase();
        const status = document.querySelector('input[name="user-status"]:checked')?.value || 'active';
        const container = document.getElementById('user-grid-container');
        if (!container) return;
        container.innerHTML = '';

        const filtered = this.allUsers.filter(u => {
            const matchesSearch = (u.displayname || '').toLowerCase().includes(term) || (u.email || '').toLowerCase().includes(term);
            const isActive = u.is_active === 1;
            const matchesStatus = (status === 'all' || (status === 'active' && isActive) || (status === 'inactive' && !isActive));
            return matchesSearch && matchesStatus;
        });

        filtered.forEach(u => {
            const card = document.createElement('div');
            card.className = `user-card ${u.is_active === 0 ? 'inactive' : ''}`;
            card.innerHTML = `
                <div class="user-avatar-placeholder">${(u.displayname || '?').substring(0,2).toUpperCase()}</div>
                <div class="user-info">
                    <div class="user-name">${u.displayname}</div>
                    <div class="user-email">${u.email || ''}</div>
                </div>
                <div class="user-actions">
                    <label class="admin-switch"><input type="checkbox" ${u.is_active === 1 ? 'checked' : ''}><span class="admin-slider"></span></label>
                </div>
            `;
            card.querySelector('input').addEventListener('change', () => this.toggleStatus(u.uid));
            card.addEventListener('click', (e) => {
                if (e.target.closest('.admin-switch')) return;
                window.location.href = OC.generateUrl('/apps/stech_timesheet/') + '?target_user=' + u.uid;
            });
            container.appendChild(card);
        });
    },

    async toggleStatus(uid) {
        const res = await StechAPI.request('post', '/api/admin/users/toggle', { uid });
        const user = this.allUsers.find(u => u.uid === uid);
        if (user) user.is_active = res.new_state;
        this.render();
    }
};