import { StechAPI } from '../api.js';

export const PayrollAdmin = {
    async load() {
        try {
            const settings = await StechAPI.request('get', '/api/admin/settings');
            
            const freqSelect = document.getElementById('pay-frequency');
            // Default to 14 (Bi-weekly) if not set
            freqSelect.value = settings['pay_frequency'] || 14;
            
            // Set standard date
            document.getElementById('pay-start-date').value = settings['pay_start_date'] || '2026-01-07';
            
            // Set custom dates (Default to 1st and 15th if empty)
            document.getElementById('pay-date-1').value = settings['pay_date_1'] || 1;
            document.getElementById('pay-date-2').value = settings['pay_date_2'] || 15;

            // Initial UI State
            this.toggleCustomFields(freqSelect.value);

            // Change Listener
            freqSelect.addEventListener('change', (e) => this.toggleCustomFields(e.target.value));

        } catch (err) {
            console.error("Failed to load payroll settings", err);
        }
    },

    /**
     * Show/Hide specific inputs based on the frequency mode
     */
    toggleCustomFields(val) {
        const isCustom = val === 'custom_twice';
        
        const standardOpts = document.getElementById('freq-standard-options');
        const customOpts = document.getElementById('freq-custom-options');

        if (standardOpts) standardOpts.classList.toggle('hidden', isCustom);
        if (customOpts) customOpts.classList.toggle('hidden', !isCustom);
    },

    async save() {
        // Collect all data points
        const data = {
            pay_frequency: document.getElementById('pay-frequency').value,
            pay_start_date: document.getElementById('pay-start-date').value,
            pay_date_1: document.getElementById('pay-date-1').value,
            pay_date_2: document.getElementById('pay-date-2').value
        };

        try {
            // Save all settings in parallel using Promise.all
            await Promise.all(Object.entries(data).map(([key, value]) => 
                StechAPI.request('post', '/api/admin/settings', { key, value })
            ));

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