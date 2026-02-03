import { StechAPI } from '../api.js';

export const PayrollAdmin = {
    async load() {
        try {
            const settings = await StechAPI.request('get', '/api/admin/settings');
            document.getElementById('pay-frequency').value = settings['pay_frequency'] || 14;
            document.getElementById('pay-start-date').value = settings['pay_start_date'] || '2026-01-07';
            document.getElementById('pay-bg-style').value = settings['pay_bg_style'] || '';
        } catch (err) {
            console.error("Failed to load payroll settings", err);
        }
    },

    async save() {
        const data = {
            pay_frequency: document.getElementById('pay-frequency').value,
            pay_start_date: document.getElementById('pay-start-date').value,
            pay_bg_style: document.getElementById('pay-bg-style').value
        };

        try {
            await Promise.all(Object.entries(data).map(([key, value]) => 
                StechAPI.request('post', '/api/admin/settings', { key, value })
            ));
            const msg = document.getElementById('payroll-msg');
            if (msg) {
                msg.style.display = 'inline';
                setTimeout(() => msg.style.display = 'none', 3000);
            }
        } catch (err) {
            OC.dialogs.error('Failed to save payroll settings', 'Error');
        }
    }
};