import { StechAPI } from './api.js';
import { ActivityRows } from './rows.js';
import { Calendar } from './calendar.js';
import { Form } from './form.js';

/**
 * Main Application Orchestrator
 * Bootstraps the application and handles global state.
 */
// FIX: Force immediate namespace initialization to prevent race conditions
window.StechTimesheet = window.StechTimesheet || {};

Object.assign(window.StechTimesheet, {
    API: StechAPI,
    ActivityRows: ActivityRows,
    Calendar: Calendar,
    Form: Form,
    state: {
        jobOptions: [],
        stateMap: {},
        stateMapRev: {}
    },

    /**
     * Calculates total hours based on Time In, Time Out, and Break Duration.
     */
    calculateTotalHours() {
        const timeInVal = document.getElementById("time-in").value;
        const timeOutVal = document.getElementById("time-out").value;
        const breakMin = parseInt(document.getElementById("break-min").value) || 0;
        const totalDisplay = document.getElementById("total-hours");

        if (timeInVal && timeOutVal) {
            let start = new Date(`2000-01-01T${timeInVal}`);
            let end = new Date(`2000-01-01T${timeOutVal}`);

            // Handle overnight shifts
            if (end < start) {
                end.setDate(end.getDate() + 1);
            }

            const diffMinutes = Math.floor((end - start) / 60000) - breakMin;
            totalDisplay.value = (diffMinutes > 0 ? diffMinutes / 60 : 0).toFixed(2);
        } else {
            totalDisplay.value = "0.00";
        }
    }
});

/**
 * Global Initialization
 */
document.addEventListener("DOMContentLoaded", () => {
    // Initialize Form listeners
    window.StechTimesheet.Form.init();

    // Fetch required attributes for form dropdowns
    window.StechTimesheet.API.getAttributes().then(data => {
        if (data.jobs) {
            window.StechTimesheet.state.jobOptions = data.jobs;
        }

        const stateDatalist = document.getElementById("state-options");
        if (stateDatalist && data.states) {
            data.states.forEach(state => {
                const opt = document.createElement("option");
                opt.value = state.state_name;
                stateDatalist.appendChild(opt);
                
                // Map state names to abbreviations for county lookups
                window.StechTimesheet.state.stateMap[state.state_name] = state.state_abbr;
                window.StechTimesheet.state.stateMapRev[state.state_abbr] = state.state_name;
            });
        }

        // Initialize Calendar after attributes are ready
        const calendarEl = document.getElementById("calendar");
        if (calendarEl) {
            window.StechTimesheet.Calendar.init(calendarEl);
        }
    }).catch(err => {
        console.error("Failed to initialize application attributes:", err);
    });

    // FIX: Attach calculation listeners to correct IDs for real-time updates
    const calcInputs = ["time-in", "time-out", "break-min"];
    calcInputs.forEach(id => {
        document.getElementById(id)?.addEventListener("input", () => window.StechTimesheet.calculateTotalHours());
    });
});