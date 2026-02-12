export const ActivityRows = {
    add(containerId, jobOptions, descVal = '', percentVal = 0, isUserAction = false) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const row = document.createElement('div');
        row.className = 'work-row';
        
        let optionsHtml = '<option value="">Select Job...</option>';
        jobOptions.forEach(j => {
            const selected = (j.job_name === descVal) ? 'selected' : '';
            optionsHtml += `<option value="${j.job_name}" ${selected}>${j.job_name}</option>`;
        });

        row.innerHTML = `
            <select class="work-desc" style="flex-grow: 1; margin-right: 10px; padding: 5px;">${optionsHtml}</select>
            <input type="number" class="work-percent" placeholder="%" value="${percentVal}" min="0" max="100" style="width: 80px;">
            <button class="btn-remove-row" tabindex="-1">&times;</button>
        `;

        // Bind Remove
        row.querySelector('.btn-remove-row').addEventListener('click', (e) => {
            e.preventDefault();
            row.remove();
            this.recalculate(null, container);
        });

        // Bind Input
        const percentInput = row.querySelector('.work-percent');
        percentInput.addEventListener('input', () => this.recalculate(percentInput, container));

        container.appendChild(row);

        // Auto-balance if user added it
        if (isUserAction) {
            this.recalculate(null, container);
        }
    },

    recalculate(sourceInput, container) {
        const allInputs = Array.from(container.querySelectorAll('.work-percent'));
        if (allInputs.length === 0) return;

        if (!sourceInput) {
            // Even Split
            const count = allInputs.length;
            const base = Math.floor(100 / count);
            let remainder = 100 % count;
            allInputs.forEach(input => {
                let val = base + (remainder > 0 ? 1 : 0);
                input.value = val;
                remainder--;
            });
            return;
        }

        // Proportional Split
        let userValue = parseInt(sourceInput.value) || 0;
        if (userValue < 0) userValue = 0;
        if (userValue > 100) userValue = 100;
        
        const remainingTotal = 100 - userValue;
        const otherInputs = allInputs.filter(i => i !== sourceInput);
        
        if (otherInputs.length > 0) {
            const base = Math.floor(remainingTotal / otherInputs.length);
            let remainder = remainingTotal % otherInputs.length;
            otherInputs.forEach(input => {
                let val = base + (remainder > 0 ? 1 : 0);
                input.value = val;
                remainder--;
            });
        }
    }
};