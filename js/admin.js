document.addEventListener('DOMContentLoaded', function() {
    
    // --- STATE ---
    let allUsers = [];
    let allJobs = [];
    let allStates = [];
    let currentCounties = [];

    // --- HELPER: Fetch with Headers ---
    function apiFetch(url, options = {}) {
        if (!options.headers) options.headers = {};
        options.headers['requesttoken'] = OC.requestToken;
        return fetch(url, options);
    }

    // --- NAVIGATION LOGIC ---
    function switchView(viewId) {
        // Hide all views
        document.querySelectorAll('.admin-view').forEach(el => el.classList.add('hidden'));
        // Show target
        document.getElementById('view-' + viewId).classList.remove('hidden');
        
        // Update Sidebar
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        document.getElementById('nav-' + viewId).classList.add('active');

        // Load Data
        if(viewId === 'users') loadUsers();
        if(viewId === 'holidays') loadHolidays();
        if(viewId === 'jobs') loadJobs();
        if(viewId === 'locations') loadStates();
    }

    // Bind Nav Clicks
    document.getElementById('nav-users').addEventListener('click', () => switchView('users'));
    document.getElementById('nav-holidays').addEventListener('click', () => switchView('holidays'));
    document.getElementById('nav-jobs').addEventListener('click', () => switchView('jobs'));
    document.getElementById('nav-locations').addEventListener('click', () => switchView('locations'));

    // Initial Load
    loadUsers();

    // =========================================================
    // 1. USER MANAGEMENT
    // =========================================================
    function loadUsers() {
        // Only fetch if empty to save bandwidth, or always fetch if you prefer live data
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users'))
            .then(r => r.json())
            .then(u => { allUsers = u; }); // Data loaded, wait for search input
    }

    const userSearch = document.getElementById('user-search');
    const userDropdown = document.getElementById('user-dropdown-list');

    userSearch.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        userDropdown.innerHTML = '';
        
        if(term.length < 1) {
            userDropdown.classList.add('hidden');
            return;
        }

        const filtered = allUsers.filter(u => u.displayname.toLowerCase().includes(term));
        
        if (filtered.length === 0) {
            userDropdown.innerHTML = '<div style="padding:10px; opacity:0.6;">No users found</div>';
        } else {
            filtered.forEach(u => {
                const div = document.createElement('div');
                div.className = 'dropdown-item';
                div.innerText = u.displayname;
                div.addEventListener('click', () => {
                    userSearch.value = u.displayname;
                    document.getElementById('selected-user-uid').value = u.uid;
                    userDropdown.classList.add('hidden');
                    document.getElementById('btn-view-user').disabled = false;
                });
                userDropdown.appendChild(div);
            });
        }
        userDropdown.classList.remove('hidden');
    });

    // Close dropdown on click outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.searchable-select-wrapper')) userDropdown.classList.add('hidden');
    });

    document.getElementById('btn-view-user').addEventListener('click', () => {
        const uid = document.getElementById('selected-user-uid').value;
        if(uid) window.location.href = OC.generateUrl('/apps/stech_timesheet/') + '?target_user=' + uid;
    });


    // =========================================================
    // 2. HOLIDAYS
    // =========================================================
    function loadHolidays() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays')).then(r => r.json()).then(data => {
            const list = document.getElementById('holiday-list');
            list.innerHTML = '';
            data.forEach(h => {
                list.innerHTML += `
                    <div class="list-item">
                        <div>
                            <strong>${h.holiday_name}</strong><br>
                            <span style="font-size:11px; opacity:0.6">${h.holiday_start_date} to ${h.holiday_end_date}</span>
                        </div>
                        <button class="icon-delete" onclick="deleteHoliday(${h.holiday_id})" title="Delete"></button>
                    </div>`;
            });
        });
    }

    document.getElementById('form-holiday').addEventListener('submit', (e) => {
        e.preventDefault();
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'), {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                name: document.getElementById('holiday-name').value,
                start: document.getElementById('holiday-start').value,
                end: document.getElementById('holiday-end').value
            })
        }).then(() => {
            document.getElementById('form-holiday').reset();
            loadHolidays();
        });
    });

    window.deleteHoliday = function(id) {
        if(confirm('Delete holiday?')) {
            apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays/'+id), { method:'DELETE' })
            .then(loadHolidays);
        }
    };


    // =========================================================
    // 3. JOBS
    // =========================================================
    function loadJobs() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/attributes'))
            .then(r => r.json())
            .then(d => { allJobs = d.jobs || []; renderJobs(); });
    }

    function renderJobs() {
        const term = document.getElementById('job-search-input').value.toLowerCase();
        const status = document.querySelector('input[name="job-status"]:checked').value;
        const list = document.getElementById('job-list');
        list.innerHTML = '';

        allJobs.filter(j => {
            const active = j.job_archive == 0;
            if(status === 'active' && !active) return false;
            if(status === 'archived' && active) return false;
            return j.job_name.toLowerCase().includes(term);
        }).forEach(j => {
            const active = j.job_archive == 0;
            list.innerHTML += `
                <div class="list-item" style="${!active?'opacity:0.6':''}">
                    <span>${j.job_name}</span>
                    <label class="admin-switch">
                        <input type="checkbox" ${active?'checked':''} onchange="toggleJob(${j.job_id})">
                        <span class="admin-slider"></span>
                    </label>
                </div>`;
        });
    }

    document.getElementById('job-search-input').addEventListener('input', renderJobs);
    document.querySelectorAll('input[name="job-status"]').forEach(r => r.addEventListener('change', renderJobs));

    document.getElementById('form-job').addEventListener('submit', (e) => {
        e.preventDefault();
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs'), {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                name: document.getElementById('job-name').value,
                description: document.getElementById('job-desc').value
            })
        }).then(() => {
            document.getElementById('form-job').reset();
            loadJobs();
        });
    });

    window.toggleJob = function(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs/'+id+'/toggle'), { method:'POST' })
        .then(loadJobs);
    };


    // =========================================================
    // 4. LOCATIONS
    // =========================================================
    function loadStates() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/attributes'))
            .then(r => r.json())
            .then(d => { allStates = d.states || []; renderStates(); });
    }

    function renderStates() {
        const term = document.getElementById('state-search-input').value.toLowerCase();
        const list = document.getElementById('state-list');
        list.innerHTML = '';

        allStates.filter(s => s.state_name.toLowerCase().includes(term))
        .forEach(s => {
            const div = document.createElement('div'); div.className = 'list-item';
            div.innerHTML = `
                <span style="cursor:pointer;flex:1">${s.state_name}</span>
                <label class="admin-switch">
                    <input type="checkbox" ${s.is_enabled == 1 ? 'checked' : ''}>
                    <span class="admin-slider"></span>
                </label>`;
            
            div.querySelector('span').addEventListener('click', () => {
                document.querySelectorAll('#state-list .list-item').forEach(el => el.classList.remove('active-selection'));
                div.classList.add('active-selection');
                loadCounties(s.state_abbr, s.state_name);
            });
            div.querySelector('input').addEventListener('change', () => toggleState(s.id));
            list.appendChild(div);
        });
    }
    document.getElementById('state-search-input').addEventListener('input', renderStates);

    window.toggleState = function(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states/'+id+'/toggle'), { method:'POST' });
    };

    function loadCounties(abbr, name) {
        document.getElementById('county-header').innerText = 'Counties: ' + name;
        document.getElementById('county-search-input').disabled = false;
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/counties/'+abbr))
            .then(r => r.json())
            .then(c => { currentCounties = c; renderCounties(); });
    }

    function renderCounties() {
        const term = document.getElementById('county-search-input').value.toLowerCase();
        const list = document.getElementById('county-list');
        list.innerHTML = '';

        currentCounties.filter(c => c.county_name.toLowerCase().includes(term))
        .forEach(c => {
            list.innerHTML += `
                <div class="list-item">
                    <span>${c.county_name}</span>
                    <label class="admin-switch">
                        <input type="checkbox" ${c.is_enabled == 1 ? 'checked' : ''} onchange="toggleCounty(${c.id})">
                        <span class="admin-slider"></span>
                    </label>
                </div>`;
        });
    }
    document.getElementById('county-search-input').addEventListener('input', renderCounties);

    window.toggleCounty = function(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/'+id+'/toggle'), { method:'POST' });
    };

});