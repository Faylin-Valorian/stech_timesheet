import { StechAPI } from '../api.js';

export const PayrollAdmin = {
    async load() {
        try {
            const settings = await StechAPI.request('get', '/api/admin/settings');
            document.getElementById('pay-frequency').value = settings['pay_frequency'] || 14;
            document.getElementById('pay-start-date').value = settings['pay_start_date'] || '2026-01-07';
        } catch (err) {
            console.error("Failed to load payroll settings", err);
        }
    },

    async save() {
        const freq = document.getElementById('pay-frequency').value;
        const start = document.getElementById('pay-start-date').value;

        try {
            // Save Basic Settings
            await StechAPI.request('post', '/api/admin/settings', { key: 'pay_frequency', value: freq });
            await StechAPI.request('post', '/api/admin/settings', { key: 'pay_start_date', value: start });

            const msg = document.getElementById('payroll-msg');
            if (msg) {
                msg.style.display = 'inline';
                setTimeout(() => msg.style.display = 'none', 3000);
            }
        } catch (err) {
            console.error(err);
            if(OC.dialogs && OC.dialogs.alert) {
                 OC.dialogs.alert(err.message || 'Failed to save settings', 'Error', null);
            } else {
                 alert('Failed to save settings');
            }
        }
    }
};