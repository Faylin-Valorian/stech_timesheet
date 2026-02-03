import { StechAPI } from './api.js';
import { ActivityRows } from './rows.js';
import { Calendar } from './calendar.js';

/**
 * Timesheet Entry Form Module
 * Handles modal state, data population, and submission.
 */
export const Form = {
    overlay: null,
    form: null,

    init() {
        this.overlay = document.getElementById("timesheet-modal-overlay");
        this.form = document.getElementById("timesheet-form");
        this.setupListeners();
    },

    setupListeners() {
        document.getElementById("btn-cancel")?.addEventListener("click", () => this.close());
        document.getElementById("modal-close-btn")?.addEventListener("click", () => this.close());
        document.getElementById("btn-add-row")?.addEventListener("click", () => ActivityRows.add());
        
        document.getElementById("toggle-travel")?.addEventListener("change", function() {
            const container = document.getElementById("travel-fields-container");
            this.checked ? container.classList.add("visible") : container.classList.remove("visible");
        });

        document.getElementById("travel-state")?.addEventListener("change", async (e) => {
            const stateName = e.target.value;
            const stateAbbr = window.StechTimesheet.state.stateMap[stateName];
            const countyList = document.getElementById("county-options");
            
            if (countyList) {
                countyList.innerHTML = "";
                if (stateAbbr) {
                    try {
                        const counties = await StechAPI.getCounties(stateAbbr);
                        counties.forEach(c => {
                            const opt = document.createElement("option");
                            opt.value = c.county_name;
                            countyList.appendChild(opt);
                        });
                    } catch (err) {
                        console.error("Failed to fetch counties", err);
                    }
                }
            }
        });

        document.getElementById("toggle-pto")?.addEventListener("change", (e) => this.handlePTOToggle(e.target));
        this.form?.addEventListener("submit", (e) => this.handleSubmit(e));
    },

    open(date, data) {
        this.form.reset();
        document.getElementById("entry-date").value = date;
        ActivityRows.clear();
        document.getElementById("travel-fields-container").classList.remove("visible");
        document.getElementById("timesheet_id").value = data ? data.timesheet_id : "";

        const checkboxes = ["toggle-pto", "toggle-travel", "req-per-diem", "road-scanning", "first-last-day", "overnight"];
        checkboxes.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.checked = false;
        });

        if (data) {
            this.populateExistingData(data);
        } else {
            ActivityRows.add();
        }
        
        if (this.overlay) this.overlay.style.display = "flex";
    },

    close() {
        if (this.overlay) this.overlay.style.display = "none";
    },

    populateExistingData(data) {
        document.getElementById("time-in").value = data.time_in || "";
        document.getElementById("time-out").value = data.time_out || "";
        document.getElementById("break-min").value = data.time_break || 0;
        document.getElementById("total-hours").value = data.time_total || 0;

        let comments = data.additional_comments || "";
        if (comments.includes("[PTO]")) {
            document.getElementById("toggle-pto").checked = true;
            comments = comments.replace("[PTO]", "").trim();
        }
        document.getElementById("comments").value = comments;

        if (parseInt(data.travel) === 1 || parseInt(data.travel_per_diem) === 1 || data.travel_miles > 0) {
            document.getElementById("toggle-travel").checked = true;
            document.getElementById("travel-fields-container").classList.add("visible");
            document.getElementById("req-per-diem").checked = parseInt(data.travel_per_diem) === 1;
            document.getElementById("miles").value = data.travel_miles;
            document.getElementById("extra-expense").value = data.travel_extra_expenses;

            let stateName = data.travel_state || "";
            if (stateName.length === 2) {
                stateName = window.StechTimesheet.state.stateMapRev[stateName] || stateName;
            }
            document.getElementById("travel-state").value = stateName;
            document.getElementById("travel-county").value = data.travel_county;
            document.getElementById("travel-state").dispatchEvent(new Event("change"));
        }

        if (data.activities && data.activities.length > 0) {
            data.activities.forEach(a => ActivityRows.add(a.activity_description, a.activity_percent));
        } else {
            ActivityRows.add();
        }
    },

    handlePTOToggle(el) {
        if (el.checked) {
            const timeIn = document.getElementById("time-in");
            const timeOut = document.getElementById("time-out");
            if (!timeIn.value && !timeOut.value) {
                timeIn.value = "08:00";
                timeOut.value = "17:00";
                document.getElementById("break-min").value = "60";
                window.StechTimesheet.calculateTotalHours();
                
                const ptoJob = window.StechTimesheet.state.jobOptions.find(j => parseInt(j.is_pto) === 1);
                if (ptoJob) {
                    ActivityRows.clear();
                    ActivityRows.add(ptoJob.job_name, 100);
                }
            }
        }
    },

    async handleSubmit(e) {
        e.preventDefault();
        
        let totalPercent = 0;
        document.querySelectorAll(".work-percent-input").forEach(el => {
            totalPercent += parseInt(el.value) || 0;
        });

        if (totalPercent > 100) {
            if (window.OCP?.Toast) window.OCP.Toast.error("Total activity cannot exceed 100%.");
            return;
        }

        const formData = new FormData(this.form);
        if (document.getElementById("toggle-pto").checked) {
            let comments = formData.get("comments") || "";
            if (!comments.includes("[PTO]")) formData.set("comments", "[PTO] " + comments);
        }

        const timeIn = formData.get("time_in");
        const isPerDiem = document.getElementById("req-per-diem").checked;

        if (timeIn || isPerDiem) {
            try {
                await StechAPI.saveTimesheet(formData);
                this.close();
                Calendar.refetch();
            } catch (err) {
                // StechAPI already handles the OCP.Toast error notification
                console.error("Submission failed", err);
            }
        } else {
            if (window.OCP?.Toast) window.OCP.Toast.error("Please enter a Start Time or select 'Request Per Diem'.");
        }
    }
};