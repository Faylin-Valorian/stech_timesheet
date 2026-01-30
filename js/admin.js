document.addEventListener('DOMContentLoaded', function() {
    
    // --- GLOBAL DATA STORES ---
    let allUsers = [];
    let allJobs = [];
    let allStates = [];
    let currentCounties = [];
    
    // --- EDIT MODE STATE ---
    let editMode = false;
    const cards = ['users', 'holidays', 'jobs', 'locations'];
    const descriptionCache = {};


    // =========================================================
    // 1. EDIT MODE & INITIALIZATION
    // =========================================================
    
    // Load saved settings (Descriptions)
    fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/settings'))
        .then(r => r.json())
        .then(settings => {
            cards.forEach(cardId => {
                const descKey = `desc_${cardId}`;
                const p = document.getElementById(`desc-${cardId}`);
                if (settings[descKey]) {
                    p.innerText = settings[descKey];
                }
                descriptionCache[cardId] = p.innerText; // Cache initial value
            });
        })
        .catch(console.error);

    // Toggle Edit Mode
    document.getElementById('admin-edit-mode').addEventListener('change', function() {
        editMode = this.checked;
        if (editMode) {
            document.body.classList.add('admin-edit-mode-active');
            enableEditUi();
        } else {
            document.body.classList.remove('admin-edit-mode-active');
            saveAndDisableEditUi();
        }
    });

    function enableEditUi() {
        cards.forEach(cardId => {
            // Swap <p> for <textarea>
            const p = document.getElementById(`desc-${cardId}`);
            const textarea = document.createElement('textarea');
            textarea.id = `desc-textarea-${cardId}`;
            textarea.className = 'editable-desc-textarea';
            textarea.value = p.innerText;
            p.replaceWith(textarea);
        });
    }

    function saveAndDisableEditUi() {
        const savePromises = [];

        cards.forEach(cardId => {
            // 1. Swap <textarea> back to <p>
            const textarea = document.getElementById(`desc-textarea-${cardId}`);
            const newText = textarea.value.trim();
            const p = document.createElement('p');
            p.id = `desc-${cardId}`;
            p.innerText = newText;
            textarea.replaceWith(p);

            // 2. Save Text if changed
            if (newText !== descriptionCache[cardId]) {
                descriptionCache[cardId] = newText;
                savePromises.push(
                    fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/settings'), {
                        method: 'POST',
                        headers: {'requesttoken': OC.requestToken, 'Content-Type': 'application/json'},
                        body: JSON.stringify({ key: `desc_${cardId}`, value: newText })
                    })
                );
            }

            // 3. Upload Image if selected
            const fileInput = document.getElementById(`file-upload-${cardId}`);
            if (fileInput.files.length > 0) {
                const formData = new FormData();
                formData.append('image', fileInput.files[0]);
                
                savePromises.push(
                    fetch(OC.generateUrl(`/apps/stech_timesheet/api/admin/thumbnail/${cardId}`), {
                        method: 'POST',
                        headers: {'requesttoken': OC.requestToken}, // Content-Type auto-set by FormData
                        body: formData
                    }).then(r => {
                        if(r.ok) {
                            // Force browser to reload image by appending timestamp
                            const img = document.getElementById(`thumb-img-${cardId}`);
                            const srcBase = img.src.split('?')[0];
                            img.src = srcBase + '?t=' + new Date().getTime();
                        }
                    })
                );
                fileInput.value = ''; // Reset input
            }
        });

        if(savePromises.length > 0) {
            Promise.all(savePromises)
                .then(() => OC.dialogs.toast('Changes saved successfully.', {type: 'success'}))
                .catch(() => OC.dialogs.toast('Error saving changes.', {type: 'error'}));
        }
    }


    // =========================================================
    // 2. MODAL CONTROLS
    // =========================================================
    function openModal(id) {
        document.getElementById(id).style.display = 'flex';
    }
    
    function closeModal(modal) {
        modal.style.display = 'none';
    }

    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.closest('.modal-overlay'));
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this);
        });
    });


    // =========================================================
    // 3. DASHBOARD CLICKS
    // =========================================================
    function attachCardClick(cardId, modalId, loadFn) {
        document.getElementById(cardId).addEventListener('click', (e) => {
            // Prevent opening modal if clicking upload button or in edit mode
            if (editMode || e.target.closest('.thumbnail-edit-overlay')) return;
            
            openModal(modalId);
            loadFn();
        });
    }

    attachCardClick('card-users', 'modal-users', loadUsers);
    attachCardClick('card-holidays', 'modal-holidays', loadHolidays);
    attachCardClick('card-jobs', 'modal-jobs', loadJobs);
    attachCardClick('card-locations', 'modal-locations', loadStates);


    // =========================================================
    // 4. USER MANAGEMENT (Searchable)
    // =========================================================
    function loadUsers() {
        const input = document.getElementById('user-search');
        input.value = ''; input.focus();
        
        const list = document.getElementById('user-dropdown-list');
        list.innerHTML = '<div class="dropdown-item" style="cursor:default">Loading users...</div>';
        list.classList.remove('hidden');

        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users'))
            .then(r => r.json())
            .then(users => {
                allUsers = users;
                renderUserList(allUsers);
            });
    }

    function renderUserList(users) {
        const list = document.getElementById('user-dropdown-list');
        list.innerHTML = '';
        
        if (users.length === 0) {
            list.innerHTML = '<div class="dropdown-item" style="cursor:default;opacity:0.6">No users found</div>';
            return;
        }

        users.forEach(u => {
            const div = document.createElement('div');
            div.className = 'dropdown-item';
            div.innerText = u.displayname;
            div.addEventListener('click', () => {
                document.getElementById('user-search').value = u.displayname;
                document.getElementById('selected-user-uid').value = u.uid;
                list.classList.add('hidden');
                document.getElementById('btn-view-user').disabled = false;
            });
            list.appendChild(div);
        });
    }

    document.getElementById('user-search').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        renderUserList(allUsers.filter(u => u.displayname.toLowerCase().includes(term)));
        document.getElementById('user-dropdown-list').classList.remove('hidden');
    });
    
    // Close dropdown if clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.searchable-select-wrapper')) {
            document.getElementById('user-dropdown-list').classList.add('hidden');
        }
    });

    document.getElementById('btn-view-user').addEventListener('click', () => {
        const uid = document.getElementById('selected-user-uid').value;
        if(uid) window.location.href = OC.generateUrl('/apps/stech_timesheet/') + '?target_user=' + uid;
    });


    // =========================================================
    // 5. HOLIDAYS
    // =========================================================
    function loadHolidays() {
        const list = document.getElementById('holiday-list');
        list.innerHTML = '<div style="padding:20px;">Loading...</div>';

        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'))
            .then(r => r.json())
            .then(data => {
                list.innerHTML = '';
                if(data.length === 0) {
                    list.innerHTML = '<div style="padding:20px;text-align:center;opacity:0.6">No holidays found.</div>';
                    return;
                }
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
        const payload = {
            name: document.getElementById('holiday-name').value,
            start: document.getElementById('holiday-start').value,
            end: document.getElementById('holiday-end').value
        };
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'), {
            method: 'POST', headers: {'requesttoken': OC.requestToken, 'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(() => {
            document.getElementById('form-holiday').reset();
            loadHolidays();
        });
    });

    window.deleteHoliday = function(id) {
        if(confirm('Delete holiday?')) {
            fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays/'+id), {
                method:'DELETE', headers:{'requesttoken':OC.requestToken}
            }).then(loadHolidays);
        }
    };


    // =========================================================
    // 6. JOBS (Search & Filter)
    // =========================================================
    function loadJobs() {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/attributes'))
            .then(r => r.json())
            .then(data => {
                allJobs = data.jobs || [];
                renderJobs();
            });
    }

    function renderJobs() {
        const searchTerm = document.getElementById('job-search-input').value.toLowerCase();
        const status = document.getElementById('job-filter-status').value;
        const list = document.getElementById('job-list');
        list.innerHTML = '';

        const filtered = allJobs.filter(j => {
            const isActive = j.job_archive == 0;
            if (status === 'active' && !isActive) return false;
            if (status === 'archived' && isActive) return false;
            return j.job_name.toLowerCase().includes(searchTerm);
        });

        if (filtered.length === 0) {
            list.innerHTML = '<div style="padding:20px;text-align:center;opacity:0.6">No jobs match filter.</div>';
            return;
        }

        filtered.forEach(j => {
            const isActive = j.job_archive == 0;
            list.innerHTML += `
                <div class="list-item" style="${!isActive?'opacity:0.6':''}">
                    <span>${j.job_name}</span>
                    <label class="admin-switch" title="Toggle Active/Archived">
                        <input type="checkbox" ${isActive?'checked':''} onchange="toggleJob(${j.job_id})">
                        <span class="admin-slider"></span>
                    </label>
                </div>`;
        });
    }

    document.getElementById('job-search-input').addEventListener('input', renderJobs);
    document.getElementById('job-filter-status').addEventListener('change', renderJobs);

    document.getElementById('form-job').addEventListener('submit', (e) => {
        e.preventDefault();
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs'), {
            method: 'POST', headers: {'requesttoken': OC.requestToken, 'Content-Type': 'application/json'},
            body: JSON.stringify({name: document.getElementById('job-name').value, description: document.getElementById('job-desc').value})
        }).then(() => {
            document.getElementById('form-job').reset();
            loadJobs();
        });
    });

    window.toggleJob = function(id) {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs/'+id+'/toggle'), {
            method:'POST', headers:{'requesttoken':OC.requestToken}
        }).then(loadJobs);
    };


    // =========================================================
    // 7. LOCATIONS (States & Counties Filters)
    // =========================================================
    function loadStates() {
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
            const div = document.createElement('div');
            div.className = 'list-item';
            div.innerHTML = `
                <span style="cursor:pointer; flex-grow:1;">${s.state_name}</span>
                <label class="admin-switch">
                    <input type="checkbox" ${s.is_enabled == 1 ? 'checked' : ''}>
                    <span class="admin-slider"></span>
                </label>`;
            
            // Text click loads counties
            div.querySelector('span').addEventListener('click', () => {
                // Visual Selection
                document.querySelectorAll('#state-list .list-item').forEach(el => el.classList.remove('active-selection'));
                div.classList.add('active-selection');
                loadCounties(s.state_abbr, s.state_name);
            });

            // Toggle Switch
            div.querySelector('input').addEventListener('change', () => toggleState(s.id));
            list.appendChild(div);
        });
    }

    document.getElementById('state-search-input').addEventListener('input', renderStates);

    function loadCounties(abbr, name) {
        document.getElementById('county-header').innerText = 'Counties: ' + name;
        const input = document.getElementById('county-search-input');
        input.disabled = false; input.value = ''; input.focus();
        
        document.getElementById('county-list').innerHTML = '<div style="padding:20px;">Loading...</div>';

        fetch(OC.generateUrl('/apps/stech_timesheet/api/counties/'+abbr))
            .then(r => r.json())
            .then(c => {
                currentCounties = c;
                renderCounties();
            });
    }

    function renderCounties() {
        const term = document.getElementById('county-search-input').value.toLowerCase();
        const list = document.getElementById('county-list');
        list.innerHTML = '';

        const filtered = currentCounties.filter(c => c.county_name.toLowerCase().includes(term));

        if(filtered.length === 0) {
            list.innerHTML = '<div style="padding:20px;text-align:center;opacity:0.6">No counties found</div>';
            return;
        }

        filtered.forEach(c => {
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

    window.toggleState = function(id) { fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states/'+id+'/toggle'), { method:'POST', headers:{'requesttoken':OC.requestToken} }); };
    window.toggleCounty = function(id) { fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/'+id+'/toggle'), { method:'POST', headers:{'requesttoken':OC.requestToken} }); };

});