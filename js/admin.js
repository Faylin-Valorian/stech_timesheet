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

    // --- NAVIGATION ---
    function switchView(viewId) {
        document.querySelectorAll('.admin-view').forEach(el => el.classList.add('hidden'));
        const target = document.getElementById('view-' + viewId);
        if(target) target.classList.remove('hidden');
        
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        const nav = document.getElementById('nav-' + viewId);
        if(nav) nav.classList.add('active');

        if(viewId === 'users') loadUsers();
        if(viewId === 'holidays') loadHolidays();
        if(viewId === 'jobs') loadJobs();
        if(viewId === 'locations') loadStates();
    }

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
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users'))
            .then(r => r.json())
            .then(u => { allUsers = u; }); 
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
                const item = document.createElement('div');
                item.className = 'list-item';
                
                const info = document.createElement('div');
                info.innerHTML = `<strong>${h.holiday_name}</strong><br><span style="font-size:11px; opacity:0.6">${h.holiday_start_date} to ${h.holiday_end_date}</span>`;
                
                const btn = document.createElement('button');
                btn.className = 'icon-delete';
                btn.title = 'Delete';
                btn.addEventListener('click', () => deleteHoliday(h.holiday_id));
                
                item.appendChild(info);
                item.appendChild(btn);
                list.appendChild(item);
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

    function deleteHoliday(id) {
        if(confirm('Delete holiday?')) {
            apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays/'+id), { method:'DELETE' })
            .then(loadHolidays);
        }
    }


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
            const item = document.createElement('div');
            item.className = 'list-item';
            if(!active) item.style.opacity = '0.6';

            // Text
            const span = document.createElement('span');
            span.innerText = j.job_name;
            
            // Toggle
            const label = document.createElement('label');
            label.className = 'admin-switch';
            
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = active;
            input.addEventListener('change', () => toggleJob(j.job_id));
            
            const slider = document.createElement('span');
            slider.className = 'admin-slider';
            
            label.appendChild(input);
            label.appendChild(slider);
            
            item.appendChild(span);
            item.appendChild(label);
            list.appendChild(item);
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

    function toggleJob(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs/'+id+'/toggle'), { method:'POST' })
        .then(loadJobs);
    }


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
            const item = document.createElement('div');
            item.className = 'list-item';
            
            // Clickable Text
            const span = document.createElement('span');
            span.innerText = s.state_name;
            span.style.cursor = 'pointer';
            span.style.flex = '1';
            span.addEventListener('click', () => {
                document.querySelectorAll('#state-list .list-item').forEach(el => el.classList.remove('active-selection'));
                item.classList.add('active-selection');
                loadCounties(s.state_abbr, s.state_name);
            });

            // Toggle
            const label = document.createElement('label');
            label.className = 'admin-switch';
            
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = (s.is_enabled == 1);
            input.addEventListener('change', () => toggleState(s.id));
            
            const slider = document.createElement('span');
            slider.className = 'admin-slider';
            
            label.appendChild(input);
            label.appendChild(slider);
            
            item.appendChild(span);
            item.appendChild(label);
            list.appendChild(item);
        });
    }
    document.getElementById('state-search-input').addEventListener('input', renderStates);

    function toggleState(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states/'+id+'/toggle'), { method:'POST' });
    }

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
            const item = document.createElement('div');
            item.className = 'list-item';
            
            const span = document.createElement('span');
            span.innerText = c.county_name;
            
            const label = document.createElement('label');
            label.className = 'admin-switch';
            
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = (c.is_enabled == 1);
            input.addEventListener('change', () => toggleCounty(c.id));
            
            const slider = document.createElement('span');
            slider.className = 'admin-slider';
            
            label.appendChild(input);
            label.appendChild(slider);
            
            item.appendChild(span);
            item.appendChild(label);
            list.appendChild(item);
        });
    }
    document.getElementById('county-search-input').addEventListener('input', renderCounties);

    function toggleCounty(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/'+id+'/toggle'), { method:'POST' });
    }

});