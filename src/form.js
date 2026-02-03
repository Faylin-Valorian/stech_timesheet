import { StechAPI } from './api.js';
import { ActivityRows } from './rows.js';

/**
 * Form Module
 * Manages the Timesheet Entry modal and submission logic.
 */
export const TimesheetForm = {
    overlay: null,
    form: null,

    init() {
        this.overlay = document.getElementById('timesheet-modal-overlay');
        this.form = document.getElementById('timesheet-form');
        this.setupListeners();
    },

    setupListeners() {
        // Basic Modal Controls
        document.getElementById('btn-cancel')?.addEventListener('click', () => this.close());
        document.getElementById('modal-close-btn')?.addEventListener('click', () => this.close());
        document.getElementById('btn-add-row')?.addEventListener('click', () => ActivityRows.add());

        // Travel Toggle
        document.getElementById('toggle-travel')?.addEventListener('change', function() {
            const container = document.getElementById('travel-fields-container');
            this.checked ? container.classList.add('visible') : container.classList.remove('visible');
        });

        // State/County Dynamic Search
        document.getElementById('travel-state')?.addEventListener('change', async (e) => {
            const val = e.target.value;
            const abbr = window.StechTimesheet.state.stateMap[val];
            const countyList = document.getElementById('county-options');
            countyList.innerHTML = '';
            
            if (abbr) {
                try {
                    const counties = await StechAPI.getCounties(abbr);
                    counties.forEach(c => {
                        let opt = document.createElement('option');
                        opt.value = c.county_name;
                        countyList.appendChild(opt);
                    });
                } catch (err) {
                    console.error("Failed to fetch counties", err);
                }
            }
        });

        // PTO Auto-Fill logic
        document.getElementById('toggle-pto')?.addEventListener('change', (e) => this.handlePTOToggle(e.target));

        // Form Submission
        this.form?.addEventListener('submit', (e) => this.handleSubmit(e));
    },

    open(dateStr, existingData) {
        this.form.reset();
        document.getElementById('entry-date').value = dateStr;
        ActivityRows.clear();
        document.getElementById('travel-fields-container').classList.remove('visible');
        document.getElementById('timesheet_id').value = existingData ? existingData.timesheet_id : '';

        // Reset toggles
        ['toggle-pto', 'toggle-travel', 'req-per-diem', 'road-scanning', 'first-last-day', 'overnight'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.checked = false;
        });

        if (existingData) {
            this.populateExistingData(existingData);
        } else {
            ActivityRows.add();
        }

        if (this.overlay) this.overlay.style.display = 'flex';
    },

    close() {
        if (this.overlay) this.overlay.style.display = 'none';
    },

    populateExistingData(data) {
        document.getElementById('time-in').value = data.time_in || '';
        document.getElementById('time-out').value = data.time_out || '';
        document.getElementById('break-min').value = data.time_break || 0;
        document.getElementById('total-hours').value = data.time_total || 0;
        
        let comms = data.additional_comments || '';
        if (comms.includes('[PTO]')) {
            document.getElementById('toggle-pto').checked = true;
            comms = comms.replace('[PTO]', '').trim();
        }
        document.getElementById('comments').value = comms;

        const hasTravel = (data.travel == 1 || data.travel_per_diem == 1 || data.travel_miles > 0 || (data.travel_state && data.travel_state !== ''));

        if (hasTravel) {
            document.getElementById('toggle-travel').checked = true;
            document.getElementById('travel-fields-container').classList.add('visible');
            document.getElementById('req-per-diem').checked = (data.travel_per_diem == 1);
            document.getElementById('road-scanning').checked = (data.travel_road_scanning == 1);
            document.getElementById('first-last-day').checked = (data.travel_first_last_day == 1);
            document.getElementById('overnight').checked = (data.travel_overnight == 1);
            document.getElementById('miles').value = data.travel_miles;
            document.getElementById('extra-expense').value = data.travel_extra_expenses;
            
            let stateName = data.travel_state || '';
            if (stateName.length === 2) stateName = window.StechTimesheet.state.stateMapRev[stateName] || stateName;
            
            document.getElementById('travel-state').value = stateName;
            document.getElementById('travel-county').value = data.travel_county;
            document.getElementById('travel-state').dispatchEvent(new Event('change'));
        }

        if (data.activities && data.activities.length > 0) {
            data.activities.forEach(act => ActivityRows.add(act.activity_description, act.activity_percent));
        } else {
            ActivityRows.add();
        }
    },

    handlePTOToggle(checkbox) {
        if (checkbox.checked) {
            const timeIn = document.getElementById('time-in');
            const timeOut = document.getElementById('time-out');
            if (!timeIn.value && !timeOut.value) {
                timeIn.value = '08:00';
                timeOut.value = '17:00';
                document.getElementById('break-min').value = '60';
                window.StechTimesheet.calculateTotalHours();

                const ptoJob = window.StechTimesheet.state.jobOptions.find(j => j.is_pto == 1);
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
        document.querySelectorAll('.work-percent-input').forEach(i => totalPercent += parseInt(i.value) || 0);
        
        if (totalPercent > 100) { 
            OC.dialogs.info('Total activity cannot exceed 100%.', 'Validation Error'); 
            return; 
        }

        const formData = new FormData(this.form);
        if (document.getElementById('toggle-pto').checked) {
            let c = formData.get('comments') || '';
            if (!c.includes('[PTO]')) formData.set('comments', '[PTO] ' + c);
        }

        const timeIn = formData.get('time_in');
        const perDiemChecked = document.getElementById('req-per-diem').checked;
        if (!timeIn && !perDiemChecked) { 
            OC.dialogs.info('Please enter a Start Time or select "Request Per Diem".', 'Validation Error'); 
            return; 
        }

        try {
            const result = await StechAPI.saveTimesheet(formData);
            if (result.error) {
                OC.dialogs.error(result.error, 'Error Saving');
            } else { 
                this.close(); 
                window.StechTimesheet.Calendar.refetch(); 
            }
        } catch (err) {
            OC.dialogs.error('There was a problem saving your entry.', 'Connection Error');
        }
    }
};