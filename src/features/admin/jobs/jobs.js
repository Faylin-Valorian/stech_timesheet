import { StechAPI } from '../../../api.js';

export const JobFeature = {
    async init() {
        if (!document.getElementById('admin-job-settings')) return;
        
        this.setupListeners();
        await this.loadJobs();
    },

    async loadJobs() {
        try {
            const jobs = await StechAPI.request('get', '/api/admin/job');
            this.renderTable(jobs);
        } catch (err) {
            console.error("Failed to load jobs", err);
        }
    },

    renderTable(jobs) {
        const tbody = document.getElementById('job-table-body');
        tbody.innerHTML = '';

        jobs.forEach(j => {
            const isArchived = parseInt(j.job_archive) === 1;
            const isPto = parseInt(j.is_pto) === 1;
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${j.job_name}</strong></td>
                <td>$${parseFloat(j.job_revenue || 0).toFixed(2)}</td>
                <td>$${parseFloat(j.job_expense_budget || 0).toFixed(2)}</td>
                <td>$${parseFloat(j.job_hourly_cost || 0).toFixed(2)}</td>
                <td>${isPto ? '<span class="pill info">PTO</span>' : '<span class="pill">Billable</span>'}</td>
                <td><span class="pill ${isArchived ? 'error' : 'success'}">${isArchived ? 'Archived' : 'Active'}</span></td>
                <td class="text-right">
                    <button class="icon-edit btn-edit-j" title="Edit"></button>
                    <button class="icon-toggle btn-toggle-j" title="Archive/Restore"></button>
                </td>
            `;
            
            row.querySelector('.btn-edit-j').onclick = () => this.openModal(j);
            row.querySelector('.btn-toggle-j').onclick = () => this.toggleJob(j.job_id);
            
            tbody.appendChild(row);
        });
    },

    setupListeners() {
        document.getElementById('btn-add-job').onclick = () => this.openModal();
        document.querySelector('.close-job-modal').onclick = () => this.closeModal();
        document.getElementById('btn-save-job-exec').onclick = () => this.save();
    },

    openModal(j = null) {
        document.getElementById('j-id').value = j ? j.job_id : '';
        document.getElementById('j-name').value = j ? j.job_name : '';
        document.getElementById('j-revenue').value = j ? j.job_revenue : 0;
        document.getElementById('j-expense').value = j ? j.job_expense_budget : 0;
        document.getElementById('j-hourly').value = j ? j.job_hourly_cost : 0;
        document.getElementById('j-pto').checked = j ? (parseInt(j.is_pto) === 1) : false;
        
        document.getElementById('modal-job').style.display = 'flex';
    },

    closeModal() {
        document.getElementById('modal-job').style.display = 'none';
    },

    async save() {
        const data = {
            job_id: document.getElementById('j-id').value,
            job_name: document.getElementById('j-name').value,
            job_revenue: document.getElementById('j-revenue').value,
            job_expense_budget: document.getElementById('j-expense').value,
            job_hourly_cost: document.getElementById('j-hourly').value,
            is_pto: document.getElementById('j-pto').checked
        };

        try {
            await StechAPI.request('post', '/api/admin/job', data);
            this.closeModal();
            this.loadJobs();
        } catch (err) { console.error("Save failed", err); }
    },

    async toggleJob(id) {
        try {
            await StechAPI.request('post', `/api/admin/job/toggle/${id}`);
            this.loadJobs();
        } catch (err) { console.error("Toggle failed", err); }
    }
};