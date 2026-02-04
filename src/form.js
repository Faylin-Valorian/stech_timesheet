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
            this.handleDelete(); // Now opens custom modal
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

        // PTO Auto-fill Logic
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

        if (!timeIn.value && !timeOut.value) {
            timeIn.value = "08:00";
            timeOut.value = "17:00";
            breakMin.value = "60";

            const ptoJob = window.StechTimesheet.state.jobs ? window.StechTimesheet.state.jobs.find(j => parseInt(j.is_pto) === 1) : null;
            
            if (ptoJob) {
                document.getElementById('work-rows-container').innerHTML = '';
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
        this.reset();
        this.currentId = id;
        document.getElementById('timesheet-date').value = date;
        const deleteBtn = document.getElementById('btn-delete');

        if (id) {
            window.StechTimesheet.API.getTimesheetDetails(id).then(data => {
                if (parseInt(data.archive) === 1) {
                   this.showCenteredError("This record is locked/archived and cannot be edited.");
                   this.currentId = null;
                   return; 
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

    // NEW: Custom Confirmation Modal for Archiving
    showConfirmArchive() {
        let overlay = document.getElementById('stech-confirm-archive');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'stech-confirm-archive';
            // Reuse the centered error style structure for consistency
            overlay.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
                background: rgba(0,0,0,0.6); z-index: 10002; display: none;
                align-items: center; justify-content: center; backdrop-filter: blur(2px);
            `;
            document.body.appendChild(overlay);
        }

        overlay.innerHTML = `
            <div class="stech-error-content" style="border-top-color: #e67e22;">
                <h3 style="color: #e67e22;">Archive Record?</h3>
                <p>Are you sure you want to archive this entry? It will be hidden from the main view.</p>
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                    <button id="btn-confirm-archive-yes" class="primary-button">Yes, Archive It</button>
                    <button id="btn-confirm-archive-no" class="secondary-button">Cancel</button>
                </div>
            </div>
        `;

        // Bind events
        document.getElementById('btn-confirm-archive-yes').onclick = () => {
            overlay.style.display = 'none';
            this.executeArchive();
        };
        document.getElementById('btn-confirm-archive-no').onclick = () => {
            overlay.style.display = 'none';
        };

        overlay.style.display = 'flex';
    },

    mapDataToForm(data) {
        document.getElementById('time-in').value = data.time_in || '';
        document.getElementById('time-out').value = data.time_out || '';
        document.getElementById('break-min').value = data.time_break || 0;
        document.getElementById('total-hours').value = data.time_total || 0;
        document.getElementById('additional-comments').value = data.additional_comments || '';
        
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

        const hasTimeOut = !!formData.time_out;
        let hasDescription = false;
        
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

    // Triggered by the "Delete" button in the form
    handleDelete() {
        if (!this.currentId) return;
        this.showConfirmArchive();
    },

    // Triggered by the "Yes" button in the custom modal
    async executeArchive() {
        try {
            const res = await window.StechTimesheet.API.deleteTimesheet(this.currentId);
            if (res.status === 'success') {
                this.close();
                window.StechTimesheet.Calendar.refresh();
                if (window.OCP && window.OCP.Toast) {
                    window.OCP.Toast.info("Record archived.");
                }
            }
        } catch (err) { console.error('Archive failed', err); }
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
        
        const ptoToggle = document.getElementById('toggle-pto');
        if (ptoToggle) ptoToggle.checked = false;
    },

    close() { document.getElementById('timesheet-modal').style.display = 'none'; }
};

window.StechTimesheet.Form = Form;
export { Form };