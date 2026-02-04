/**
 * Activity Rows Module
 * Handles dynamic row creation and percentage math.
 */
// FIX: Ensure namespace is initialized before module assignment
window.StechTimesheet = window.StechTimesheet || {};

export const ActivityRows = {
    containerId: 'work-rows-container',

    /**
     * Add a new work row to the container
     */
    add(descVal = '', percentVal = '') {
        const container = document.getElementById(this.containerId);
        if (!container) return;

        const existingRows = container.querySelectorAll('.work-row');
        
        // Auto-calculate if it's a new, empty row
        if (descVal === '' && percentVal === '') {
            if (existingRows.length === 0) {
                percentVal = 100; 
            } else {
                const count = existingRows.length + 1;
                const split = Math.floor(100 / count);
                container.querySelectorAll('.work-percent-input').forEach(inp => {
                    inp.value = split;
                });
                percentVal = 100 - (split * (count - 1));
            }
        }

        const row = document.createElement('div');
        row.className = 'work-row';
        
        // Access jobOptions from the global state defined in main.js
        const jobOptions = window.StechTimesheet.state.jobOptions || [];
        let optionsHtml = '<option value="">Select Job...</option>';
        
        jobOptions.forEach(job => {
            const selected = (job.job_name === descVal) ? 'selected' : '';
            optionsHtml += `<option value="${job.job_name}" ${selected}>${job.job_name}</option>`;
        });

        // FIX: Added 'work-desc' and 'work-percent' classes so Form.getFormData can extract values
        row.innerHTML = `
            <select name="work_desc[]" class="form-control work-desc">${optionsHtml}</select>
            <input type="number" name="work_percent[]" class="form-control text-center work-percent work-percent-input" 
                   value="${percentVal}" placeholder="0" min="0" max="100">
            <div class="btn-remove-row" title="Remove">&times;</div>
        `;
        
        // Attach internal listeners for percentage balancing
        const input = row.querySelector('.work-percent-input');
        input.addEventListener('change', (e) => this.recalculate(e.target));

        row.querySelector('.btn-remove-row').addEventListener('click', () => {
            row.remove();
        });

        container.appendChild(row);
    },

    /**
     * Balances percentages across all rows when one is changed
     */
    recalculate(changedInput) {
        const allInputs = document.querySelectorAll('.work-percent-input');
        if (allInputs.length < 2) return;

        let newVal = parseInt(changedInput.value) || 0;
        if (newVal > 100) { newVal = 100; changedInput.value = 100; }
        if (newVal < 0) { newVal = 0; changedInput.value = 0; }
        
        const remaining = 100 - newVal;
        const others = [];
        allInputs.forEach(inp => { if(inp !== changedInput) others.push(inp); });
        
        if (others.length === 1) {
            others[0].value = remaining;
        } else if (others.length > 1) {
            const split = Math.floor(remaining / others.length);
            others.forEach((inp, idx) => {
                if (idx === others.length - 1) {
                    inp.value = remaining - (split * (others.length - 1));
                } else {
                    inp.value = split;
                }
            });
        }
    },

    /**
     * Clear all current rows
     */
    clear() {
        const container = document.getElementById(this.containerId);
        if (container) container.innerHTML = '';
    }
};

// --- BUILD FIX ---
// This alias ensures main.js and form.js can access ActivityRows via the global namespace
window.StechTimesheet.ActivityRows = ActivityRows;