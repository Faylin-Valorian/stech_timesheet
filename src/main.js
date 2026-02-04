import { StechAPI } from './api.js';
import { Form } from './form.js';
import { Calendar } from './calendar.js';
import { generateUrl } from '@nextcloud/router';

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

        // FIX: Populate State Datalist (Not the Input itself)
        const stateDatalist = document.getElementById('state-options');
        if (stateDatalist) {
            stateDatalist.innerHTML = ''; // Clear existing
            attributes.states.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.state_name;
                stateDatalist.appendChild(opt);
            });
        }
    } catch (e) {
        console.error("Failed to load attributes", e);
    }

    // 2. Initialize Components
    Form.init();
    Calendar.init(document.getElementById('calendar'));

    // 3. Navigation Buttons
    const navLinks = document.querySelectorAll('#app-navigation a');
    
    navLinks.forEach(link => {
        const text = link.innerText.toLowerCase();
        const href = link.href.toLowerCase();

        // Admin Panel Link
        if (text.includes('admin') || href.includes('/admin')) {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                window.location.href = generateUrl('/apps/stech_timesheet/admin');
            });
        }

        // Time Analysis Link
        if (text.includes('analysis') || href.includes('/analysis')) {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                window.location.href = generateUrl('/apps/stech_timesheet/analysis');
            });
        }
    });
});

// 4. Activity Row Builder
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
                const selected = job.job_name === desc ? 'selected' : '';
                optionsHtml += `<option value="${job.job_name}" ${selected}>${job.job_name}</option>`;
            });
        }

        row.innerHTML = `
            <select class="work-desc" style="flex-grow: 1; margin-right: 10px; padding: 5px;">
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