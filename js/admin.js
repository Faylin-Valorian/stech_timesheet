document.addEventListener('DOMContentLoaded', function() {
    
    // --- Data Stores ---
    let allUsers = [];
    let allJobs = [];
    let allStates = [];
    let currentCounties = [];
    let allHolidays = [];
    let systemGroups = []; 
    let accessRules = {};  
    let selectedStateAbbr = null;
    let selectedStateName = null;

    // --- Helper: API Wrapper ---
    function apiFetch(url, options = {}) {
        if (!options.headers) options.headers = {};
        options.headers['requesttoken'] = OC.requestToken;
        return fetch(url, options);
    }

    // =========================================================
    //  1. NAVIGATION & VIEW SWITCHING
    // =========================================================
    function switchView(viewId) {
        document.querySelectorAll('.admin-view').forEach(el => el.classList.add('hidden'));
        document.getElementById('view-' + viewId).classList.remove('hidden');
        
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        document.getElementById('nav-' + viewId).classList.add('active');

        if(viewId === 'users') loadUsers();
        if(viewId === 'access') loadAccessControl();
        if(viewId === 'payroll') loadPayroll();
        if(viewId === 'holidays') loadHolidays();
        if(viewId === 'jobs') loadJobs();
        if(viewId === 'locations') loadStates();
    }

    document.getElementById('nav-users').addEventListener('click', () => switchView('users'));
    document.getElementById('nav-access').addEventListener('click', () => switchView('access'));
    document.getElementById('nav-payroll').addEventListener('click', () => switchView('payroll'));
    document.getElementById('nav-holidays').addEventListener('click', () => switchView('holidays'));
    document.getElementById('nav-jobs').addEventListener('click', () => switchView('jobs'));
    document.getElementById('nav-locations').addEventListener('click', () => switchView('locations'));

    // Initial Load
    loadUsers();

    // =========================================================
    //  2. GENERIC FILTER LOGIC
    // =========================================================
    function setupFilter(btnId, menuId, inputName, renderFn) {
        const btn = document.getElementById(btnId);
        const menu = document.getElementById(menuId);
        if(!btn || !menu) return;
        
        btn.addEventListener('click', (e) => { 
            e.stopPropagation(); 
            menu.classList.toggle('hidden'); 
        });
        menu.querySelectorAll(`input[name="${inputName}"]`).forEach(radio => {
            radio.addEventListener('change', () => { 
                renderFn(); 
                menu.classList.add('hidden'); 
            });
        });
        document.addEventListener('click', (e) => { 
            if(!menu.contains(e.target) && e.target !== btn) {
                menu.classList.add('hidden'); 
            }
        });
    }
    setupFilter('user-filter-btn', 'user-filter-menu', 'user-status', renderUsers);
    setupFilter('job-filter-btn', 'job-filter-menu', 'job-status', renderJobs);
    setupFilter('state-filter-btn', 'state-filter-menu', 'state-status', renderStates);
    setupFilter('county-filter-btn', 'county-filter-menu', 'county-status', renderCounties);
    setupFilter('holiday-filter-btn', 'holiday-filter-menu', 'holiday-status', renderHolidays);

    // =========================================================
    //  3. ACCESS CONTROL (UPDATED)
    // =========================================================
    
    document.querySelectorAll('.access-tab').forEach(tab => {
        tab.addEventListener('click', (e) => {
            document.querySelectorAll('.access-tab').forEach(t => t.classList.remove('active'));
            e.target.classList.add('active');
            
            const targetId = e.target.dataset.target;
            document.querySelectorAll('.access-group-panel').forEach(p => p.classList.add('hidden'));
            document.getElementById(targetId).classList.remove('hidden');
        });
    });

    function loadAccessControl() {
        const p1 = apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/groups')).then(r => r.json());
        const p2 = apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/access')).then(r => r.json());

        Promise.all([p1, p2]).then(([groups, rules]) => {
            systemGroups = groups || [];
            accessRules = rules || {};
            
            // Render All Toggles
            renderAccessToggles('list-admin_panel-groups', 'admin_panel');
            renderAccessToggles('list-analysis_tab-groups', 'analysis_tab');
            renderAccessToggles('list-analysis_view_others-groups', 'analysis_view_others');
            
            // New Features
            renderAccessToggles('list-analysis_travel-groups', 'analysis_travel');
            renderAccessToggles('list-analysis_financial-groups', 'analysis_financial');
            renderAccessToggles('list-analysis_location-groups', 'analysis_location');
        });
    }

    function renderAccessToggles(containerId, ruleKey) {
        const container = document.getElementById(containerId);
        if(!container) return;
        container.innerHTML = '';
        
        const allowed = accessRules[ruleKey] || [];
        systemGroups.sort((a, b) => {
            if(a.toLowerCase() === 'admin') return -1;
            if(b.toLowerCase() === 'admin') return 1;
            return a.localeCompare(b);
        });

        systemGroups.forEach(group => {
            const isAllowed = allowed.includes(group);
            const isAdmin = (group.toLowerCase() === 'admin');
            const locked = isAdmin; 
            const checked = (isAllowed || locked); 

            const row = document.createElement('div');
            row.className = 'list-item'; 
            
            const nameSpan = document.createElement('span');
            nameSpan.style.flex = '1';
            nameSpan.style.fontWeight = 'bold';
            nameSpan.innerText = group;
            if(locked) nameSpan.innerHTML += ' <span style="opacity:0.5; font-weight:normal; font-size:0.85em;">(Owner)</span>';

            const label = document.createElement('label');
            label.className = 'admin-switch';
            
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.value = group;
            input.checked = checked;
            input.disabled = locked; 
            
            input.addEventListener('change', () => saveAccessRule(ruleKey, containerId));

            const slider = document.createElement('span');
            slider.className = 'admin-slider';
            
            label.appendChild(input);
            label.appendChild(slider);
            row.appendChild(nameSpan);
            row.appendChild(label);
            container.appendChild(row);
        });
    }

    function saveAccessRule(ruleKey, containerId) {
        const container = document.getElementById(containerId);
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        const selectedGroups = [];

        checkboxes.forEach(cb => {
            if (cb.checked) selectedGroups.push(cb.value);
        });

        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/access'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                rule_key: ruleKey,
                allowed_groups: selectedGroups
            })
        });
    }

    // =========================================================
    //  4. PAYROLL SETTINGS
    // =========================================================
    function loadPayroll() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/settings'))
            .then(r => r.json())
            .then(settings => {
                document.getElementById('pay-frequency').value = settings['pay_frequency'] || 14;
                document.getElementById('pay-start-date').value = settings['pay_start_date'] || '2026-01-07';
                document.getElementById('pay-bg-style').value = settings['pay_bg_style'] || '';
            });
    }

    document.getElementById('btn-save-payroll').addEventListener('click', () => {
        const freq = document.getElementById('pay-frequency').value;
        const start = document.getElementById('pay-start-date').value;
        const bg = document.getElementById('pay-bg-style').value;
        
        const p1 = apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/settings'), { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({key:'pay_frequency', value:freq}) });
        const p2 = apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/settings'), { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({key:'pay_start_date', value:start}) });
        const p3 = apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/settings'), { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({key:'pay_bg_style', value:bg}) });

        Promise.all([p1, p2, p3]).then(() => {
            const msg = document.getElementById('payroll-msg');
            msg.style.display = 'inline';
            setTimeout(() => msg.style.display = 'none', 3000);
        });
    });

    // =========================================================
    //  5. USER MANAGEMENT
    // =========================================================
    function loadUsers() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users'))
            .then(r => r.json()).then(u => { allUsers = u; renderUsers(); });
    }

    function renderUsers() {
        const term = (document.getElementById('user-search-input').value || '').toLowerCase();
        const status = document.querySelector('input[name="user-status"]:checked') ? document.querySelector('input[name="user-status"]:checked').value : 'active';
        const container = document.getElementById('user-grid-container');
        container.innerHTML = '';

        const filtered = allUsers.filter(u => {
            const matchesName = (u.displayname || '').toLowerCase().includes(term);
            const matchesEmail = (u.email || '').toLowerCase().includes(term);
            if (!matchesName && !matchesEmail) return false;

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
            card.style.cursor = 'pointer'; 
            if (u.is_active === 0) card.classList.add('inactive');

            const initials = (u.displayname || '?').substring(0,2).toUpperCase();
            const buttonsHtml = `<label class="admin-switch" title="Toggle Active/Inactive"><input type="checkbox" class="user-toggle-input" data-uid="${u.uid}" ${u.is_active === 1 ? 'checked' : ''}><span class="admin-slider"></span></label>`;

            card.innerHTML = `<div class="user-avatar-placeholder">${initials}</div><div class="user-info"><div class="user-name">${u.displayname}</div><div class="user-email">${u.email || ''}</div></div><div class="user-actions">${buttonsHtml}</div>`;

            card.addEventListener('click', (e) => {
                if (e.target.closest('.admin-switch')) return;
                window.location.href = OC.generateUrl('/apps/stech_timesheet/') + '?target_user=' + u.uid;
            });
            container.appendChild(card);
        });

        document.querySelectorAll('.user-toggle-input').forEach(input => {
            input.addEventListener('change', (e) => {
                toggleUserStatus(e.target.dataset.uid);
            });
        });
    }

    function toggleUserStatus(uid) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users/toggle'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({uid: uid}) })
        .then(r => r.json()).then(res => {
            const u = allUsers.find(user => user.uid === uid);
            if (u) u.is_active = res.new_state;
            renderUsers(); 
        });
    }
    document.getElementById('user-search-input').addEventListener('input', renderUsers);

    // =========================================================
    //  6. HOLIDAY MANAGEMENT
    // =========================================================
    function loadHolidays() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'))
            .then(r => r.json()).then(data => { allHolidays = data; renderHolidays(); });
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

            const label = document.createElement('label'); label.className = 'admin-switch';
            const input = document.createElement('input'); input.type = 'checkbox'; input.checked = active;
            input.addEventListener('change', () => toggleHoliday(h.holiday_id));
            const slider = document.createElement('span'); slider.className = 'admin-slider';
            
            label.appendChild(input); label.appendChild(slider); item.appendChild(info); item.appendChild(label); list.appendChild(item);
        });
    }
    document.getElementById('holiday-search-input').addEventListener('input', renderHolidays);

    function editHoliday(h) {
        document.getElementById('holiday-id').value = h.holiday_id;
        document.getElementById('holiday-name').value = h.holiday_name;
        document.getElementById('holiday-start').value = h.holiday_start_date;
        document.getElementById('holiday-end').value = h.holiday_end_date;
        document.getElementById('holiday-bg').value = h.holiday_bg || ''; 
        
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
            end: document.getElementById('holiday-end').value,
            bg_style: document.getElementById('holiday-bg').value 
        };
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) })
        .then(() => { resetHolidayForm(); loadHolidays(); });
    });

    function toggleHoliday(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays/'+id+'/toggle'), { method:'POST' })
        .then(loadHolidays);
    }

    // =========================================================
    //  7. JOB CODES
    // =========================================================
    function loadJobs() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs')).then(r => r.json()).then(d => { allJobs = d || []; renderJobs(); });
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
            const item = document.createElement('div'); item.className = 'list-item';
            if(!active) item.style.opacity = '0.6';

            const span = document.createElement('span');
            const ptoTag = (j.is_pto == 1) ? ' <span style="font-size:0.7em; background:#9b59b6; color:white; padding:1px 4px; border-radius:3px;">PTO</span>' : '';
            span.innerHTML = j.job_name + ptoTag;
            span.style.flex = '1'; span.style.cursor = 'pointer';
            span.addEventListener('click', () => editJob(j));

            const label = document.createElement('label'); label.className = 'admin-switch';
            const input = document.createElement('input'); input.type = 'checkbox'; input.checked = active;
            input.addEventListener('change', () => toggleJob(j.job_id));
            const slider = document.createElement('span'); slider.className = 'admin-slider';
            label.appendChild(input); label.appendChild(slider); item.appendChild(span); item.appendChild(label); list.appendChild(item);
        });
    }
    document.getElementById('job-search-input').addEventListener('input', renderJobs);

    function editJob(j) {
        document.getElementById('job-id').value = j.job_id;
        document.getElementById('job-name').value = j.job_name;
        document.getElementById('job-desc').value = j.job_description;
        document.getElementById('job-is-pto').checked = (j.is_pto == 1);
        document.getElementById('btn-save-job').innerText = "Update Job";
        document.getElementById('job-form-title').innerText = "Edit Job";
        document.getElementById('btn-cancel-job').classList.remove('hidden');
    }

    function resetJobForm() {
        document.getElementById('form-job').reset();
        document.getElementById('job-id').value = '';
        document.getElementById('job-is-pto').checked = false;
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
            description: document.getElementById('job-desc').value,
            is_pto: document.getElementById('job-is-pto').checked ? 1 : 0
        };
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) })
        .then(() => { resetJobForm(); loadJobs(); });
    });

    function toggleJob(id) { 
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs/'+id+'/toggle'), { method:'POST' }).then(loadJobs); 
    }

    // =========================================================
    //  8. LOCATION MANAGEMENT
    // =========================================================
    function loadStates() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states')).then(r => r.json()).then(d => { allStates = d || []; renderStates(); });
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
                selectedStateAbbr = s.state_abbr; 
                selectedStateName = s.state_name;
                loadCounties(s.state_abbr, s.state_name);
            });

            const label = document.createElement('label'); label.className = 'admin-switch';
            const input = document.createElement('input'); input.type = 'checkbox'; input.checked = (s.is_enabled == 1);
            input.addEventListener('change', () => toggleState(s.id));
            const slider = document.createElement('span'); slider.className = 'admin-slider';
            label.appendChild(input); label.appendChild(slider); item.appendChild(span); item.appendChild(label); list.appendChild(item);
        });
    }
    document.getElementById('state-search-input').addEventListener('input', renderStates);
    
    function toggleState(id) { 
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states/'+id+'/toggle'), { method:'POST' })
        .then(() => { loadStates(); if(selectedStateAbbr) loadCounties(selectedStateAbbr, selectedStateName); }); 
    }

    function loadCounties(abbr, name) {
        document.getElementById('county-header').innerText = 'Counties: ' + name;
        document.getElementById('county-search-input').disabled = false;
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/'+abbr)).then(r => r.json()).then(c => { currentCounties = c; renderCounties(); });
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
            label.appendChild(input); label.appendChild(slider); item.appendChild(span); item.appendChild(label); list.appendChild(item);
        });
    }
    document.getElementById('county-search-input').addEventListener('input', renderCounties);
    
    function toggleCounty(id) { 
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/'+id+'/toggle'), { method:'POST' })
        .then(() => { if(selectedStateAbbr) loadCounties(selectedStateAbbr, selectedStateName); }); 
    }
});