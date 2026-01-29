document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    const overlay = document.getElementById('timesheet-modal-overlay');
    const form = document.getElementById('timesheet-form');
    
    // Data Stores
    let jobOptions = [];
    
    // Helper for API URLs (Supports Admin Impersonation)
    function getApiUrl(endpoint) {
        const target = document.getElementById('global-target-user')?.value;
        let url = OC.generateUrl('/apps/stech_timesheet' + endpoint);
        if(target) {
            url += (url.includes('?') ? '&' : '?') + 'target_user=' + target;
        }
        return url;
    }

    // 1. Initial Data Fetch
    fetchAttributes();

    // 2. Calendar Setup
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        firstDay: 0,
        headerToolbar: false,
        height: '100%',
        themeSystem: 'standard',
        
        events: function(info, successCallback, failureCallback) {
            let url = getApiUrl('/api/list') + '&start=' + info.startStr + '&end=' + info.endStr;
            fetch(url, { headers: { 'requesttoken': OC.requestToken, 'OCS-APIRequest': 'true' } })
            .then(res => res.json())
            .then(data => successCallback(data))
            .catch(err => failureCallback(err));
        },
        
        eventContent: function(arg) {
            let div = document.createElement('div');
            div.className = 'fc-event-content-box'; 
            div.style.backgroundColor = arg.event.backgroundColor;
            div.innerText = arg.event.title;
            return { domNodes: [div] };
        },

        // --- CLICK TAB (EDIT) ---
        eventClick: function(info) {
            const id = info.event.id;
            fetch(getApiUrl('/api/get/' + id), {
                headers: { 'requesttoken': OC.requestToken, 'OCS-APIRequest': 'true' }
            })
            .then(res => res.json())
            .then(data => {
                openModal(data.timesheet_date, data);
            })
            .catch(err => console.error(err));
        },

        // --- CLICK DATE (NEW) ---
        dateClick: function(info) {
            const dayEvents = calendar.getEvents().filter(e => e.startStr === info.dateStr);
            const hasOpenEntry = dayEvents.some(e => !e.extendedProps.isClosed);

            if (hasOpenEntry) {
                OC.dialogs.alert('You have an open entry for this date. Clock out first.', 'Active Entry Exists');
            } else {
                openModal(info.dateStr, null);
            }
        },

        datesSet: function(info) {
            var titleEl = document.getElementById('current-date-label');
            if (titleEl) titleEl.innerText = info.view.title;
        },
        windowResize: function(view) { calendar.render(); }
    });
    calendar.render();

    // 3. API Calls
    function fetchAttributes() {
        fetch(getApiUrl('/api/attributes'), {
            headers: { 'requesttoken': OC.requestToken, 'OCS-APIRequest': 'true' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.jobs) jobOptions = data.jobs;
            
            const stateSelect = document.getElementById('travel-state');
            if (stateSelect && data.states) {
                stateSelect.innerHTML = '<option value="">Select State...</option>';
                data.states.forEach(state => {
                    let opt = document.createElement('option');
                    opt.value = state.state_abbr;
                    opt.innerText = state.state_name;
                    stateSelect.appendChild(opt);
                });
            }
        });
    }

    // State Change Listener
    const stateSelect = document.getElementById('travel-state');
    if (stateSelect) {
        stateSelect.addEventListener('change', function() {
            const val = this.value;
            const countySelect = document.getElementById('travel-county');
            countySelect.innerHTML = '<option value="">Loading...</option>';
            if (val) {
                fetch(getApiUrl('/api/counties/' + val), {
                    headers: { 'requesttoken': OC.requestToken, 'OCS-APIRequest': 'true' }
                }).then(res => res.json()).then(counties => {
                    countySelect.innerHTML = '<option value="">Select County...</option>';
                    counties.forEach(c => {
                        let opt = document.createElement('option');
                        opt.value = c.county_name;
                        opt.innerText = c.county_name;
                        countySelect.appendChild(opt);
                    });
                });
            } else { countySelect.innerHTML = '<option value="">Select County...</option>'; }
        });
    }

    // 4. Modal Functions (Populator)
    function openModal(dateStr, existingData) {
        form.reset();
        document.getElementById('entry-date').value = dateStr;
        document.getElementById('work-rows-container').innerHTML = '';
        document.getElementById('travel-fields-container').classList.remove('visible');
        
        // Reset ID
        document.getElementById('timesheet_id').value = existingData ? existingData.timesheet_id : '';

        // Reset Checkboxes (Important for "New" mode)
        ['toggle-pto', 'toggle-travel', 'req-per-diem', 'road-scanning', 'first-last-day', 'overnight'].forEach(id => {
            if(document.getElementById(id)) document.getElementById(id).checked = false;
        });

        if (existingData) {
            // Populate Basic Fields
            document.getElementById('time-in').value = existingData.time_in || '';
            document.getElementById('time-out').value = existingData.time_out || '';
            document.getElementById('break-min').value = existingData.time_break || 0;
            document.getElementById('total-hours').value = existingData.time_total || 0;
            
            // Populate Comments (Strip PTO tag for display)
            let comms = existingData.additional_comments || '';
            if (comms.includes('[PTO]')) {
                document.getElementById('toggle-pto').checked = true;
                comms = comms.replace('[PTO]', '').trim();
            }
            document.getElementById('comments').value = comms;

            // Travel Logic
            if (existingData.travel == 1) {
                document.getElementById('toggle-travel').checked = true;
                document.getElementById('travel-fields-container').classList.add('visible');
                
                // Set Checkboxes
                document.getElementById('req-per-diem').checked = (existingData.travel_per_diem == 1);
                document.getElementById('road-scanning').checked = (existingData.travel_road_scanning == 1);
                document.getElementById('first-last-day').checked = (existingData.travel_first_last_day == 1);
                document.getElementById('overnight').checked = (existingData.travel_overnight == 1);
                
                // Set Values
                document.getElementById('miles').value = existingData.travel_miles;
                document.getElementById('extra-expense').value = existingData.travel_extra_expenses;
                document.getElementById('travel-state').value = existingData.travel_state;
                // Note: County dropdown won't populate instantly if state wasn't loaded, 
                // but setting value usually works if options exist.
            }

            // Activities
            if (existingData.activities && existingData.activities.length > 0) {
                existingData.activities.forEach(act => addWorkRow(act.activity_description, act.activity_percent));
            } else {
                addWorkRow();
            }

        } else {
            // New Entry
            addWorkRow();
        }

        if (overlay) overlay.style.display = 'flex';
    }

    function closeModal() {
        if (overlay) overlay.style.display = 'none';
    }

    document.getElementById('btn-cancel').addEventListener('click', closeModal);
    document.getElementById('modal-close-btn').addEventListener('click', closeModal);

    // 5. Dynamic Rows
    document.getElementById('btn-add-row').addEventListener('click', () => addWorkRow());

    function addWorkRow(descVal = '', percentVal = '') {
        const container = document.getElementById('work-rows-container');
        const row = document.createElement('div');
        row.className = 'work-row';
        
        let optionsHtml = '<option value="">Select Job...</option>';
        if (jobOptions) {
            jobOptions.forEach(job => {
                const selected = (job.job_name === descVal) ? 'selected' : '';
                optionsHtml += `<option value="${job.job_name}" ${selected}>${job.job_name}</option>`;
            });
        }

        row.innerHTML = `
            <select name="work_desc[]" class="form-control">${optionsHtml}</select>
            <input type="number" name="work_percent[]" class="form-control text-center work-percent-input" value="${percentVal}" placeholder="0" min="0" max="100">
            <div class="btn-remove-row" title="Remove">&times;</div>
        `;
        
        row.querySelector('.btn-remove-row').addEventListener('click', () => row.remove());
        container.appendChild(row);
    }

    // 6. Save
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        let totalPercent = 0;
        document.querySelectorAll('.work-percent-input').forEach(i => totalPercent += parseInt(i.value) || 0);
        if (totalPercent > 100) {
            alert('Total activity cannot exceed 100%.');
            return;
        }

        const formData = new FormData(form);
        const ptoToggle = document.getElementById('toggle-pto');
        if (ptoToggle && ptoToggle.checked) {
            let c = formData.get('comments') || '';
            if (!c.includes('[PTO]')) formData.set('comments', '[PTO] ' + c);
        }

        fetch(getApiUrl('/api/save'), {
            method: 'POST',
            body: new URLSearchParams(formData),
            headers: { 'requesttoken': OC.requestToken, 'OCS-APIRequest': 'true' }
        })
        .then(res => res.json())
        .then(result => {
            if (result.error) alert(result.error);
            else { closeModal(); calendar.refetchEvents(); }
        })
        .catch(() => alert('Error saving.'));
    });

    // --- Toggles & Calc ---
    document.getElementById('toggle-travel').addEventListener('change', function() {
        const container = document.getElementById('travel-fields-container');
        this.checked ? container.classList.add('visible') : container.classList.remove('visible');
    });

    const timeInputs = document.querySelectorAll('.calc-time');
    timeInputs.forEach(input => input.addEventListener('change', calculateTotalHours));

    function calculateTotalHours() {
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
        } else {
            totalEl.value = "0.00";
        }
    }
    
    setupSidebarButtons(calendar);
});

// Sidebar setup function
function setupSidebarButtons(calendar) {
    var prevBtn = document.getElementById('nav-prev');
    if (prevBtn) prevBtn.addEventListener('click', () => calendar.prev());

    var nextBtn = document.getElementById('nav-next');
    if (nextBtn) nextBtn.addEventListener('click', () => calendar.next());

    var monthBtn = document.getElementById('view-month');
    if (monthBtn) monthBtn.addEventListener('click', function() {
        calendar.changeView('dayGridMonth');
        toggleActive(this);
    });

    var weekBtn = document.getElementById('view-week');
    if (weekBtn) weekBtn.addEventListener('click', function() {
        calendar.changeView('dayGridWeek');
        toggleActive(this);
    });

    var todayBtn = document.getElementById('view-today');
    if (todayBtn) todayBtn.addEventListener('click', function() {
        calendar.today();
        
        var now = new Date();
        var offset = now.getTimezoneOffset();
        var today = new Date(now.getTime() - (offset*60*1000));
        var todayStr = today.toISOString().split('T')[0];

        var dateInput = document.getElementById('entry-date');
        var form = document.getElementById('timesheet-form');
        var overlay = document.getElementById('timesheet-modal-overlay');

        if (dateInput && form && overlay) {
            form.reset();
            dateInput.value = todayStr;
            document.getElementById('work-rows-container').innerHTML = '';
            document.getElementById('btn-add-row').click(); 
            document.getElementById('travel-fields-container').classList.remove('visible');
            overlay.style.display = 'flex';
        }
    });

    var dateLabel = document.getElementById('current-date-label');
    var dateInput = document.getElementById('date-picker-input');
    if (dateLabel && dateInput) {
        dateLabel.addEventListener('click', () => dateInput.showPicker());
        dateInput.addEventListener('change', function() {
            if (this.value) calendar.gotoDate(this.value + '-01');
        });
    }

    function toggleActive(activeBtn) {
        document.querySelectorAll('.view-buttons button').forEach(btn => btn.classList.remove('active'));
        activeBtn.classList.add('active');
    }
}