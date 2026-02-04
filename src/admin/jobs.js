import { StechAPI } from '../api.js';

export const JobAdmin = {
    allJobs: [],

    async load() {
        this.allJobs = await StechAPI.request('get', '/api/admin/jobs');
        this.render();
    },

    render() {
        const term = (document.getElementById('job-search-input')?.value || '').toLowerCase();
        const status = document.querySelector('input[name="job-status"]:checked')?.value || 'active';
        const list = document.getElementById('job-list');
        if (!list) return;
        list.innerHTML = '';

        this.allJobs.filter(j => {
            const active = parseInt(j.job_archive) === 0;
            const matchesSearch = (j.job_name || '').toLowerCase().includes(term);
            const matchesStatus = (status === 'all' || (status === 'active' && active) || (status === 'archived' && !active));
            return matchesSearch && matchesStatus;
        }).forEach(j => {
            const active = parseInt(j.job_archive) === 0;
            const item = document.createElement('div');
            item.className = 'list-item';
            item.style.opacity = active ? '1' : '0.6';

            const isPto = parseInt(j.is_pto) === 1;
            const ptoTag = isPto ? '<span class="pto-tag" style="background: var(--color-primary); color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; margin-left: 8px; font-weight: bold;">PTO</span>' : '';

            item.innerHTML = `
                <span style="flex:1; cursor:pointer;">
                    ${j.job_name} ${ptoTag}
                </span>
                <label class="admin-switch">
                    <input type="checkbox" ${active ? 'checked' : ''}>
                    <span class="admin-slider"></span>
                </label>
            `;
            
            item.querySelector('span').addEventListener('click', () => this.edit(j));
            item.querySelector('input').addEventListener('change', () => this.toggleArchive(j.job_id));
            list.appendChild(item);
        });
    },

    edit(j) {
        document.getElementById('job-id').value = j.job_id;
        document.getElementById('job-name').value = j.job_name;
        // FIX: Populate description correctly
        document.getElementById('job-desc').value = j.job_description || '';
        
        // FIX: Populate Financials
        document.getElementById('job-revenue').value = j.job_revenue || '';
        document.getElementById('job-expense').value = j.job_expense_budget || '';
        document.getElementById('job-hourly').value = j.job_hourly_cost || '';

        // FIX: Ensure PTO toggle works
        document.getElementById('job-is-pto').checked = (parseInt(j.is_pto) === 1);

        document.getElementById('btn-save-job').innerText = "Update Job";
        document.getElementById('job-form-title').innerText = "Edit Job";
        document.getElementById('btn-cancel-job').classList.remove('hidden');
    },

    resetForm() {
        document.getElementById('form-job').reset();
        document.getElementById('job-id').value = '';
        document.getElementById('btn-save-job').innerText = "Create Job";
        document.getElementById('job-form-title').innerText = "Create Job";
        document.getElementById('btn-cancel-job').classList.add('hidden');
    },

    async submit(e) {
        e.preventDefault();
        
        const payload = {
            job_id: document.getElementById('job-id').value,
            job_name: document.getElementById('job-name').value,
            // FIX: Send Description
            job_description: document.getElementById('job-desc').value, 
            // FIX: Send PTO
            is_pto: document.getElementById('job-is-pto').checked ? 1 : 0,
            
            // FIX: Send Financials
            job_revenue: document.getElementById('job-revenue').value || 0,
            job_expense_budget: document.getElementById('job-expense').value || 0,
            job_hourly_cost: document.getElementById('job-hourly').value || 0
        };

        await StechAPI.request('post', '/api/admin/jobs', payload);
        this.resetForm();
        this.load();
    },

    async toggleArchive(id) {
        await StechAPI.request('post', `/api/admin/jobs/${id}/toggle`);
        this.load();
    }
};