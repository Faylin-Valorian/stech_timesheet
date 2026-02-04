/**
 * StechTimesheet.Form
 * Handles modal interactions, dynamic dropdowns, and toggles.
 */
window.StechTimesheet = window.StechTimesheet || {};

const Form = {
    currentId: null,
    isLocked: false,

    init() {
        this.setupEventListeners();
        this.setupStateListener();
        this.setupToggleListeners();
    },

    setupEventListeners() {
        document.getElementById('btn-save').addEventListener('click', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });

        document.getElementById('btn-delete').addEventListener('click', (e) => {
            e.preventDefault();
            this.handleDelete();
        });

        document.querySelectorAll('.close-modal, .secondary-button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.close();
            });
        });
        
        // Close centered error on click
        const errorOverlay = document.getElementById('stech-centered-error');
        if(errorOverlay) {
            errorOverlay.addEventListener('click', () => {
                errorOverlay.style.display = 'none';
            });
        }
    },

    setupToggleListeners() {
        const travelToggle = document.getElementById('toggle-travel');
        const travelFields = document.getElementById('travel-fields-container');
        if (travelToggle && travelFields) {
            travelToggle.addEventListener('change', (e) => {
                travelFields.style.display = e.target.checked ? 'block' : 'none';
            });
        }

        // NEW: PTO Auto-fill Logic
        const ptoToggle = document.getElementById('toggle-pto');
        if (ptoToggle) {
            ptoToggle.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.handlePTOAutoFill();
                }
            });
        }
    },

    handlePTOAutoFill() {
        const timeIn = document.getElementById('time-in');
        const timeOut = document.getElementById('time-out');
        const breakMin = document.getElementById('break-min');

        // Only trigger if user hasn't already started entering times
        if (!timeIn.value && !timeOut.value) {
            timeIn.value = "08:00";
            timeOut.value = "17:00";
            breakMin.value = "60";

            // Find first PTO job from the global state
            // Ensure window.StechTimesheet.state.jobs is populated via getAttributes
            const ptoJob = window.StechTimesheet.state.jobs ? window.StechTimesheet.state.jobs.find(j => parseInt(j.is_pto) === 1) : null;
            
            if (ptoJob) {
                // clear existing rows
                document.getElementById('work-rows-container').innerHTML = '';
                // Add the PTO job row
                window.StechTimesheet.ActivityRows.add(ptoJob.job_name, 100);
            }
        }
    },

    setupStateListener() {
        const stateInput = document.getElementById('travel-state');
        const countyList = document.getElementById('county-options');
        if (stateInput) {
            stateInput.addEventListener('change', (e) => {
                const stateName = e.target.value;
                const stateAbbr = window.StechTimesheet.state.stateMap[stateName];
                countyList.innerHTML = '';
                if (stateAbbr) {
                    window.StechTimesheet.API.getCounties(stateAbbr).then(counties => {
                        counties.forEach(county => {
                            const option = document.createElement('option');
                            option.value = county.county_name;
                            countyList.appendChild(option);
                        });
                    });
                }
            });
        }
    },

    open(date, id = null) {
        this.currentId = id;
        this.reset();
        document.getElementById('timesheet-date').value = date;
        const deleteBtn = document.getElementById('btn-delete');

        if (id) {
            window.StechTimesheet.API.getTimesheetDetails(id).then(data => {
                // CHECK FOR LOCK/PAYROLL STATUS
                // Assuming data.archive > 0 means it's locked/payrolled
                if (parseInt(data.archive) === 1) {
                   this.showCenteredError("This record is locked by Payroll and cannot be edited.");
                   return; // Stop opening the edit modal
                }

                deleteBtn.style.display = 'block';
                this.mapDataToForm(data);
                document.getElementById('timesheet-modal').style.display = 'flex';
            }).catch(err => {
                console.error("Error fetching details", err);
            });
        } else {
            deleteBtn.style.display = 'none';
            window.StechTimesheet.ActivityRows.add();
            document.getElementById('timesheet-modal').style.display = 'flex';
        }
    },
    
    showCenteredError(msg) {
        let overlay = document.getElementById('stech-centered-error');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'stech-centered-error';
            document.body.appendChild(overlay);
        }
        overlay.innerHTML = `<div class="stech-error-content">
            <h3>Access Denied</h3>
            <p>${msg}</p>
            <button onclick="document.getElementById('stech-centered-error').style.display='none'" class="primary-button">Close</button>
        </div>`;
        overlay.style.display = 'flex';
    },

    mapDataToForm(data) {
        document.getElementById('time-in').value = data.time_in || '';
        document.getElementById('time-out').value = data.time_out || '';
        document.getElementById('break-min').value = data.time_break || 0;
        document.getElementById('total-hours').value = data.time_total || 0;
        document.getElementById('additional-comments').value = data.additional_comments || '';
        
        // Handle State/Travel mapping safely
        const stateRev = window.StechTimesheet.state.stateMapRev || {};
        document.getElementById('travel-state').value = stateRev[data.travel_state] || '';
        document.getElementById('travel-county').value = data.travel_county || '';
        document.getElementById('travel-miles').value = data.travel_miles || 0;
        document.getElementById('travel-extra-expense').value = data.travel_extra_expenses || 0;
        document.getElementById('req-per-diem').checked = data.travel_per_diem == 1;

        if (data.travel_state || data.travel_miles > 0) {
            document.getElementById('toggle-travel').checked = true;
            document.getElementById('travel-fields-container').style.display = 'block';
        }
        this.renderActivities(data.activities);
    },

    renderActivities(activities) {
        const container = document.getElementById('work-rows-container');
        container.innerHTML = '';
        if (activities && activities.length > 0) {
            activities.forEach(act => window.StechTimesheet.ActivityRows.add(act.activity_description, act.activity_percent));
        } else {
            window.StechTimesheet.ActivityRows.add();
        }
    },

    async handleSubmit() {
        const formData = this.getFormData();
        
        // --- VALIDATION LOGIC ---
        
        // 1. Must have Sign In Time OR Request Per Diem
        const hasTimeIn = !!formData.time_in;
        const hasPerDiem = !!formData.req_per_diem;
        
        if (!hasTimeIn && !hasPerDiem) {
            if (window.OCP && window.OCP.Toast) {
                window.OCP.Toast.error("You must enter a Sign In Time OR request Per Diem to create a record.");
            } else {
                alert("You must enter a Sign In Time OR request Per Diem to create a record.");
            }
            return;
        }

        // 2. If Clock Out exists, Job Description is MANDATORY
        const hasTimeOut = !!formData.time_out;
        let hasDescription = false;
        
        // Check if at least one description is filled
        if (formData.work_desc && formData.work_desc.length > 0) {
            hasDescription = formData.work_desc.some(desc => desc.trim() !== '');
        }

        if (hasTimeOut && !hasDescription) {
            if (window.OCP && window.OCP.Toast) {
                window.OCP.Toast.error("You cannot Clock Out without entering a Job Description.");
            } else {
                alert("You cannot Clock Out without entering a Job Description.");
            }
            return;
        }

        // --- END VALIDATION ---

        try {
            const res = await window.StechTimesheet.API.saveTimesheet(formData);
            if (res.status === 'success') {
                this.close();
                window.StechTimesheet.Calendar.refresh();
            }
        } catch (err) { 
            console.error('Save failed', err);
        }
    },

    async handleDelete() {
        if (!this.currentId || !confirm('Archive this record?')) return;
        try {
            const res = await window.StechTimesheet.API.deleteTimesheet(this.currentId);
            if (res.status === 'success') {
                this.close();
                window.StechTimesheet.Calendar.refresh();
            }
        } catch (err) { console.error('Delete failed', err); }
    },

    getFormData() {
        const workDesc = [];
        const workPercent = [];
        document.querySelectorAll('.work-desc').forEach(el => workDesc.push(el.value));
        document.querySelectorAll('.work-percent').forEach(el => workPercent.push(el.value));
        
        return {
            timesheet_id: this.currentId,
            date: document.getElementById('timesheet-date').value,
            time_in: document.getElementById('time-in').value,
            time_out: document.getElementById('time-out').value,
            break_min: document.getElementById('break-min').value,
            total_hours: document.getElementById('total-hours').value,
            comments: document.getElementById('additional-comments').value,
            state: window.StechTimesheet.state.stateMap[document.getElementById('travel-state').value] || '',
            county: document.getElementById('travel-county').value,
            miles: document.getElementById('travel-miles').value,
            extra_expense: document.getElementById('travel-extra-expense').value,
            req_per_diem: document.getElementById('req-per-diem').checked ? 1 : 0,
            work_desc: workDesc,
            work_percent: workPercent
        };
    },

    reset() {
        this.currentId = null;
        document.getElementById('timesheet-form').reset();
        document.getElementById('work-rows-container').innerHTML = '';
        document.getElementById('travel-fields-container').style.display = 'none';
        document.getElementById('toggle-travel').checked = false;
        
        // Reset PTO toggle if it exists
        const ptoToggle = document.getElementById('toggle-pto');
        if (ptoToggle) ptoToggle.checked = false;
    },

    close() { document.getElementById('timesheet-modal').style.display = 'none'; }
};

window.StechTimesheet.Form = Form;
export { Form };