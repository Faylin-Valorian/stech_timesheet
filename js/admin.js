document.addEventListener('DOMContentLoaded', function() {
    
    // --- GLOBAL DATA STORES ---
    let allUsers = [];
    let allJobs = [];
    let allStates = [];
    let currentCounties = [];
    
    // --- STATE FLAGS ---
    let editMode = false;
    const cards = ['users', 'holidays', 'jobs', 'locations'];
    const descriptionCache = {};

    // =========================================================
    // 1. HELPER FUNCTIONS
    // =========================================================
    
    // Wrapper for Fetch to always include Nextcloud CSRF Token (Fixes 412)
    function apiFetch(url, options = {}) {
        if (!options.headers) {
            options.headers = {};
        }
        options.headers['requesttoken'] = OC.requestToken;
        // If sending JSON, ensure Content-Type is set (but not for FormData!)
        if (options.body && typeof options.body === 'string') {
            options.headers['Content-Type'] = 'application/json';
        }
        return fetch(url, options);
    }

    // Modal Control (Using inline styles to be robust against CSS conflicts)
    function openModal(id) {
        const modal = document.getElementById(id);
        if(modal) modal.style.display = 'flex';
    }

    function closeModal(modal) {
        if(modal) modal.style.display = 'none';
    }

    // =========================================================
    // 2. INITIALIZATION & IMAGE HANDLING
    // =========================================================
    
    // Image Error Handler (Fixes CSP "inline event handler" error)
    // Runs on load to catch images that failed before JS ran
    document.querySelectorAll('.card-thumbnail-img').forEach(img => {
        // If image is already broken (naturalWidth is 0 and it's complete)
        if (img.complete && img.naturalWidth === 0) {
            if (img.dataset.fallbackSrc) img.src = img.dataset.fallbackSrc;
        }
        
        // Listen for future errors
        img.addEventListener('error', function() {
            if (this.dataset.fallbackSrc) {
                this.src = this.dataset.fallbackSrc;
            }
        });
    });

    // Load Saved Settings (Descriptions)
    apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/settings'))
        .then(r => r.json())
        .then(settings => {
            cards.forEach(cardId => {
                const descKey = `desc_${cardId}`;
                const p = document.getElementById(`desc-${cardId}`);
                if (settings[descKey] && p) {
                    p.innerText = settings[descKey];
                }
                if (p) descriptionCache[cardId] = p.innerText;
            });
        })
        .catch(err => console.error('Failed to load settings:', err));

    // =========================================================
    // 3. EDIT MODE LOGIC
    // =========================================================
    const editToggle = document.getElementById('admin-edit-mode');
    if (editToggle) {
        editToggle.addEventListener('change', function() {
            editMode = this.checked;
            if (editMode) {
                document.body.classList.add('admin-edit-mode-active');
                enableEditUi();
            } else {
                document.body.classList.remove('admin-edit-mode-active');
                saveAndDisableEditUi();
            }
        });
    }

    function enableEditUi() {
        cards.forEach(cardId => {
            const p = document.getElementById(`desc-${cardId}`);
            if (!p) return;
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
            // 1. Save Description
            const textarea = document.getElementById(`desc-textarea-${cardId}`);
            if (textarea) {
                const newText = textarea.value.trim();
                const p = document.createElement('p');
                p.id = `desc-${cardId}`;
                p.className = 'editable-desc';
                p.innerText = newText;
                textarea.replaceWith(p);

                if (newText !== descriptionCache[cardId]) {
                    descriptionCache[cardId] = newText;
                    savePromises.push(
                        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/settings'), {
                            method: 'POST',
                            body: JSON.stringify({ key: `desc_${cardId}`, value: newText })
                        })
                    );
                }
            }

            // 2. Save Image
            const fileInput = document.getElementById(`file-upload-${cardId}`);
            if (fileInput && fileInput.files.length > 0) {
                const formData = new FormData();
                formData.append('image', fileInput.files[0]);
                
                savePromises.push(
                    apiFetch(OC.generateUrl(`/apps/stech_timesheet/api/admin/thumbnail/${cardId}`), {
                        method: 'POST',
                        body: formData // No Content-Type header; browser sets multipart/form-data
                    }).then(r => {
                        if (r.ok) {
                            // Force refresh image
                            const img = document.getElementById(`thumb-img-${cardId}`);
                            if(img) {
                                const srcBase = img.src.split('?')[0];
                                img.src = srcBase + '?t=' + new Date().getTime();
                            }
                        }
                    })
                );
                fileInput.value = ''; // Reset input
            }
        });

        if (savePromises.length > 0) {
            Promise.all(savePromises)
                .then(() => OC.dialogs.toast('Settings saved successfully.', { type: 'success' }))
                .catch(() => OC.dialogs.toast('Error saving settings.', { type: 'error' }));
        }
    }

    // =========================================================
    // 4. GENERAL UI HANDLERS (Modals, Filters)
    // =========================================================
    
    // Close Buttons
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.closest('.modal-overlay'));
        });
    });

    // Outside Click Close
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this);
        });
    });

    // Card Click Listeners
    function attachCardClick(cardId, modalId, loadFn) {
        const card = document.getElementById(cardId);
        if (card) {
            card.addEventListener('click', (e) => {
                // Do not open if in Edit Mode OR if clicking upload elements
                if (editMode || e.target.closest('.thumbnail-edit-overlay')) return;
                openModal(modalId);
                if (loadFn) loadFn();
            });
        }
    }

    attachCardClick('card-users', 'modal-users', loadUsers);
    attachCardClick('card-holidays', 'modal-holidays', loadHolidays);
    attachCardClick('card-jobs', 'modal-jobs', loadJobs);
    attachCardClick('card-locations', 'modal-locations', loadStates);

    // Filter Menu Setup (Click icon to show dropdown)
    function setupFilter(btnId, menuId, inputName, renderFn) {
        const btn = document.getElementById(btnId);
        const menu = document.getElementById(menuId);
        if (!btn || !menu) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });

        // Radio Button Change
        menu.querySelectorAll(`input[name="${inputName}"]`).forEach(radio => {
            radio.addEventListener('change', () => {
                renderFn();
                menu.classList.add('hidden');
            });
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!menu.contains(e.target) && e.target !== btn) {
                menu.classList.add('hidden');
            }
        });
    }

    setupFilter('job-filter-btn', 'job-filter-menu', 'job-status', renderJobs);
    setupFilter('state-filter-btn', 'state-filter-menu', 'state-status', renderStates);
    setupFilter('county-filter-btn', 'county-filter-menu', 'county-status', renderCounties);


    // =========================================================
    // 5. USER MANAGEMENT
    // =========================================================
    function loadUsers() {
        const input = document.getElementById('user-search');
        if(input) { input.value = ''; input.focus(); }
        
        const list = document.getElementById('user-dropdown-list');
        list.innerHTML = '<div class="dropdown-item" style="cursor:default">Loading...</div>';
        list.classList.remove('hidden');

        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users'))
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
            list.innerHTML = '<div class="dropdown-item" style="opacity:0.6; cursor:default">No users found</div>';
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

    const userSearch = document.getElementById('user-search');
    if (userSearch) {
        userSearch.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            renderUserList(allUsers.filter(u => u.displayname.toLowerCase().includes(term)));
            document.getElementById('user-dropdown-list').classList.remove('hidden');
        });
        
        // Hide dropdown on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.searchable-select-wrapper')) {
                document.getElementById('user-dropdown-list').classList.add('hidden');
            }
        });
    }

    const viewUserBtn = document.getElementById('btn-view-user');
    if (viewUserBtn) {
        viewUserBtn.addEventListener('click', () => {
            const uid = document.getElementById('selected-user-uid').value;
            if (uid) window.location.href = OC.generateUrl('/apps/stech_timesheet/') + '?target_user=' + uid;
        });
    }


    // =========================================================
    // 6. HOLIDAYS
    // =========================================================
    function loadHolidays() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'))
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('holiday-list');
                list.innerHTML = '';
                data.forEach(h => {
                    list.innerHTML += `
                        <div class="list-item">
                            <div>
                                <strong>${h.holiday_name}</strong><br>
                                <span style="font-size:11px; opacity:0.6">${h.holiday_start_date} to ${h.holiday_end_date}</span>
                            </div>
                            <button class="icon-delete" onclick="deleteHoliday(${h.holiday_id})" title="Delete">&times;</button>
                        </div>`;
                });
            });
    }

    const holidayForm = document.getElementById('form-holiday');
    if (holidayForm) {
        holidayForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const payload = {
                name: document.getElementById('holiday-name').value,
                start: document.getElementById('holiday-start').value,
                end: document.getElementById('holiday-end').value
            };
            apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'), {
                method: 'POST', body: JSON.stringify(payload)
            }).then(() => {
                holidayForm.reset();
                loadHolidays();
            });
        });
    }

    // Expose global for onclick
    window.deleteHoliday = function(id) {
        if(confirm('Delete?')) {
            apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays/'+id), { method:'DELETE' })
            .then(loadHolidays);
        }
    };


    // =========================================================
    // 7. JOBS
    // =========================================================
    function loadJobs() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/attributes'))
            .then(r => r.json())
            .then(data => {
                allJobs = data.jobs || [];
                renderJobs();
            });
    }

    function renderJobs() {
        const term = document.getElementById('job-search-input').value.toLowerCase();
        // Get checked radio value
        const statusRadio = document.querySelector('input[name="job-status"]:checked');
        const status = statusRadio ? statusRadio.value : 'active';
        
        const list = document.getElementById('job-list');
        list.innerHTML = '';

        allJobs.filter(j => {
            const isActive = j.job_archive == 0;
            // Filter Logic
            if (status === 'active' && !isActive) return false;
            if (status === 'archived' && isActive) return false;
            return j.job_name.toLowerCase().includes(term);
        }).forEach(j => {
            const isActive = j.job_archive == 0;
            list.innerHTML += `
                <div class="list-item" style="${!isActive ? 'opacity:0.6' : ''}">
                    <span>${j.job_name}</span>
                    <label class="admin-switch" title="Toggle Active">
                        <input type="checkbox" ${isActive ? 'checked' : ''} onchange="toggleJob(${j.job_id})">
                        <span class="admin-slider"></span>
                    </label>
                </div>`;
        });
    }

    const jobSearch = document.getElementById('job-search-input');
    if (jobSearch) jobSearch.addEventListener('input', renderJobs);

    const jobForm = document.getElementById('form-job');
    if (jobForm) {
        jobForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const payload = {
                name: document.getElementById('job-name').value,
                description: document.getElementById('job-desc').value
            };
            apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs'), {
                method: 'POST', body: JSON.stringify(payload)
            }).then(() => {
                jobForm.reset();
                loadJobs();
            });
        });
    }

    window.toggleJob = function(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs/'+id+'/toggle'), { method:'POST' })
        .then(loadJobs);
    };


    // =========================================================
    // 8. LOCATIONS
    // =========================================================
    function loadStates() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/attributes'))
            .then(r => r.json())
            .then(data => {
                allStates = data.states || [];
                renderStates();
            });
    }

    function renderStates() {
        const term = document.getElementById('state-search-input').value.toLowerCase();
        const statusRadio = document.querySelector('input[name="state-status"]:checked');
        const status = statusRadio ? statusRadio.value : 'all';
        
        const list = document.getElementById('state-list');
        list.innerHTML = '';

        allStates.filter(s => {
            const isEnabled = s.is_enabled == 1;
            if (status === 'enabled' && !isEnabled) return false;
            if (status === 'disabled' && isEnabled) return false;
            return s.state_name.toLowerCase().includes(term);
        }).forEach(s => {
            const div = document.createElement('div');
            div.className = 'list-item';
            div.innerHTML = `
                <span style="cursor:pointer; flex:1;">${s.state_name}</span>
                <label class="admin-switch">
                    <input type="checkbox" ${s.is_enabled == 1 ? 'checked' : ''}>
                    <span class="admin-slider"></span>
                </label>`;
            
            // Text click
            div.querySelector('span').addEventListener('click', () => {
                // Highlight
                document.querySelectorAll('#state-list .list-item').forEach(el => el.classList.remove('active-selection'));
                div.classList.add('active-selection');
                loadCounties(s.state_abbr, s.state_name);
            });

            // Toggle
            div.querySelector('input').addEventListener('change', () => toggleState(s.id));
            list.appendChild(div);
        });
    }

    const stateSearch = document.getElementById('state-search-input');
    if (stateSearch) stateSearch.addEventListener('input', renderStates);

    window.toggleState = function(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states/'+id+'/toggle'), { method:'POST' });
    };

    // --- COUNTIES ---
    function loadCounties(abbr, name) {
        document.getElementById('county-header').innerText = 'Counties: ' + name;
        const input = document.getElementById('county-search-input');
        input.disabled = false; input.value = '';
        
        document.getElementById('county-list').innerHTML = '<div style="padding:10px;">Loading...</div>';

        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/counties/'+abbr))
            .then(r => r.json())
            .then(c => {
                currentCounties = c;
                renderCounties();
            });
    }

    function renderCounties() {
        const term = document.getElementById('county-search-input').value.toLowerCase();
        const statusRadio = document.querySelector('input[name="county-status"]:checked');
        const status = statusRadio ? statusRadio.value : 'all';
        
        const list = document.getElementById('county-list');
        list.innerHTML = '';

        currentCounties.filter(c => {
            const isEnabled = c.is_enabled == 1;
            if (status === 'enabled' && !isEnabled) return false;
            if (status === 'disabled' && isEnabled) return false;
            return c.county_name.toLowerCase().includes(term);
        }).forEach(c => {
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

    const countySearch = document.getElementById('county-search-input');
    if (countySearch) countySearch.addEventListener('input', renderCounties);

    window.toggleCounty = function(id) {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/'+id+'/toggle'), { method:'POST' });
    };

});