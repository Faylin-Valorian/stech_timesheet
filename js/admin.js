document.addEventListener('DOMContentLoaded', function() {
    
    let allUsers = [];
    let allJobs = [];
    let allStates = [];
    let currentCounties = [];
    let allHolidays = [];
    let selectedStateAbbr = null;
    let selectedStateName = null;

    function apiFetch(url, options = {}) {
        if (!options.headers) options.headers = {};
        options.headers['requesttoken'] = OC.requestToken;
        return fetch(url, options);
    }

    function switchView(viewId) {
        document.querySelectorAll('.admin-view').forEach(el => el.classList.add('hidden'));
        document.getElementById('view-' + viewId).classList.remove('hidden');
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        document.getElementById('nav-' + viewId).classList.add('active');

        if(viewId === 'users') loadUsers();
        if(viewId === 'holidays') loadHolidays();
        if(viewId === 'jobs') loadJobs();
        if(viewId === 'locations') loadStates();
    }

    document.getElementById('nav-users').addEventListener('click', () => switchView('users'));
    document.getElementById('nav-holidays').addEventListener('click', () => switchView('holidays'));
    document.getElementById('nav-jobs').addEventListener('click', () => switchView('jobs'));
    document.getElementById('nav-locations').addEventListener('click', () => switchView('locations'));

    // Init Logic
    loadUsers();

    // --- FILTERS ---
    function setupFilter(btnId, menuId, inputName, renderFn) {
        const btn = document.getElementById(btnId);
        const menu = document.getElementById(menuId);
        if(!btn || !menu) return;
        btn.addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('hidden'); });
        menu.querySelectorAll(`input[name="${inputName}"]`).forEach(radio => {
            radio.addEventListener('change', () => { renderFn(); menu.classList.add('hidden'); });
        });
        document.addEventListener('click', (e) => { if(!menu.contains(e.target) && e.target !== btn) menu.classList.add('hidden'); });
    }
    // Setup for all views
    setupFilter('user-filter-btn', 'user-filter-menu', 'user-status', renderUsers);
    setupFilter('job-filter-btn', 'job-filter-menu', 'job-status', renderJobs);
    setupFilter('state-filter-btn', 'state-filter-menu', 'state-status', renderStates);
    setupFilter('county-filter-btn', 'county-filter-menu', 'county-status', renderCounties);
    setupFilter('holiday-filter-btn', 'holiday-filter-menu', 'holiday-status', renderHolidays);

    // --- USERS (Redesigned) ---
    function loadUsers() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users'))
            .then(r => r.json())
            .then(u => { 
                allUsers = u; 
                renderUsers(); 
            });
    }

    function renderUsers() {
        const term = (document.getElementById('user-search-input').value || '').toLowerCase();
        // Get the selected status from the filter menu
        const statusRadio = document.querySelector('input[name="user-status"]:checked');
        const status = statusRadio ? statusRadio.value : 'active';

        const container = document.getElementById('user-grid-container');
        container.innerHTML = '';

        const filtered = allUsers.filter(u => {
            // 1. Text Search Filter (Dynamic)
            const matchesName = (u.displayname || '').toLowerCase().includes(term);
            const matchesEmail = (u.email || '').toLowerCase().includes(term);
            if (!matchesName && !matchesEmail) return false;

            // 2. Status Filter
            const isActive = (u.is_active === 1);
            if (status === 'active' && !isActive) return false;
            if (status === 'inactive' && isActive) return false;
            
            return true;
        });

        if (filtered.length === 0) {
            container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:20px; opacity:0.6;">No employees found matching filter.</div>';
            return;
        }

        filtered.forEach(u => {
            const card = document.createElement('div');
            card.className = 'user-card';
            if (u.is_active === 0) card.classList.add('inactive');

            // Avatar Initials
            const initials = (u.displayname || '?').substring(0,2).toUpperCase();
            
            // Buttons HTML
            const buttonsHtml = `
                <button class="btn-icon-only" title="Edit Employee" onclick="alert('Edit functionality coming soon for ${u.displayname}')">
                    <span class="icon-rename"></span>
                </button>
                <button class="btn-icon-only btn-calendar" title="Open Timesheet" data-uid="${u.uid}">
                    <span class="icon-calendar-dark"></span>
                </button>
                <label class="admin-switch" title="Toggle Active/Inactive">
                    <input type="checkbox" class="user-toggle-input" data-uid="${u.uid}" ${u.is_active === 1 ? 'checked' : ''}>
                    <span class="admin-slider"></span>
                </label>
            `;

            card.innerHTML = `
                <div class="user-avatar-placeholder">${initials}</div>
                <div class="user-info">
                    <div class="user-name">${u.displayname}</div>
                    <div class="user-email">${u.email || ''}</div>
                </div>
                <div class="user-actions">${buttonsHtml}</div>
            `;

            container.appendChild(card);
        });

        // Attach Events
        document.querySelectorAll('.btn-calendar').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const uid = e.currentTarget.dataset.uid;
                window.location.href = OC.generateUrl('/apps/stech_timesheet/') + '?target_user=' + uid;
            });
        });

        document.querySelectorAll('.user-toggle-input').forEach(input => {
            input.addEventListener('change', (e) => {
                const uid = e.target.dataset.uid;
                toggleUserStatus(uid);
            });
        });
    }

    function toggleUserStatus(uid) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users/toggle'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({uid: uid})
        }).then(r => r.json()).then(res => {
            // Update local state without full reload to keep UI snappy
            const u = allUsers.find(user => user.uid === uid);
            if (u) u.is_active = res.new_state;
            renderUsers(); // Re-render to apply active/inactive styling immediately
        });
    }

    // Bind the input event for dynamic searching
    document.getElementById('user-search-input').addEventListener('input', renderUsers);

    // --- HOLIDAYS ---
    function loadHolidays() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'))
            .then(r => r.json())
            .then(data => { allHolidays = data; renderHolidays(); });
    }

    function renderHolidays() {
        const term = (document.getElementById('holiday-search-input').value || '').toLowerCase();
        const status = document.querySelector('input[name="holiday-status"]:checked').value;
        const list = document.getElementById('holiday-list');
        list.innerHTML = '';

        allHolidays.filter(h => {
            const active = (h.holiday_archive == 0 || h.holiday_archive == null);
            if(status === 'active' && !active) return false;
            if(status === 'archived' && active) return false;
            return (h.holiday_name || '').toLowerCase().includes(term);
        }).forEach(h => {
            const active = (h.holiday_archive == 0 || h.holiday_archive == null);
            const item = document.createElement('div');
            item.className = 'list-item';
            if(!active) item.style.opacity = '0.6';

            const info = document.createElement('span');
            info.style.flex = '1';
            info.style.cursor = 'pointer';
            info.innerHTML = `<strong>${h.holiday_name}</strong><br><span style="font-size:11px">${h.holiday_start_date}</span>`;
            info.addEventListener('click', () => editHoliday(h));

            const label = document.createElement('label');
            label.className = 'admin-switch';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = active;
            input.addEventListener('change', () => toggleHoliday(h.holiday_id));
            const slider = document.createElement('span'); slider.className = 'admin-slider';
            label.appendChild(input); label.appendChild(slider);
            item.appendChild(info); item.appendChild(label);
            list.appendChild(item);
        });
    }
    document.getElementById('holiday-search-input').addEventListener('input', renderHolidays);

    function editHoliday(h) {
        document.getElementById('holiday-id').value = h.holiday_id;
        document.getElementById('holiday-name').value = h.holiday_name;
        document.getElementById('holiday-start').value = h.holiday_start_date;
        document.getElementById('holiday-end').value = h.holiday_end_date;
        document.getElementById('btn-save-holiday').innerText = "Update Holiday";
        document.getElementById('holiday-form-title').innerText = "Edit Holiday";
        document.getElementById('btn-cancel-holiday').classList.remove('hidden');
    }

    function resetHolidayForm() {
        document.getElementById('form-holiday').reset();
        document.getElementById('holiday-id').value = '';
        document.getElementById('btn-save-holiday').innerText = "Add Holiday";
        document.getElementById('holiday-form-title').innerText = "Add Holiday";
        document.getElementById('btn-cancel-holiday').classList.add('hidden');
    }
    document.getElementById('btn-cancel-holiday').addEventListener('click', resetHolidayForm);

    document.getElementById('form-holiday').addEventListener('submit', (e) => {
        e.preventDefault();
        const payload = {
            id: document.getElementById('holiday-id').value,
            name: document.getElementById('holiday-name').value,
            start: document.getElementById('holiday-start').value,
            end: document.getElementById('holiday-end').value
        };
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'), {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(() => { resetHolidayForm(); loadHolidays(); });
    });

    function toggleHoliday(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays/'+id+'/toggle'), { method:'POST' })
        .then(loadHolidays);
    }

    // --- JOBS ---
    function loadJobs() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs'))
            .then(r => r.json()).then(d => { allJobs = d || []; renderJobs(); });
    }
    function renderJobs() {
        const term = (document.getElementById('job-search-input').value || '').toLowerCase();
        const status = document.querySelector('input[name="job-status"]:checked').value;
        const list = document.getElementById('job-list');
        list.innerHTML = '';

        allJobs.filter(j => {
            const active = j.job_archive == 0;
            if(status === 'active' && !active) return false;
            if(status === 'archived' && active) return false;
            return (j.job_name || '').toLowerCase().includes(term);
        }).forEach(j => {
            const active = j.job_archive == 0;
            const item = document.createElement('div');
            item.className = 'list-item';
            if(!active) item.style.opacity = '0.6';

            const span = document.createElement('span');
            span.innerText = j.job_name;
            span.style.flex = '1';
            span.style.cursor = 'pointer';
            span.addEventListener('click', () => editJob(j));

            const label = document.createElement('label');
            label.className = 'admin-switch';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = active;
            input.addEventListener('change', () => toggleJob(j.job_id));
            const slider = document.createElement('span'); slider.className = 'admin-slider';
            label.appendChild(input); label.appendChild(slider);
            item.appendChild(span); item.appendChild(label);
            list.appendChild(item);
        });
    }
    document.getElementById('job-search-input').addEventListener('input', renderJobs);

    function editJob(j) {
        document.getElementById('job-id').value = j.job_id;
        document.getElementById('job-name').value = j.job_name;
        document.getElementById('job-desc').value = j.job_description;
        document.getElementById('btn-save-job').innerText = "Update Job";
        document.getElementById('job-form-title').innerText = "Edit Job";
        document.getElementById('btn-cancel-job').classList.remove('hidden');
    }

    function resetJobForm() {
        document.getElementById('form-job').reset();
        document.getElementById('job-id').value = '';
        document.getElementById('btn-save-job').innerText = "Create Job";
        document.getElementById('job-form-title').innerText = "Create Job";
        document.getElementById('btn-cancel-job').classList.add('hidden');
    }
    document.getElementById('btn-cancel-job').addEventListener('click', resetJobForm);

    document.getElementById('form-job').addEventListener('submit', (e) => {
        e.preventDefault();
        const payload = {
            id: document.getElementById('job-id').value,
            name: document.getElementById('job-name').value,
            description: document.getElementById('job-desc').value
        };
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs'), {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(() => { resetJobForm(); loadJobs(); });
    });

    function toggleJob(id) { apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs/'+id+'/toggle'), { method:'POST' }).then(loadJobs); }

    // --- LOCATIONS ---
    function loadStates() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states'))
            .then(r => r.json()).then(d => { allStates = d || []; renderStates(); });
    }
    function renderStates() {
        const term = (document.getElementById('state-search-input').value || '').toLowerCase();
        const status = document.querySelector('input[name="state-status"]:checked').value;
        const list = document.getElementById('state-list');
        list.innerHTML = '';

        allStates.filter(s => {
            const en = s.is_enabled == 1;
            if(status === 'enabled' && !en) return false;
            if(status === 'disabled' && en) return false;
            return (s.state_name || '').toLowerCase().includes(term);
        }).forEach(s => {
            const item = document.createElement('div'); item.className = 'list-item';
            const span = document.createElement('span'); span.innerText = s.state_name; span.style.cursor='pointer'; span.style.flex='1';
            if(s.state_abbr === selectedStateAbbr) item.classList.add('active-selection');
            span.addEventListener('click', () => {
                document.querySelectorAll('#state-list .list-item').forEach(el => el.classList.remove('active-selection'));
                item.classList.add('active-selection');
                selectedStateAbbr = s.state_abbr; selectedStateName = s.state_name;
                loadCounties(s.state_abbr, s.state_name);
            });
            const label = document.createElement('label'); label.className = 'admin-switch';
            const input = document.createElement('input'); input.type = 'checkbox'; input.checked = (s.is_enabled == 1);
            input.addEventListener('change', () => toggleState(s.id));
            const slider = document.createElement('span'); slider.className = 'admin-slider';
            label.appendChild(input); label.appendChild(slider);
            item.appendChild(span); item.appendChild(label);
            list.appendChild(item);
        });
    }
    document.getElementById('state-search-input').addEventListener('input', renderStates);
    function toggleState(id) { apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states/'+id+'/toggle'), { method:'POST' }).then(loadStates); }

    function loadCounties(abbr, name) {
        document.getElementById('county-header').innerText = 'Counties: ' + name;
        document.getElementById('county-search-input').disabled = false;
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/'+abbr))
            .then(r => r.json()).then(c => { currentCounties = c; renderCounties(); });
    }
    function renderCounties() {
        const term = (document.getElementById('county-search-input').value || '').toLowerCase();
        const status = document.querySelector('input[name="county-status"]:checked').value;
        const list = document.getElementById('county-list');
        list.innerHTML = '';

        currentCounties.filter(c => {
            const en = c.is_enabled == 1;
            if(status === 'enabled' && !en) return false;
            if(status === 'disabled' && en) return false;
            return (c.county_name || '').toLowerCase().includes(term);
        }).forEach(c => {
            const item = document.createElement('div'); item.className = 'list-item';
            const span = document.createElement('span'); span.innerText = c.county_name;
            const label = document.createElement('label'); label.className = 'admin-switch';
            const input = document.createElement('input'); input.type = 'checkbox'; input.checked = (c.is_enabled == 1);
            input.addEventListener('change', () => toggleCounty(c.id));
            const slider = document.createElement('span'); slider.className = 'admin-slider';
            label.appendChild(input); label.appendChild(slider);
            item.appendChild(span); item.appendChild(label);
            list.appendChild(item);
        });
    }
    document.getElementById('county-search-input').addEventListener('input', renderCounties);
    function toggleCounty(id) { apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/'+id+'/toggle'), { method:'POST' }).then(() => { if(selectedStateAbbr) loadCounties(selectedStateAbbr, selectedStateName); }); }
});