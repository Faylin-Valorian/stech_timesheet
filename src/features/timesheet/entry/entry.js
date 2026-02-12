import { StechAPI } from '../../../api.js';
import { ActivityRows } from './rows.js';
import { CalendarFeature } from '../calendar/calendar.js';

export const EntryForm = {
    currentId: null,
    jobOptions: [],
    stateMap: {},
    isArchivedRecord: false,

    async init() {
        await this.loadAttributes();
        this.bindEvents();
        this.initTimeWidgets(); // Logic from main.js
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
            this.handleSubmit();
        });

        // Add Row Button
        document.getElementById('btn-add-row')?.addEventListener('click', (e) => {
            e.preventDefault();
            ActivityRows.add('work-rows-container', this.jobOptions, '', 0, true);
        });

        // Delete/Restore Button
        document.getElementById('btn-delete')?.addEventListener('click', (e) => {
            e.preventDefault();
            if (this.isArchivedRecord) this.showConfirmRestore();
            else this.showConfirmArchive();
        });

        // Time Calculation Listeners
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

        // Close Buttons
        document.querySelectorAll('.close-modal, .secondary-button').forEach(btn => {
            if (btn.id.startsWith('btn-confirm')) return; // Skip dynamic buttons
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.close();
            });
        });
    },

    // --- 12-Hour Widget Logic (Ported from main.js) ---
    initTimeWidgets() {
        const timeWidgets = document.querySelectorAll('.time-split-widget');
        timeWidgets.forEach(widget => {
            const hourSelect = widget.querySelector('.hour-select');
            const minSelect = widget.querySelector('.minute-select');
            const ampmSelect = widget.querySelector('.ampm-select');
            const hiddenInput = widget.querySelector('.combined-time-input');

            // UI -> Hidden Input
            const updateHidden = () => {
                if (hourSelect.value && minSelect.value && ampmSelect.value) {
                    let h = parseInt(hourSelect.value, 10);
                    const m = minSelect.value;
                    const amp = ampmSelect.value;
                    if (amp === 'PM' && h < 12) h += 12;
                    if (amp === 'AM' && h === 12) h = 0;
                    hiddenInput.value = `${h.toString().padStart(2, '0')}:${m}`;
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    hiddenInput.value = '';
                }
            };

            // Hidden Input -> UI
            hiddenInput.refreshWidget = function() {
                const val = hiddenInput.value;
                if (val && val.includes(':')) {
                    const parts = val.split(':');
                    let h = parseInt(parts[0], 10);
                    const m = parts[1];
                    let amp = 'AM';
                    if (h >= 12) { amp = 'PM'; if (h > 12) h -= 12; }
                    if (h === 0) h = 12;
                    hourSelect.value = h.toString().padStart(2, '0');
                    minSelect.value = m;
                    ampmSelect.value = amp;
                } else {
                    hourSelect.value = ""; minSelect.value = ""; ampmSelect.value = "AM";
                }
            };

            hourSelect.addEventListener('change', updateHidden);
            minSelect.addEventListener('change', updateHidden);
            ampmSelect.addEventListener('change', updateHidden);
        });
    },

    // --- Logic: PTO Auto Fill ---
    handlePTOAutoFill() {
        const tIn = document.getElementById('time-in');
        const tOut = document.getElementById('time-out');
        const brk = document.getElementById('break-min');

        if (!tIn.value && !tOut.value) {
            tIn.value = "08:00"; tOut.value = "17:00"; brk.value = "60";
            
            // Refresh 12h widgets
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
                
                // Admin Override Logic
                const isArchived = parseInt(data.archive) === 1;
                const isAdmin = data.is_admin;
                
                if (isArchived) {
                    if (!isAdmin) {
                        this.showCenteredError("This record is locked/archived and cannot be edited.");
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
            // New Entry
            deleteBtn.style.display = 'none';
            ActivityRows.add('work-rows-container', this.jobOptions, '', 0, true);
            document.getElementById('timesheet-modal').style.display = 'flex';
        }
    },

    mapDataToForm(data) {
        document.getElementById('time-in').value = data.time_in || '';
        document.getElementById('time-out').value = data.time_out || '';
        
        // Update Widgets
        const tIn = document.getElementById('time-in');
        const tOut = document.getElementById('time-out');
        if(tIn.refreshWidget) tIn.refreshWidget();
        if(tOut.refreshWidget) tOut.refreshWidget();
        
        document.getElementById('break-min').value = data.time_break || 0;
        document.getElementById('total-hours').value = data.time_total || 0;
        document.getElementById('additional-comments').value = data.additional_comments || '';
        
        // State Lookup (Reverse Map)
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

        // Activities
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

    async handleSubmit() {
        const formData = this.getFormData();
        
        if (!formData.time_in && !formData.req_per_diem) {
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

    // --- Custom UI Overlays (Preserved) ---
    showCenteredError(msg) {
        let overlay = document.getElementById('stech-centered-error');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'stech-centered-error';
            // Styling handled by global CSS or inline if originally JS-driven
            // Assuming classes exist based on previous file context
            overlay.innerHTML = `
                <div class="stech-error-content">
                    <h3>Access Denied</h3>
                    <p id="stech-error-msg-text"></p>
                    <button class="primary-button" id="btn-err-close">Close</button>
                </div>`;
            document.body.appendChild(overlay);
            document.getElementById('btn-err-close').onclick = () => overlay.style.display = 'none';
        }
        document.getElementById('stech-error-msg-text').textContent = msg;
        overlay.style.display = 'flex';
    },

    showConfirmArchive() {
        let overlay = document.getElementById('stech-confirm-archive');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'stech-confirm-archive';
            overlay.style.cssText = `position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:10002;display:none;align-items:center;justify-content:center;backdrop-filter:blur(2px);`;
            document.body.appendChild(overlay);
        }
        overlay.innerHTML = `
            <div class="stech-error-content" style="border-top-color:#e67e22;background:white;padding:20px;border-radius:8px;">
                <h3 style="color:#e67e22;margin-top:0;">Archive Record?</h3>
                <p>Are you sure? It will be hidden from the main view.</p>
                <div style="display:flex;gap:10px;justify-content:center;margin-top:20px;">
                    <button id="btn-conf-arch-yes" class="primary-button">Yes, Archive</button>
                    <button id="btn-conf-arch-no" class="secondary-button">Cancel</button>
                </div>
            </div>`;
        
        document.getElementById('btn-conf-arch-yes').onclick = async () => {
            overlay.style.display = 'none';
            await StechAPI.request('post', `/api/entry/${this.currentId}/delete`);
            this.close();
            CalendarFeature.refresh();
        };
        document.getElementById('btn-conf-arch-no').onclick = () => overlay.style.display = 'none';
        overlay.style.display = 'flex';
    },

    showConfirmRestore() {
        let overlay = document.getElementById('stech-confirm-restore');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'stech-confirm-restore';
            overlay.style.cssText = `position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:10002;display:none;align-items:center;justify-content:center;backdrop-filter:blur(2px);`;
            document.body.appendChild(overlay);
        }
        overlay.innerHTML = `
            <div class="stech-error-content" style="border-top-color:#28a745;background:white;padding:20px;border-radius:8px;">
                <h3 style="color:#28a745;margin-top:0;">Restore Record?</h3>
                <p>This will move the record back to the active list.</p>
                <div style="display:flex;gap:10px;justify-content:center;margin-top:20px;">
                    <button id="btn-conf-rest-yes" class="primary-button" style="background:#28a745;">Yes, Restore</button>
                    <button id="btn-conf-rest-no" class="secondary-button">Cancel</button>
                </div>
            </div>`;
            
        document.getElementById('btn-conf-rest-yes').onclick = async () => {
            overlay.style.display = 'none';
            await StechAPI.request('post', `/api/entry/${this.currentId}/restore`);
            this.close();
            CalendarFeature.refresh();
        };
        document.getElementById('btn-conf-rest-no').onclick = () => overlay.style.display = 'none';
        overlay.style.display = 'flex';
    },

    // --- Helpers ---
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
        
        // Reset 12h widgets
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