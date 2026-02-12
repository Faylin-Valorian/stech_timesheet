import { StechAPI } from '../../../api.js';

export const HolidayFeature = {
    async init() {
        if (!document.getElementById('admin-holiday-settings')) return;
        
        this.setupListeners();
        await this.loadHolidays();
    },

    async loadHolidays() {
        try {
            const holidays = await StechAPI.request('get', '/api/admin/holiday');
            this.renderTable(holidays);
        } catch (err) {
            console.error("Failed to load holidays", err);
        }
    },

    renderTable(holidays) {
        const tbody = document.getElementById('holiday-table-body');
        tbody.innerHTML = '';

        holidays.forEach(h => {
            const isArchived = parseInt(h.holiday_archive) === 1;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${h.holiday_name}</strong></td>
                <td>${h.holiday_start_date}</td>
                <td>${h.holiday_end_date}</td>
                <td><span class="pill ${isArchived ? 'error' : 'success'}">${isArchived ? 'Inactive' : 'Active'}</span></td>
                <td class="text-right">
                    <button class="icon-edit btn-edit-h" data-id="${h.holiday_id}" title="Edit"></button>
                    <button class="icon-toggle btn-toggle-h" data-id="${h.holiday_id}" title="Toggle Active/Inactive"></button>
                    <button class="icon-delete btn-delete-h" data-id="${h.holiday_id}" title="Delete Permanently"></button>
                </td>
            `;
            
            // Inline listeners for modularity
            row.querySelector('.btn-edit-h').onclick = () => this.openModal(h);
            row.querySelector('.btn-toggle-h').onclick = () => this.toggleStatus(h.holiday_id);
            row.querySelector('.btn-delete-h').onclick = () => this.deleteHoliday(h.holiday_id);
            
            tbody.appendChild(row);
        });
    },

    setupListeners() {
        document.getElementById('btn-add-holiday').onclick = () => this.openModal();
        document.querySelector('.close-holiday-modal').onclick = () => this.closeModal();
        document.getElementById('btn-save-holiday-exec').onclick = () => this.save();
    },

    openModal(h = null) {
        document.getElementById('h-id').value = h ? h.holiday_id : '';
        document.getElementById('h-name').value = h ? h.holiday_name : '';
        document.getElementById('h-start').value = h ? h.holiday_start_date : '';
        document.getElementById('h-end').value = h ? h.holiday_end_date : '';
        document.getElementById('h-bg').value = h ? h.holiday_bg : '#e67e22';
        document.getElementById('modal-holiday').style.display = 'flex';
    },

    closeModal() {
        document.getElementById('modal-holiday').style.display = 'none';
    },

    async save() {
        const data = {
            id: document.getElementById('h-id').value,
            name: document.getElementById('h-name').value,
            start: document.getElementById('h-start').value,
            end: document.getElementById('h-end').value,
            bg: document.getElementById('h-bg').value
        };

        try {
            await StechAPI.request('post', '/api/admin/holiday', data);
            this.closeModal();
            this.loadHolidays();
        } catch (err) { console.error("Save failed", err); }
    },

    async toggleStatus(id) {
        try {
            await StechAPI.request('post', `/api/admin/holiday/toggle/${id}`);
            this.loadHolidays();
        } catch (err) { console.error("Toggle failed", err); }
    },

    async deleteHoliday(id) {
        if (!confirm("Are you sure you want to permanently delete this holiday?")) return;
        try {
            await StechAPI.request('delete', `/api/admin/holiday/${id}`);
            this.loadHolidays();
        } catch (err) { console.error("Delete failed", err); }
    }
};