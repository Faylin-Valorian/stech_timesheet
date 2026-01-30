document.addEventListener('DOMContentLoaded', function() {
    
    // --- GLOBAL STATE ---
    let allUsers = [];
    let allJobs = [];
    let allStates = [];
    let currentCounties = [];

    // =========================================================
    // 1. MODAL SYSTEM (Safe Open/Close)
    // =========================================================
    function openModal(id) {
        document.getElementById(id).style.display = 'flex';
    }

    function closeModal(modal) {
        modal.style.display = 'none';
    }
    
    // Wire up close buttons
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.closest('.modal-overlay'));
        });
    });

    // Wire up background click close
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this);
        });
    });

    // --- DASHBOARD CLICK LISTENERS ---
    document.getElementById('card-users').addEventListener('click', () => {
        openModal('modal-users');
        loadUsers(); // Fetch fresh data
    });

    document.getElementById('card-holidays').addEventListener('click', () => {
        openModal('modal-holidays');
        loadHolidays();
    });

    document.getElementById('card-jobs').addEventListener('click', () => {
        openModal('modal-jobs');
        loadJobs();
    });

    document.getElementById('card-locations').addEventListener('click', () => {
        openModal('modal-locations');
        loadStates();
    });


    // =========================================================
    // 2. USER MANAGEMENT (Searchable Dropdown)
    // =========================================================
    
    function loadUsers() {
        // Reset Interface
        const input = document.getElementById('user-search');
        input.value = '';
        input.focus();
        
        const list = document.getElementById('user-dropdown-list');
        list.innerHTML = '<div class="dropdown-item" style="cursor:default">Loading users...</div>';
        list.classList.remove('hidden');

        // Fetch
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users'))
            .then(response => response.json())
            .then(users => {
                allUsers = users;
                renderUserList(allUsers);
            })
            .catch(err => {
                console.error(err);
                list.innerHTML = '<div class="dropdown-item">Error loading users</div>';
            });
    }

    function renderUserList(users) {
        const list = document.getElementById('user-dropdown-list');
        list.innerHTML = '';

        if (users.length === 0) {
            list.innerHTML = '<div class="dropdown-item" style="cursor:default; opacity:0.6;">No users found</div>';
            return;
        }

        users.forEach(u => {
            const div = document.createElement('div');
            div.className = 'dropdown-item';
            div.innerText = u.displayname + (u.uid !== u.displayname ? ` (${u.uid})` : '');
            
            div.addEventListener('click', () => {
                selectUser(u);
            });
            
            list.appendChild(div);
        });
    }

    function selectUser(user) {
        document.getElementById('user-search').value = user.displayname;
        document.getElementById('selected-user-uid').value = user.uid;
        document.getElementById('user-dropdown-list').classList.add('hidden');
        document.getElementById('btn-view-user').disabled = false;
    }

    // Filter Logic
    const userSearchInput = document.getElementById('user-search');
    userSearchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        const filtered = allUsers.filter(u => 
            u.displayname.toLowerCase().includes(term) || 
            u.uid.toLowerCase().includes(term)
        );
        renderUserList(filtered);
        document.getElementById('user-dropdown-list').classList.remove('hidden');
    });

    userSearchInput.addEventListener('focus', () => {
        if(allUsers.length > 0) document.getElementById('user-dropdown-list').classList.remove('hidden');
    });

    // Close Dropdown on Outside Click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.searchable-select-wrapper')) {
            document.getElementById('user-dropdown-list').classList.add('hidden');
        }
    });

    // Open Calendar Action
    document.getElementById('btn-view-user').addEventListener('click', () => {
        const uid = document.getElementById('selected-user-uid').value;
        if (uid) {
            window.location.href = OC.generateUrl('/apps/stech_timesheet/') + '?target_user=' + uid;
        }
    });


    // =========================================================
    // 3. HOLIDAYS
    // =========================================================

    function loadHolidays() {
        const list = document.getElementById('holiday-list');
        list.innerHTML = '<div style="padding:10px;">Loading...</div>';

        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'))
            .then(r => r.json())
            .then(data => {
                list.innerHTML = '';
                if (data.length === 0) {
                    list.innerHTML = '<div style="padding:20px; text-align:center; opacity:0.6">No holidays found.</div>';
                    return;
                }

                data.forEach(h => {
                    const div = document.createElement('div');
                    div.className = 'list-item';
                    div.innerHTML = `
                        <div>
                            <strong>${h.holiday_name}</strong><br>
                            <span style="font-size:11px; opacity:0.6">${h.holiday_start_date} to ${h.holiday_end_date}</span>
                        </div>
                        <button class="icon-delete" title="Delete Holiday"></button>
                    `;
                    div.querySelector('.icon-delete').addEventListener('click', () => deleteHoliday(h.holiday_id));
                    list.appendChild(div);
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

    function deleteHoliday(id) {
        if (confirm('Are you sure you want to delete this holiday?')) {
            fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays/' + id), {
                method: 'DELETE', headers: { 'requesttoken': OC.requestToken }
            }).then(() => loadHolidays());
        }
    }


    // =========================================================
    // 4. JOBS (With Status Filter & Search)
    // =========================================================

    function loadJobs() {
        const list = document.getElementById('job-list');
        list.innerHTML = '<div style="padding:10px;">Loading...</div>';

        fetch(OC.generateUrl('/apps/stech_timesheet/api/attributes'))
            .then(r => r.json())
            .then(data => {
                allJobs = data.jobs || [];
                renderJobs();
            });
    }

    function renderJobs() {
        const searchTerm = document.getElementById('job-search-input').value.toLowerCase();
        const filterStatus = document.getElementById('job-filter-status').value;
        const list = document.getElementById('job-list');
        list.innerHTML = '';

        const filtered = allJobs.filter(j => {
            const isActive = (j.job_archive == 0);
            const matchesSearch = j.job_name.toLowerCase().includes(searchTerm);
            
            let matchesStatus = true;
            if (filterStatus === 'active') matchesStatus = isActive;
            if (filterStatus === 'archived') matchesStatus = !isActive;

            return matchesSearch && matchesStatus;
        });

        if (filtered.length === 0) {
            list.innerHTML = '<div style="padding:20px; text-align:center; opacity:0.6">No matching jobs.</div>';
            return;
        }

        filtered.forEach(j => {
            const isActive = (j.job_archive == 0);
            const div = document.createElement('div');
            div.className = 'list-item';
            if(!isActive) div.style.opacity = '0.6';
            
            div.innerHTML = `
                <span>${j.job_name}</span>
                <label class="admin-switch" title="${isActive ? 'Active' : 'Archived'}">
                    <input type="checkbox" ${isActive ? 'checked' : ''}>
                    <span class="admin-slider"></span>
                </label>
            `;

            // Toggle Handler
            div.querySelector('input').addEventListener('change', () => toggleJob(j.job_id));
            list.appendChild(div);
        });
    }

    document.getElementById('job-search-input').addEventListener('input', renderJobs);
    document.getElementById('job-filter-status').addEventListener('change', renderJobs);

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

    function toggleJob(id) {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs/' + id + '/toggle'), {
            method: 'POST', headers: { 'requesttoken': OC.requestToken }
        }).then(loadJobs); // Reload to reflect status changes
    }


    // =========================================================
    // 5. LOCATIONS (Split View Filter)
    // =========================================================

    function loadStates() {
        const list = document.getElementById('state-list');
        list.innerHTML = '<div style="padding:10px;">Loading...</div>';

        fetch(OC.generateUrl('/apps/stech_timesheet/api/attributes'))
            .then(r => r.json())
            .then(data => {
                allStates = data.states || [];
                renderStates();
            });
    }

    function renderStates() {
        const term = document.getElementById('state-search-input').value.toLowerCase();
        const list = document.getElementById('state-list');
        list.innerHTML = '';

        const filtered = allStates.filter(s => s.state_name.toLowerCase().includes(term));

        filtered.forEach(s => {
            const isEnabled = (s.is_enabled == 1);
            const div = document.createElement('div');
            div.className = 'list-item';
            div.innerHTML = `
                <span style="cursor:pointer; flex-grow:1;">${s.state_name}</span>
                <label class="admin-switch" title="Enable/Disable State">
                    <input type="checkbox" ${isEnabled ? 'checked' : ''}>
                    <span class="admin-slider"></span>
                </label>
            `;

            // Click Text -> Load Counties
            div.querySelector('span').addEventListener('click', () => {
                // Visual Highlight
                document.querySelectorAll('#state-list .list-item').forEach(el => el.style.backgroundColor = '');
                div.style.backgroundColor = 'var(--color-primary-light)';
                loadCounties(s.state_abbr, s.state_name);
            });

            // Click Toggle -> API
            div.querySelector('input').addEventListener('change', () => toggleState(s.id));

            list.appendChild(div);
        });
    }

    document.getElementById('state-search-input').addEventListener('input', renderStates);

    function toggleState(id) {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states/' + id + '/toggle'), {
            method: 'POST', headers: { 'requesttoken': OC.requestToken }
        });
    }

    // --- COUNTIES ---

    function loadCounties(abbr, name) {
        document.getElementById('county-header').innerText = 'Counties: ' + name;
        
        const searchInput = document.getElementById('county-search-input');
        searchInput.disabled = false;
        searchInput.value = '';
        searchInput.focus();

        const list = document.getElementById('county-list');
        list.innerHTML = '<div style="padding:20px; text-align:center;">Loading...</div>';

        fetch(OC.generateUrl('/apps/stech_timesheet/api/counties/' + abbr))
            .then(r => r.json())
            .then(counties => {
                currentCounties = counties;
                renderCounties();
            });
    }

    function renderCounties() {
        const term = document.getElementById('county-search-input').value.toLowerCase();
        const list = document.getElementById('county-list');
        list.innerHTML = '';

        const filtered = currentCounties.filter(c => c.county_name.toLowerCase().includes(term));

        if (filtered.length === 0) {
            list.innerHTML = '<div style="padding:20px; text-align:center; opacity:0.6">No counties found.</div>';
            return;
        }

        filtered.forEach(c => {
            const isEnabled = (c.is_enabled == 1);
            const div = document.createElement('div');
            div.className = 'list-item';
            div.innerHTML = `
                <span>${c.county_name}</span>
                <label class="admin-switch">
                    <input type="checkbox" ${isEnabled ? 'checked' : ''}>
                    <span class="admin-slider"></span>
                </label>
            `;

            div.querySelector('input').addEventListener('change', () => toggleCounty(c.id));
            list.appendChild(div);
        });
    }

    document.getElementById('county-search-input').addEventListener('input', renderCounties);

    function toggleCounty(id) {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/' + id + '/toggle'), {
            method: 'POST', headers: { 'requesttoken': OC.requestToken }
        });
    }

});