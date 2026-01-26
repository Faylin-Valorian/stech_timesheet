document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    const overlay = document.getElementById('timesheet-modal-overlay');
    const form = document.getElementById('timesheet-form');
    
    // Data Stores
    let jobOptions = [];
    
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
            // Manual Fetch to include Headers
            let url = OC.generateUrl('/apps/stech_timesheet/api/list') + '?start=' + info.startStr + '&end=' + info.endStr;
            fetch(url, {
                method: 'GET',
                headers: {
                    'requesttoken': OC.requestToken,
                    'OCS-APIRequest': 'true'
                }
            })
            .then(response => response.json())
            .then(data => successCallback(data))
            .catch(error => failureCallback(error));
        },
        eventContent: function(arg) {
            // Custom Rendering for Tags
            let div = document.createElement('div');
            div.style.padding = '2px 4px';
            div.style.borderRadius = '3px';
            div.style.backgroundColor = arg.event.backgroundColor;
            div.style.color = '#fff';
            div.style.fontSize = '0.85em';
            div.style.overflow = 'hidden';
            div.style.whiteSpace = 'nowrap';
            div.innerText = arg.event.title;
            return { domNodes: [div] };
        },
        dateClick: function(info) {
            openModal(info.dateStr);
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
        fetch(OC.generateUrl('/apps/stech_timesheet/api/attributes'), {
            headers: {
                'requesttoken': OC.requestToken,
                'OCS-APIRequest': 'true'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error("API Request Failed");
            return response.json();
        })
        .then(data => {
            // Store Jobs
            if (data.jobs) {
                jobOptions = data.jobs;
            }
            
            // Populate States
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
        })
        .catch(console.error);
    }

    // State Change Listener
    const stateSelect = document.getElementById('travel-state');
    if (stateSelect) {
        stateSelect.addEventListener('change', function() {
            const val = this.value;
            const countySelect = document.getElementById('travel-county');
            countySelect.innerHTML = '<option value="">Loading...</option>';
            
            if (val) {
                fetch(OC.generateUrl('/apps/stech_timesheet/api/counties/' + val), {
                    headers: {
                        'requesttoken': OC.requestToken,
                        'OCS-APIRequest': 'true'
                    }
                })
                .then(res => res.json())
                .then(counties => {
                    countySelect.innerHTML = '<option value="">Select County...</option>';
                    counties.forEach(c => {
                        let opt = document.createElement('option');
                        opt.value = c.county_name;
                        opt.innerText = c.county_name;
                        countySelect.appendChild(opt);
                    });
                });
            } else {
                countySelect.innerHTML = '<option value="">Select County...</option>';
            }
        });
    }

    // 4. Modal Functions
    function openModal(dateStr) {
        var dateInput = document.getElementById('entry-date');
        form.reset();
        
        if (dateInput) dateInput.value = dateStr;
        
        document.getElementById('work-rows-container').innerHTML = ''; 
        addWorkRow(); 
        document.getElementById('travel-fields-container').classList.remove('visible');

        if (overlay) overlay.style.display = 'flex';
    }

    function closeModal() {
        if (overlay) overlay.style.display = 'none';
    }

    document.getElementById('btn-cancel').addEventListener('click', closeModal);
    document.getElementById('modal-close-btn').addEventListener('click', closeModal);

    // 5. Dynamic Rows
    document.getElementById('btn-add-row').addEventListener('click', addWorkRow);

    function addWorkRow() {
        const container = document.getElementById('work-rows-container');
        const row = document.createElement('div');
        row.className = 'work-row';
        
        // Build Select Options
        let optionsHtml = '<option value="">Select Job...</option>';
        if (jobOptions) {
            jobOptions.forEach(job => {
                optionsHtml += `<option value="${job.job_name}">${job.job_name}</option>`;
            });
        }

        row.innerHTML = `
            <select name="work_desc[]" class="form-control">${optionsHtml}</select>
            <input type="number" name="work_percent[]" class="form-control text-center" placeholder="0" min="0" max="100">
            <div class="btn-remove-row" title="Remove">&times;</div>
        `;
        
        row.querySelector('.btn-remove-row').addEventListener('click', () => row.remove());
        container.appendChild(row);
    }

    // 6. Form Submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const payload = new URLSearchParams(formData);

        fetch(OC.generateUrl('/apps/stech_timesheet/api/save'), {
            method: 'POST',
            body: payload,
            headers: {
                'requesttoken': OC.requestToken,
                'OCS-APIRequest': 'true'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.error) {
                alert(result.error);
            } else {
                closeModal();
                calendar.refetchEvents();
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error saving timesheet.');
        });
    });

    // --- Travel Toggle ---
    document.getElementById('toggle-travel').addEventListener('change', function() {
        const container = document.getElementById('travel-fields-container');
        if (this.checked) container.classList.add('visible');
        else container.classList.remove('visible');
    });

    // Auto Calc (Existing)
    const timeInputs = document.querySelectorAll('.calc-time');
    timeInputs.forEach(input => {
        input.addEventListener('change', calculateTotalHours);
    });

    function calculateTotalHours() {
        const inStr = document.getElementById('time-in').value;
        const outStr = document.getElementById('time-out').value;
        const breakMin = parseInt(document.getElementById('break-min').value) || 0;
        const totalEl = document.getElementById('total-hours');

        if (inStr && outStr) {
            let dateIn = new Date(`2000-01-01T${inStr}`);
            let dateOut = new Date(`2000-01-01T${outStr}`);
            if (dateOut < dateIn) dateOut.setDate(dateOut.getDate() + 1);

            let diffMins = Math.floor((dateOut - dateIn) / 60000) - breakMin;
            if (diffMins < 0) diffMins = 0;
            totalEl.value = (diffMins / 60).toFixed(2);
        } else {
            totalEl.value = "0.00";
        }
    }
    
    setupSidebarButtons(calendar);
});

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
        
        // Manual Open for Today
        var now = new Date();
        var offset = now.getTimezoneOffset();
        var today = new Date(now.getTime() - (offset*60*1000));
        var todayStr = today.toISOString().split('T')[0];

        // Ensure date populates
        var dateInput = document.getElementById('entry-date');
        var form = document.getElementById('timesheet-form');
        var overlay = document.getElementById('timesheet-modal-overlay');

        if (dateInput && form && overlay) {
            form.reset();
            dateInput.value = todayStr; // Force Value
            
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