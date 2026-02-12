import { StechAPI } from '../../../api.js';

export const PayrollFeature = {
    async init() {
        try {
            // Updated to the new modular route defined in Step 1.6
            const settings = await StechAPI.request('get', '/api/admin/payroll/settings');
            this.render(settings);
            this.setupListeners();
        } catch (err) {
            console.error("Failed to load modular payroll settings", err);
        }
    },

    render(settings) {
        const enabledToggle = document.getElementById('pay-enabled');
        if (enabledToggle) {
            enabledToggle.checked = (settings['pay_enabled'] === '1');
        }

        const freqSelect = document.getElementById('pay-frequency');
        if (freqSelect) {
            freqSelect.value = settings['pay_frequency'] || 14;
            this.toggleCustomFields(freqSelect.value);
        }
        
        document.getElementById('pay-start-date').value = settings['pay_start_date'] || '';
        document.getElementById('pay-date-1').value = settings['pay_date_1'] || 1;
        document.getElementById('pay-date-2').value = settings['pay_date_2'] || 15;

        const savedColor = settings['pay_color'] || '#34495e';
        const colorInput = document.getElementById('pay-color');
        const colorText = document.getElementById('pay-color-text');
        
        if (colorInput && colorText) {
            colorInput.value = savedColor;
            colorText.value = savedColor;
        }
    },

    toggleCustomFields(val) {
        const isCustom = val === 'custom_twice';
        const standardOpts = document.getElementById('freq-standard-options');
        const customOpts = document.getElementById('freq-custom-options');
        if (standardOpts) standardOpts.classList.toggle('hidden', isCustom);
        if (customOpts) customOpts.classList.toggle('hidden', !isCustom);
    },

    setupListeners() {
        const freqSelect = document.getElementById('pay-frequency');
        freqSelect?.addEventListener('change', (e) => this.toggleCustomFields(e.target.value));

        // Sync Color Inputs
        const colorInput = document.getElementById('pay-color');
        const colorText = document.getElementById('pay-color-text');
        colorInput?.addEventListener('input', (e) => { colorText.value = e.target.value; });

        // Save Button
        document.getElementById('btn-save-payroll')?.addEventListener('click', () => this.save());
    },

    async save() {
        const data = {
            pay_enabled: document.getElementById('pay-enabled').checked ? '1' : '0',
            pay_frequency: document.getElementById('pay-frequency').value,
            pay_start_date: document.getElementById('pay-start-date').value,
            pay_date_1: document.getElementById('pay-date-1').value,
            pay_date_2: document.getElementById('pay-date-2').value,
            pay_color: document.getElementById('pay-color-text').value
        };

        try {
            await StechAPI.request('post', '/api/admin/payroll/settings', data);
            const msg = document.getElementById('payroll-msg');
            if (msg) {
                msg.style.display = 'inline';
                setTimeout(() => msg.style.display = 'none', 3000);
            }
        } catch (err) {
            console.error("Modular Save Failed", err);
        }
    }
};