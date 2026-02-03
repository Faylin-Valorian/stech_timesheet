import { StechAPI } from '../api.js';

export const HolidayAdmin = {
    allHolidays: [],

    async load() {
        this.allHolidays = await StechAPI.request('get', '/api/admin/holidays');
        this.render();
    },

    render() {
        const term = (document.getElementById('holiday-search-input')?.value || '').toLowerCase();
        const status = document.querySelector('input[name="holiday-status"]:checked')?.value || 'active';
        const list = document.getElementById('holiday-list');
        if (!list) return;
        list.innerHTML = '';

        this.allHolidays.filter(h => {
            const active = (h.holiday_archive == 0 || h.holiday_archive == null);
            const matchesSearch = (h.holiday_name || '').toLowerCase().includes(term);
            const matchesStatus = (status === 'all' || (status === 'active' && active) || (status === 'archived' && !active));
            return matchesSearch && matchesStatus;
        }).forEach(h => {
            const active = (h.holiday_archive == 0 || h.holiday_archive == null);
            const item = document.createElement('div');
            item.className = 'list-item';
            item.style.opacity = active ? '1' : '0.6';
            item.innerHTML = `
                <span style="flex:1; cursor:pointer;"><strong>${h.holiday_name}</strong><br><small>${h.holiday_start_date}</small></span>
                <label class="admin-switch">
                    <input type="checkbox" ${active ? 'checked' : ''}>
                    <span class="admin-slider"></span>
                </label>
            `;
            item.querySelector('span').addEventListener('click', () => this.edit(h));
            item.querySelector('input').addEventListener('change', () => this.toggleArchive(h.holiday_id));
            list.appendChild(item);
        });
    },

    edit(h) {
        document.getElementById('holiday-id').value = h.holiday_id;
        document.getElementById('holiday-name').value = h.holiday_name;
        document.getElementById('holiday-start').value = h.holiday_start_date;
        document.getElementById('holiday-end').value = h.holiday_end_date;
        document.getElementById('holiday-bg').value = h.holiday_bg || ''; 
        document.getElementById('btn-save-holiday').innerText = "Update Holiday";
        document.getElementById('holiday-form-title').innerText = "Edit Holiday";
        document.getElementById('btn-cancel-holiday').classList.remove('hidden');
    },

    resetForm() {
        document.getElementById('form-holiday').reset();
        document.getElementById('holiday-id').value = '';
        document.getElementById('btn-save-holiday').innerText = "Add Holiday";
        document.getElementById('holiday-form-title').innerText = "Add Holiday";
        document.getElementById('btn-cancel-holiday').classList.add('hidden');
    },

    async submit(e) {
        e.preventDefault();
        const payload = {
            id: document.getElementById('holiday-id').value,
            name: document.getElementById('holiday-name').value,
            start: document.getElementById('holiday-start').value,
            end: document.getElementById('holiday-end').value,
            bg_style: document.getElementById('holiday-bg').value 
        };
        await StechAPI.request('post', '/api/admin/holidays', payload);
        this.resetForm();
        this.load();
    },

    async toggleArchive(id) {
        await StechAPI.request('post', `/api/admin/holidays/${id}/toggle`);
        this.load();
    }
};