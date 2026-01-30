document.addEventListener('DOMContentLoaded', function() {
    
    // --- MODAL HANDLERS ---
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }
    
    document.querySelectorAll('.close-modal').forEach(b => {
        b.addEventListener('click', function() {
            this.closest('.admin-modal').classList.add('hidden');
        });
    });

    // Close on click outside
    document.querySelectorAll('.admin-modal').forEach(m => {
        m.addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });

    // --- DASHBOARD CLICK EVENTS ---

    // 1. Users
    document.getElementById('card-users').addEventListener('click', () => {
        openModal('modal-users');
        loadUsers();
    });

    // 2. Holidays
    document.getElementById('card-holidays').addEventListener('click', () => {
        openModal('modal-holidays');
        loadHolidays();
    });

    // 3. Jobs
    document.getElementById('card-jobs').addEventListener('click', () => {
        openModal('modal-jobs');
        loadJobs();
    });

    // 4. Locations
    document.getElementById('card-locations').addEventListener('click', () => {
        openModal('modal-locations');
        loadStates();
    });

    // ================= LOGIC =================

    // --- USERS LOGIC ---
    function loadUsers() {
        const select = document.getElementById('user-select');
        select.innerHTML = '<option>Loading...</option>';
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users'))
            .then(r => r.json())
            .then(users => {
                select.innerHTML = '';
                users.forEach(u => {
                    select.innerHTML += `<option value="${u.uid}">${u.displayname}</option>`;
                });
            });
    }

    document.getElementById('btn-view-user').addEventListener('click', () => {
        const uid = document.getElementById('user-select').value;
        if(uid) window.location.href = OC.generateUrl('/apps/stech_timesheet/') + '?target_user=' + uid;
    });

    // --- HOLIDAY LOGIC ---
    function loadHolidays() {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'))
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('holiday-list');
                list.innerHTML = '';
                data.forEach(h => {
                    list.innerHTML += `
                        <div class="list-item">
                            <div>
                                <strong>${h.holiday_name}</strong><br>
                                <span class="item-meta">${h.holiday_start_date} to ${h.holiday_end_date}</span>
                            </div>
                            <button class="icon-delete" onclick="deleteHoliday(${h.holiday_id})"></button>
                        </div>`;
                });
            });
    }

    document.getElementById('form-holiday').addEventListener('submit', (e) => {
        e.preventDefault();
        const payload = {
            name: document.getElementById('holiday-name').value,
            start: document.getElementById('holiday-start').value,
            end: document.getElementById('holiday-end').value
        };
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'), {
            method: 'POST',
            headers: {'requesttoken': OC.requestToken, 'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(() => {
            document.getElementById('form-holiday').reset();
            loadHolidays();
        });
    });

    window.deleteHoliday = function(id) {
        if(confirm('Delete holiday?')) {
            fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays/' + id), { 
                method: 'DELETE', headers: {'requesttoken': OC.requestToken} 
            }).then(loadHolidays);
        }
    };

    // --- JOBS LOGIC ---
    function loadJobs() {
        // We reuse attributes API or create specific admin one. Assuming getAttributes fetches all for now
        // OR we use the admin specific API I defined in routes
        // For this example, let's assume we created /api/admin/jobs (GET) or reuse attributes
        fetch(OC.generateUrl('/apps/stech_timesheet/api/attributes'))
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('job-list');
                list.innerHTML = '';
                if(data.jobs) {
                    data.jobs.forEach(j => {
                        const isArchived = j.job_archive == 1;
                        list.innerHTML += `
                            <div class="list-item ${isArchived ? 'opacity-50' : ''}">
                                <span>${j.job_name}</span>
                                <label class="toggle-switch" title="Toggle Archive">
                                    <input type="checkbox" ${!isArchived ? 'checked' : ''} onchange="toggleJob(${j.job_id})">
                                    <span class="ts-slider"></span>
                                </label>
                            </div>`;
                    });
                }
            });
    }

    document.getElementById('form-job').addEventListener('submit', (e) => {
        e.preventDefault();
        const payload = {
            name: document.getElementById('job-name').value,
            description: document.getElementById('job-desc').value
        };
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs'), {
            method: 'POST',
            headers: {'requesttoken': OC.requestToken, 'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(() => {
            document.getElementById('form-job').reset();
            loadJobs();
        });
    });

    window.toggleJob = function(id) {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs/' + id + '/toggle'), {
            method: 'POST', headers: {'requesttoken': OC.requestToken}
        });
        // Visual update handled by toggle, or reload list
    };

    // --- LOCATIONS LOGIC ---
    function loadStates() {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/attributes')) // Should allow admin to see disabled states too
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('state-list');
                list.innerHTML = '';
                data.states.forEach(s => {
                    const isEnabled = s.is_enabled == 1;
                    const div = document.createElement('div');
                    div.className = 'list-item';
                    div.innerHTML = `
                        <span>${s.state_name}</span>
                        <label class="toggle-switch">
                            <input type="checkbox" ${isEnabled ? 'checked' : ''}>
                            <span class="ts-slider"></span>
                        </label>`;
                    
                    // Click text to load counties
                    div.querySelector('span').addEventListener('click', () => loadCounties(s.state_abbr, s.state_name));
                    // Toggle switch logic
                    div.querySelector('input').addEventListener('change', () => toggleState(s.id));
                    
                    list.appendChild(div);
                });
            });
    }

    function loadCounties(abbr, name) {
        document.getElementById('county-header').innerText = 'Counties: ' + name;
        const list = document.getElementById('county-list');
        list.innerHTML = 'Loading...';
        
        fetch(OC.generateUrl('/apps/stech_timesheet/api/counties/' + abbr))
            .then(r => r.json())
            .then(counties => {
                list.innerHTML = '';
                counties.forEach(c => {
                    const isEnabled = c.is_enabled == 1;
                    list.innerHTML += `
                        <div class="list-item">
                            <span>${c.county_name}</span>
                            <label class="toggle-switch">
                                <input type="checkbox" ${isEnabled ? 'checked' : ''} onchange="toggleCounty(${c.id})">
                                <span class="ts-slider"></span>
                            </label>
                        </div>`;
                });
            });
    }

    window.toggleState = function(id) {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states/' + id + '/toggle'), { method: 'POST', headers: {'requesttoken': OC.requestToken}});
    };
    window.toggleCounty = function(id) {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/' + id + '/toggle'), { method: 'POST', headers: {'requesttoken': OC.requestToken}});
    };

});