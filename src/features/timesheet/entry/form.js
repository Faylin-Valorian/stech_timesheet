import { StechAPI } from '../../../api.js';
import { ActivityRows } from './rows.js';
import { CalendarFeature } from '../calendar/calendar.js';
// New Imports
import { Modals } from './modals.js';
import { TimeWidgets } from './time-widgets.js';

export const EntryForm = {
    currentId: null,
    jobOptions: [],
    stateMap: {},
    isArchivedRecord: false,

    async init() {
        await this.loadAttributes();
        this.bindEvents();
        TimeWidgets.init(); // Delegated logic
    },

    async loadAttributes() {
        try {
            const attr = await StechAPI.request('get', '/api/entry/attributes');
            this.jobOptions = attr.jobs || [];
            
            const dl = document.getElementById('state-options');
            if (dl && attr.states) {
                dl.innerHTML = '';
                attr.states.forEach(s => {
                    this.stateMap[s.state_name] = s.state_abbr;
                    const opt = document.createElement('option');
                    opt.value = s.state_name;
                    dl.appendChild(opt);
                });
            }
        } catch (err) { console.error("Failed to load attributes", err); }
    },

    bindEvents() {
        document.getElementById('btn-save')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.validateAndSave();
        });

        document.getElementById('btn-add-row')?.addEventListener('click', (e) => {
            e.preventDefault();
            ActivityRows.add('work-rows-container', this.jobOptions, '', 0, true);
        });

        // Delete/Restore using Modular Modals
        document.getElementById('btn-delete')?.addEventListener('click', (e) => {
            e.preventDefault();
            const action = this.isArchivedRecord ? 'restore' : 'archive';
            
            Modals.showConfirm(action, async () => {
                const endpoint = action === 'restore' ? 'restore' : 'delete';
                await StechAPI.request('post', `/api/entry/${this.currentId}/${endpoint}`);
                this.close();
                CalendarFeature.refresh();
                if (window.OCP?.Toast) window.OCP.Toast.info(`Record ${action}d`);
            });
        });

        // Time Calculation
        ['time-in', 'time-out', 'break-min'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => this.calcTotal());
        });

        // Toggles
        document.getElementById('toggle-travel')?.addEventListener('change', (e) => {
            document.getElementById('travel-fields-container').style.display = e.target.checked ? 'block' : 'none';
        });

        document.getElementById('toggle-pto')?.addEventListener('change', (e) => {
            if (e.target.checked) this.handlePTOAutoFill();
        });

        document.getElementById('travel-state')?.addEventListener('change', (e) => this.loadCounties(e.target.value));

        document.querySelectorAll('.close-modal, .secondary-button').forEach(btn => {
            if (btn.id.startsWith('btn-')) return; 
            btn.addEventListener('click', (e) => { e.preventDefault(); this.close(); });
        });
    },

    // --- Logic: PTO Auto Fill ---
    handlePTOAutoFill() {
        const tIn = document.getElementById('time-in');
        const tOut = document.getElementById('time-out');
        const brk = document.getElementById('break-min');

        if (!tIn.value && !tOut.value) {
            tIn.value = "08:00"; tOut.value = "17:00"; brk.value = "60";
            
            if(tIn.refreshWidget) tIn.refreshWidget();
            if(tOut.refreshWidget) tOut.refreshWidget();

            this.calcTotal();

            const ptoJob = this.jobOptions.find(j => parseInt(j.is_pto) === 1);
            if (ptoJob) {
                document.getElementById('work-rows-container').innerHTML = '';
                ActivityRows.add('work-rows-container', this.jobOptions, ptoJob.job_name, 100, true);
            }
        }
    },

    // --- Logic: Open/Populate ---
    async open(date, id = null) {
        this.reset();
        this.currentId = id;
        document.getElementById('timesheet-date').value = date;
        const deleteBtn = document.getElementById('btn-delete');
        
        deleteBtn.textContent = "Delete";
        deleteBtn.style.backgroundColor = '';
        this.isArchivedRecord = false;

        if (id) {
            try {
                const data = await StechAPI.request('get', `/api/entry/${id}`);
                
                const isArchived = parseInt(data.archive) === 1;
                const isAdmin = data.is_admin;
                
                if (isArchived) {
                    if (!isAdmin) {
                        Modals.showError("This record is locked/archived and cannot be edited.");
                        this.currentId = null;
                        return;
                    } else {
                        this.isArchivedRecord = true;
                        deleteBtn.textContent = "Restore Record";
                        deleteBtn.style.backgroundColor = '#28a745';
                        deleteBtn.style.display = 'block';
                        if (window.OCP?.Toast) window.OCP.Toast.info("Admin Override: Editing Archived Record");
                    }
                } else {
                    deleteBtn.style.display = 'block';
                }

                this.mapDataToForm(data);
                document.getElementById('timesheet-modal').style.display = 'flex';
            } catch (err) { console.error(err); }
        } else {
            deleteBtn.style.display = 'none';
            ActivityRows.add('work-rows-container', this.jobOptions, '', 0, true);
            document.getElementById('timesheet-modal').style.display = 'flex';
        }
    },

    mapDataToForm(data) {
        // ... (This function remains largely the same, just removed the widget refresh logic as it handles itself)
        document.getElementById('time-in').value = data.time_in || '';
        document.getElementById('time-out').value = data.time_out || '';
        
        // Refresh Widgets
        const tIn = document.getElementById('time-in');
        const tOut = document.getElementById('time-out');
        if(tIn.refreshWidget) tIn.refreshWidget();
        if(tOut.refreshWidget) tOut.refreshWidget();
        
        document.getElementById('break-min').value = data.time_break || 0;
        document.getElementById('total-hours').value = data.time_total || 0;
        document.getElementById('additional-comments').value = data.additional_comments || '';
        
        const stateName = Object.keys(this.stateMap).find(key => this.stateMap[key] === data.travel_state);
        document.getElementById('travel-state').value = stateName || '';
        document.getElementById('travel-county').value = data.travel_county || '';
        document.getElementById('travel-miles').value = data.travel_miles || 0;
        document.getElementById('travel-extra-expense').value = data.travel_extra_expenses || 0;
        document.getElementById('req-per-diem').checked = data.travel_per_diem == 1;

        document.getElementById('road-scanning').checked = parseInt(data.travel_road_scanning) === 1;
        document.getElementById('first-last-day').checked = parseInt(data.travel_first_last_day) === 1;
        document.getElementById('overnight').checked = parseInt(data.travel_overnight) === 1;

        if (data.travel == 1) {
            document.getElementById('toggle-travel').checked = true;
            document.getElementById('travel-fields-container').style.display = 'block';
        }

        const container = document.getElementById('work-rows-container');
        container.innerHTML = '';
        if (data.activities && data.activities.length > 0) {
            data.activities.forEach(act => {
                ActivityRows.add('work-rows-container', this.jobOptions, act.activity_description, act.activity_percent, false);
            });
        } else {
            ActivityRows.add('work-rows-container', this.jobOptions, '', 0, true);
        }
    },

    async validateAndSave() {
        const formData = this.getFormData();
        
        if (!formData.time_in && !formData.req_per_diem) {
            // Using standard alert for validation as per request, or Modals.showError if preferred
            if (window.OCP?.Toast) window.OCP.Toast.error("You must enter a Sign In Time OR request Per Diem.");
            else alert("You must enter a Sign In Time OR request Per Diem.");
            return;
        }

        if (formData.time_out) {
            const hasDesc = formData.work_desc.some(d => d.trim() !== '');
            if (!hasDesc) {
                if (window.OCP?.Toast) window.OCP.Toast.error("You cannot Clock Out without entering a Job Description.");
                else alert("You cannot Clock Out without entering a Job Description.");
                return;
            }
        }

        try {
            const res = await StechAPI.request('post', '/api/entry/save', formData);
            if (res.status === 'success') {
                this.close();
                CalendarFeature.refresh();
            }
        } catch (err) { console.error('Save failed', err); }
    },

    calcTotal() {
        const t1 = document.getElementById('time-in').value;
        const t2 = document.getElementById('time-out').value;
        const brk = parseInt(document.getElementById('break-min').value) || 0;
        if (t1 && t2) {
            const d1 = new Date(`2000-01-01T${t1}`);
            const d2 = new Date(`2000-01-01T${t2}`);
            if (d2 < d1) d2.setDate(d2.getDate() + 1);
            let diffMins = Math.floor((d2 - d1) / 60000) - brk;
            if (diffMins < 0) diffMins = 0;
            document.getElementById('total-hours').value = (diffMins / 60).toFixed(2);
        }
    },

    async loadCounties(stateName) {
        const abbr = this.stateMap[stateName];
        if (!abbr) return;
        const counties = await StechAPI.request('get', `/api/entry/counties/${abbr}`);
        const dl = document.getElementById('county-options');
        dl.innerHTML = '';
        counties.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.county_name;
            dl.appendChild(opt);
        });
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
            state: this.stateMap[document.getElementById('travel-state').value] || '',
            county: document.getElementById('travel-county').value,
            miles: document.getElementById('travel-miles').value,
            extra_expense: document.getElementById('travel-extra-expense').value,
            req_per_diem: document.getElementById('req-per-diem').checked ? 1 : 0,
            road_scanning: document.getElementById('road-scanning').checked ? 1 : 0,
            first_last_day: document.getElementById('first-last-day').checked ? 1 : 0,
            overnight: document.getElementById('overnight').checked ? 1 : 0,
            work_desc: workDesc,
            work_percent: workPercent
        };
    },

    reset() {
        this.currentId = null;
        this.isArchivedRecord = false;
        document.getElementById('timesheet-form').reset();
        
        const tIn = document.getElementById('time-in');
        const tOut = document.getElementById('time-out');
        if(tIn.refreshWidget) tIn.refreshWidget();
        if(tOut.refreshWidget) tOut.refreshWidget();

        document.getElementById('work-rows-container').innerHTML = '';
        document.getElementById('travel-fields-container').style.display = 'none';
        document.getElementById('btn-delete').style.display = 'none';
        
        if(document.getElementById('toggle-travel')) document.getElementById('toggle-travel').checked = false;
        if(document.getElementById('toggle-pto')) document.getElementById('toggle-pto').checked = false;
    },

    close() { document.getElementById('timesheet-modal').style.display = 'none'; }
};