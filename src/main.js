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
    // --- PATCH: PERSISTENT IMPERSONATION CHECK ---
    const storedTarget = sessionStorage.getItem('stech_impersonate');
    const urlParams = new URLSearchParams(window.location.search);
    const currentTarget = urlParams.get('target_user');

    // If we have a stored impersonation target, but it's not in the URL, redirect immediately
    if (storedTarget && storedTarget !== currentTarget) {
        urlParams.set('target_user', storedTarget);
        window.location.search = urlParams.toString();
        return; // Stop further execution to allow reload
    }
    
    // --- PATCH: CLOSE IMPERSONATION LISTENER ---
    document.getElementById('btn-end-impersonation')?.addEventListener('click', () => {
        sessionStorage.removeItem('stech_impersonate');
        // Clear param and reload
        const url = new URL(window.location.href);
        url.searchParams.delete('target_user');
        window.location.href = url.toString();
    });
    // ---------------------------------------------

    // 1. Load Initial Data
    try {
        const attributes = await StechAPI.getAttributes();
        window.StechTimesheet.state.jobs = attributes.jobs || [];
        
        attributes.states.forEach(s => {
            window.StechTimesheet.state.stateMap[s.state_name] = s.state_abbr;
            window.StechTimesheet.state.stateMapRev[s.state_abbr] = s.state_name;
        });

        const stateDatalist = document.getElementById('state-options');
        if (stateDatalist) {
            stateDatalist.innerHTML = ''; 
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

    // ============================================================
    // 3. NEW: 12-Hour Split Time Widget Logic
    // ============================================================
    const timeWidgets = document.querySelectorAll('.time-split-widget');

    timeWidgets.forEach(widget => {
        const hourSelect = widget.querySelector('.hour-select');
        const minSelect = widget.querySelector('.minute-select');
        const ampmSelect = widget.querySelector('.ampm-select');
        const hiddenInput = widget.querySelector('.combined-time-input');

        // Function: Convert 12h UI to 24h Value (e.g. "02:00 PM" -> "14:00")
        function updateHiddenInput() {
            if (hourSelect.value && minSelect.value && ampmSelect.value) {
                let h = parseInt(hourSelect.value, 10);
                const m = minSelect.value;
                const amp = ampmSelect.value;

                if (amp === 'PM' && h < 12) h += 12;
                if (amp === 'AM' && h === 12) h = 0;

                const hStr = h.toString().padStart(2, '0');
                hiddenInput.value = `${hStr}:${m}`; // Save as 24h format for DB
                
                // Dispatch events
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
            } else {
                hiddenInput.value = ''; 
            }
        }

        // Function: Convert 24h Value to 12h UI (e.g. "14:00" -> "02:00 PM")
        hiddenInput.refreshWidget = function() {
            const val = hiddenInput.value;
            if (val && val.includes(':')) {
                const parts = val.split(':'); // ["14", "30"]
                let h = parseInt(parts[0], 10);
                const m = parts[1];
                let amp = 'AM';

                if (h >= 12) {
                    amp = 'PM';
                    if (h > 12) h -= 12;
                }
                if (h === 0) h = 12; // Midnight is 12 AM

                hourSelect.value = h.toString().padStart(2, '0');
                minSelect.value = m;
                ampmSelect.value = amp;
            } else {
                // Reset if empty
                hourSelect.value = "";
                minSelect.value = "";
                ampmSelect.value = "AM";
            }
        };

        // Listen for user changes
        hourSelect.addEventListener('change', updateHiddenInput);
        minSelect.addEventListener('change', updateHiddenInput);
        ampmSelect.addEventListener('change', updateHiddenInput);
    });
    // ============================================================


    // 4. Navigation Buttons
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

// 5. Activity Row Builder (Smart Auto-Balancing)
window.StechTimesheet.ActivityRows = {
    // Add a row. isUserAction = true means the user clicked "Add", so we should auto-balance.
    add: (desc = '', percent = 0, isUserAction = false) => {
        const container = document.getElementById('work-rows-container');
        if (!container) return;

        const row = document.createElement('div');
        row.className = 'work-row';
        
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

        // 1. DELETE LISTENER: Remove row and rebalance remaining
        row.querySelector('.btn-remove-row').addEventListener('click', (e) => {
            e.preventDefault();
            row.remove();
            window.StechTimesheet.ActivityRows.recalculate(null);
        });

        // 2. INPUT LISTENER: When user types, adjust OTHER rows
        const percentInput = row.querySelector('.work-percent');
        percentInput.addEventListener('input', () => {
            window.StechTimesheet.ActivityRows.recalculate(percentInput);
        });

        container.appendChild(row);

        // 3. AUTO-BALANCE: If this was a user click, balance immediately
        if (isUserAction) {
            window.StechTimesheet.ActivityRows.recalculate(null);
        }
    },

    // The Math Logic for Auto-Balancing
    recalculate: (sourceInput = null) => {
        const allInputs = Array.from(document.querySelectorAll('.work-percent'));
        if (allInputs.length === 0) return;

        // SCENARIO 1: Even Split (Add/Delete Event)
        // If no specific input triggered this (sourceInput is null), divide 100% evenly.
        if (!sourceInput) {
            const count = allInputs.length;
            const base = Math.floor(100 / count);
            let remainder = 100 % count;

            allInputs.forEach(input => {
                // Distribute remainder one by one
                let val = base + (remainder > 0 ? 1 : 0);
                input.value = val;
                remainder--;
            });
            return;
        }

        // SCENARIO 2: Proportional Adjustment (User Typing)
        // User changed one value -> adjust the REST to equal 100
        let userValue = parseInt(sourceInput.value) || 0;
        
        // Clamp input to 0-100
        if (userValue < 0) userValue = 0;
        if (userValue > 100) userValue = 100;
        
        const remainingTotal = 100 - userValue;
        const otherInputs = allInputs.filter(i => i !== sourceInput);
        
        if (otherInputs.length === 0) return; // Only one row, do nothing

        // Distribute remainingTotal among other inputs
        const base = Math.floor(remainingTotal / otherInputs.length);
        let remainder = remainingTotal % otherInputs.length;

        otherInputs.forEach(input => {
            let val = base + (remainder > 0 ? 1 : 0);
            input.value = val;
            remainder--;
        });
    }
};