import { StechAPI } from './api.js';
import { ActivityRows } from './rows.js';
import { TimesheetCalendar } from './calendar.js';
import { TimesheetForm } from './form.js';

window.StechTimesheet = {
    API: StechAPI,
    ActivityRows: ActivityRows,
    Calendar: TimesheetCalendar,
    Form: TimesheetForm,
    state: { jobOptions: [], stateMap: {}, stateMapRev: {} },

    calculateTotalHours() {
        const inStr = document.getElementById('time-in').value;
        const outStr = document.getElementById('time-out').value;
        const breakMin = parseInt(document.getElementById('break-min').value) || 0;
        const totalEl = document.getElementById('total-hours');
        if (inStr && outStr) {
            let d1 = new Date(`2000-01-01T${inStr}`);
            let d2 = new Date(`2000-01-01T${outStr}`);
            if (d2 < d1) d2.setDate(d2.getDate() + 1);
            let diff = Math.floor((d2 - d1) / 60000) - breakMin;
            totalEl.value = (diff > 0 ? diff / 60 : 0).toFixed(2);
        } else { totalEl.value = "0.00"; }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Form first so listeners are ready
    window.StechTimesheet.Form.init();

    // 2. Fetch shared data
    window.StechTimesheet.API.getAttributes().then(data => {
        if (data.jobs) window.StechTimesheet.state.jobOptions = data.jobs;
        const stateList = document.getElementById('state-options');
        if (stateList && data.states) {
            data.states.forEach(state => {
                let opt = document.createElement('option');
                opt.value = state.state_name;
                stateList.appendChild(opt);
                window.StechTimesheet.state.stateMap[state.state_name] = state.state_abbr;
                window.StechTimesheet.state.stateMapRev[state.state_abbr] = state.state_name;
            });
        }
        
        // 3. Initialize Calendar after data is ready
        const calendarEl = document.getElementById('calendar');
        if (calendarEl) window.StechTimesheet.Calendar.init(calendarEl);
    });

    document.querySelectorAll('.calc-time').forEach(input => {
        input.addEventListener('change', window.StechTimesheet.calculateTotalHours);
    });
});