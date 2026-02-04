/**
 * StechTimesheet.Form
 * Handles modal interactions, dynamic dropdowns, and toggles.
 */
window.StechTimesheet = window.StechTimesheet || {};

const Form = {
    currentId: null,

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
    },

    setupToggleListeners() {
        const travelToggle = document.getElementById('toggle-travel');
        const travelFields = document.getElementById('travel-fields-container');
        if (travelToggle && travelFields) {
            travelToggle.addEventListener('change', (e) => {
                travelFields.style.display = e.target.checked ? 'block' : 'none';
            });
        }
    },

    setupStateListener() {
        const stateInput = document.getElementById('travel-state');
        const countyList = document.getElementById('county-options');
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
    },

    open(date, id = null) {
        this.currentId = id;
        this.reset();
        document.getElementById('timesheet-date').value = date;
        const deleteBtn = document.getElementById('btn-delete');
        if (id) {
            deleteBtn.style.display = 'block';
            window.StechTimesheet.API.getTimesheetDetails(id).then(data => this.mapDataToForm(data));
        } else {
            deleteBtn.style.display = 'none';
            window.StechTimesheet.ActivityRows.add();
        }
        document.getElementById('timesheet-modal').style.display = 'flex';
    },

    mapDataToForm(data) {
        document.getElementById('time-in').value = data.time_in || '';
        document.getElementById('time-out').value = data.time_out || '';
        document.getElementById('break-min').value = data.time_break || 0;
        document.getElementById('total-hours').value = data.time_total || 0;
        document.getElementById('additional-comments').value = data.additional_comments || '';
        document.getElementById('travel-state').value = window.StechTimesheet.state.stateMapRev[data.travel_state] || '';
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
            // These arrays are handled by the new logic in api.js request()
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
    },

    close() { document.getElementById('timesheet-modal').style.display = 'none'; }
};

window.StechTimesheet.Form = Form;
export { Form };