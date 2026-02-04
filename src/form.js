/**
 * StechTimesheet.Form
 * Handles modal interactions, dynamic dropdowns, and data mapping.
 */
window.StechTimesheet.Form = {
    currentId: null,

    init() {
        this.setupEventListeners();
        this.setupStateListener();
    },

    setupEventListeners() {
        // Save Button
        document.getElementById('btn-save').addEventListener('click', () => this.handleSubmit());

        // Delete (Archive) Button
        document.getElementById('btn-delete').addEventListener('click', () => this.handleDelete());

        // Close Modal
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', () => this.close());
        });
    },

    /**
     * FIX: Listens for state changes to populate counties.
     */
    setupStateListener() {
        const stateInput = document.getElementById('travel-state');
        const countyInput = document.getElementById('travel-county');
        const countyList = document.getElementById('county-options');

        stateInput.addEventListener('change', (e) => {
            const stateAbbr = e.target.value;
            
            // Clear existing counties
            countyList.innerHTML = '';
            countyInput.value = '';

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

    /**
     * Opens the modal and populates data for existing records.
     */
    open(date, id = null) {
        this.currentId = id;
        this.reset();
        
        document.getElementById('timesheet-date').value = date;
        const deleteBtn = document.getElementById('btn-delete');

        if (id) {
            deleteBtn.style.display = 'block';
            window.StechTimesheet.API.getTimesheet(id).then(data => {
                this.mapDataToForm(data);
            });
        } else {
            deleteBtn.style.display = 'none';
            window.StechTimesheet.Rows.add(); // Start with one empty row
        }

        document.getElementById('timesheet-modal').style.display = 'flex';
    },

    mapDataToForm(data) {
        document.getElementById('time-in').value = data.time_in || '';
        document.getElementById('time-out').value = data.time_out || '';
        document.getElementById('break-min').value = data.time_break || 0;
        document.getElementById('total-hours').value = data.time_total || 0;
        document.getElementById('additional-comments').value = data.additional_comments || '';
        document.getElementById('travel-state').value = data.travel_state || '';
        document.getElementById('travel-miles').value = data.travel_miles || 0;
        document.getElementById('travel-extra-expense').value = data.travel_extra_expenses || 0;
        document.getElementById('req-per-diem').checked = data.travel_per_diem == 1;

        // Populate County Options and Value
        if (data.travel_state) {
            window.StechTimesheet.API.getCounties(data.travel_state).then(counties => {
                const countyList = document.getElementById('county-options');
                countyList.innerHTML = '';
                counties.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.county_name;
                    countyList.appendChild(opt);
                });
                document.getElementById('travel-county').value = data.travel_county || '';
            });
        }

        // FIX: Render Activity Rows (Job Description and Percent)
        this.renderActivities(data.activities);
    },

    /**
     * FIX: Correctly pulls and adds activity rows to the UI.
     */
    renderActivities(activities) {
        const container = document.getElementById('work-rows-container');
        container.innerHTML = '';
        
        if (activities && activities.length > 0) {
            activities.forEach(act => {
                // Mapping activity_description and activity_percent from DB
                window.StechTimesheet.Rows.add(act.activity_description, act.activity_percent);
            });
        } else {
            window.StechTimesheet.Rows.add();
        }
    },

    async handleSubmit() {
        const formData = this.getFormData();
        try {
            await window.StechTimesheet.API.saveTimesheet(formData);
            this.close();
            window.StechTimesheet.Calendar.refresh();
        } catch (err) {
            console.error('Submission failed', err);
            alert('Error saving timesheet: ' + (err.response?.data?.error || 'Internal Server Error'));
        }
    },

    /**
     * Handles the Archiving (Soft Delete) of the record.
     */
    async handleDelete() {
        if (!this.currentId || !confirm('Are you sure you want to delete this entry?')) return;

        try {
            await window.StechTimesheet.API.deleteTimesheet(this.currentId);
            this.close();
            window.StechTimesheet.Calendar.refresh();
        } catch (err) {
            console.error('Delete failed', err);
            alert('Error deleting entry.');
        }
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
            state: document.getElementById('travel-state').value,
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
        document.getElementById('county-options').innerHTML = '';
    },

    close() {
        document.getElementById('timesheet-modal').style.display = 'none';
    }
};