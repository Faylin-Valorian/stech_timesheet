import { StechAPI } from './api.js';
import { Form } from './form.js';
import { Calendar } from './calendar.js';
import { generateUrl } from '@nextcloud/router'; // Required for navigation

window.StechTimesheet = window.StechTimesheet || {};
window.StechTimesheet.API = StechAPI;
window.StechTimesheet.Form = Form;
window.StechTimesheet.Calendar = Calendar;
window.StechTimesheet.state = {
    jobs: [],
    stateMap: {},
    stateMapRev: {}
};

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Load Initial Data
    try {
        const attributes = await StechAPI.getAttributes();
        window.StechTimesheet.state.jobs = attributes.jobs || [];
        
        attributes.states.forEach(s => {
            window.StechTimesheet.state.stateMap[s.state_name] = s.state_abbr;
            window.StechTimesheet.state.stateMapRev[s.state_abbr] = s.state_name;
        });

        // Populate State Dropdown
        const stateSelect = document.getElementById('travel-state');
        if (stateSelect) {
            stateSelect.innerHTML = '<option value="">Select State...</option>';
            attributes.states.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.state_name;
                opt.textContent = s.state_name;
                stateSelect.appendChild(opt);
            });
        }
    } catch (e) {
        console.error("Failed to load attributes", e);
    }

    // 2. Initialize Components
    Form.init();
    Calendar.init(document.getElementById('calendar'));

    // 3. FIX: Navigation Buttons
    // These match the IDs in your sidebar templates
    document.querySelector('a[href="/analysis"]')?.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = generateUrl('/apps/stech_timesheet/analysis');
    });

    document.querySelector('a[href="/admin"]')?.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = generateUrl('/apps/stech_timesheet/admin');
    });
});

// 4. FIX: Activity Row Builder (Now uses <select>)
window.StechTimesheet.ActivityRows = {
    add: (desc = '', percent = 0) => {
        const container = document.getElementById('work-rows-container');
        if (!container) return;

        const row = document.createElement('div');
        row.className = 'work-row';
        
        // Build Job Options
        let optionsHtml = '<option value="">Select Job...</option>';
        if (window.StechTimesheet.state.jobs) {
            window.StechTimesheet.state.jobs.forEach(job => {
                // Check if this job matches the passed description (for editing)
                const selected = job.job_name === desc ? 'selected' : '';
                optionsHtml += `<option value="${job.job_name}" ${selected}>${job.job_name}</option>`;
            });
        }

        row.innerHTML = `
            <select class="work-desc" style="flex-grow: 1; margin-right: 10px;">
                ${optionsHtml}
            </select>
            <input type="number" class="work-percent" placeholder="%" value="${percent}" min="0" max="100" style="width: 80px;">
            <button class="btn-remove-row" tabindex="-1">&times;</button>
        `;

        row.querySelector('.btn-remove-row').addEventListener('click', (e) => {
            e.preventDefault();
            row.remove();
        });

        container.appendChild(row);
    }
};