import { StechAPI } from '../api.js';

export const PayrollAdmin = {
    async load() {
        try {
            const settings = await StechAPI.request('get', '/api/admin/settings');
            
            const freqSelect = document.getElementById('pay-frequency');
            freqSelect.value = settings['pay_frequency'] || 14;
            
            document.getElementById('pay-start-date').value = settings['pay_start_date'] || '2026-01-07';
            document.getElementById('pay-date-1').value = settings['pay_date_1'] || 1;
            document.getElementById('pay-date-2').value = settings['pay_date_2'] || 15;

            // PATCH: Load Saved Color & Sync Inputs
            const savedColor = settings['pay_color'] || '#34495e';
            const colorInput = document.getElementById('pay-color');
            const colorText = document.getElementById('pay-color-text');
            
            if (colorInput && colorText) {
                colorInput.value = savedColor;
                colorText.value = savedColor;

                // Sync: Color -> Text
                colorInput.addEventListener('input', (e) => {
                    colorText.value = e.target.value;
                });

                // Sync: Text -> Color (Validation)
                colorText.addEventListener('input', (e) => {
                    let val = e.target.value;
                    if (!val.startsWith('#')) val = '#' + val;
                    if (/^#[0-9A-F]{6}$/i.test(val)) {
                        colorInput.value = val;
                    }
                });
                // Ensure proper formatting on blur
                colorText.addEventListener('change', (e) => {
                     let val = e.target.value;
                     if (!val.startsWith('#')) val = '#' + val;
                     if (/^#[0-9A-F]{6}$/i.test(val)) {
                         e.target.value = val;
                         colorInput.value = val;
                     } else {
                         e.target.value = colorInput.value; // Revert if invalid
                     }
                });
            }

            this.toggleCustomFields(freqSelect.value);
            freqSelect.addEventListener('change', (e) => this.toggleCustomFields(e.target.value));

        } catch (err) {
            console.error("Failed to load payroll settings", err);
        }
    },

    toggleCustomFields(val) {
        const isCustom = val === 'custom_twice';
        const standardOpts = document.getElementById('freq-standard-options');
        const customOpts = document.getElementById('freq-custom-options');
        if (standardOpts) standardOpts.classList.toggle('hidden', isCustom);
        if (customOpts) customOpts.classList.toggle('hidden', !isCustom);
    },

    async save() {
        const data = {
            pay_frequency: document.getElementById('pay-frequency').value,
            pay_start_date: document.getElementById('pay-start-date').value,
            pay_date_1: document.getElementById('pay-date-1').value,
            pay_date_2: document.getElementById('pay-date-2').value,
            // PATCH: Save Color Setting
            pay_color: document.getElementById('pay-color-text').value
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
            console.error(err);
            if(OC.dialogs && OC.dialogs.alert) {
                 OC.dialogs.alert(err.message || 'Failed to save settings', 'Error', null);
            } else {
                 alert('Failed to save settings');
            }
        }
    }
};