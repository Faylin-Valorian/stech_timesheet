document.addEventListener('DOMContentLoaded', function() {
    
    // --- STATE ---
    let allUsers = [];
    let allJobs = [];
    let allStates = [];
    let currentCounties = [];
    let editMode = false;
    const cards = ['users', 'holidays', 'jobs', 'locations'];
    const descriptionCache = {};

    // --- HELPER: Fetch Wrapper ---
    function apiFetch(url, options = {}) {
        if (!options.headers) options.headers = {};
        options.headers['requesttoken'] = OC.requestToken;
        return fetch(url, options);
    }

    // --- 1. INITIALIZATION & IMAGE ERROR HANDLING ---
    document.querySelectorAll('.card-thumbnail-img').forEach(img => {
        // If broken on load
        if (img.complete && img.naturalWidth === 0 && img.dataset.fallbackSrc) {
            img.src = img.dataset.fallbackSrc;
        }
        // If broken dynamically
        img.addEventListener('error', function() {
            if (this.dataset.fallbackSrc && this.src !== this.dataset.fallbackSrc) {
                this.src = this.dataset.fallbackSrc;
            }
        });
    });

    // Load Settings
    apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/settings'))
        .then(r => r.json())
        .then(settings => {
            cards.forEach(cardId => {
                const descKey = `desc_${cardId}`;
                const p = document.getElementById(`desc-${cardId}`);
                if (settings[descKey] && p) p.innerText = settings[descKey];
                if (p) descriptionCache[cardId] = p.innerText;
            });
        }).catch(console.error);

    // --- 2. EDIT MODE & UPLOADS ---
    document.getElementById('admin-edit-mode').addEventListener('change', function() {
        editMode = this.checked;
        editMode ? (document.body.classList.add('admin-edit-mode-active'), enableEditUi()) : (document.body.classList.remove('admin-edit-mode-active'), saveAndDisableEditUi());
    });

    function enableEditUi() {
        cards.forEach(cardId => {
            const p = document.getElementById(`desc-${cardId}`);
            if(!p) return;
            const t = document.createElement('textarea');
            t.id = `desc-textarea-${cardId}`; t.className = 'editable-desc-textarea'; t.value = p.innerText;
            p.replaceWith(t);
        });
    }

    function saveAndDisableEditUi() {
        const promises = [];
        cards.forEach(cardId => {
            // Text Save
            const t = document.getElementById(`desc-textarea-${cardId}`);
            if(t) {
                const txt = t.value.trim();
                const p = document.createElement('p');
                p.id = `desc-${cardId}`; p.className = 'editable-desc'; p.innerText = txt;
                t.replaceWith(p);
                if(txt !== descriptionCache[cardId]) {
                    descriptionCache[cardId] = txt;
                    promises.push(apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/settings'), {
                        method: 'POST', headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ key: `desc_${cardId}`, value: txt })
                    }));
                }
            }

            // Image Upload
            const input = document.getElementById(`file-upload-${cardId}`);
            if(input && input.files.length > 0) {
                const fd = new FormData(); fd.append('image', input.files[0]);
                
                const uploadPromise = apiFetch(OC.generateUrl(`/apps/stech_timesheet/api/admin/thumbnail/${cardId}`), {
                    method: 'POST', body: fd
                }).then(r => {
                    if(r.ok) {
                        const img = document.getElementById(`thumb-img-${cardId}`);
                        if(img) img.src = img.src.split('?')[0] + '?t=' + new Date().getTime();
                    } else {
                        console.error('Upload failed', r.statusText);
                    }
                });
                promises.push(uploadPromise);
                input.value = '';
            }
        });

        if(promises.length) {
            Promise.all(promises)
                .then(() => OC.Notification.showTemporary('Settings saved successfully'))
                .catch(err => OC.Notification.showTemporary('Error saving settings'));
        }
    }

    // --- 3. MODALS ---
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(modal) { modal.style.display = 'none'; }
    document.querySelectorAll('.close-modal').forEach(b => b.addEventListener('click', function() { closeModal(this.closest('.modal-overlay')); }));
    document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) { if(e.target === this) closeModal(this); }));

    function attachCard(id, modal, fn) {
        document.getElementById(id).addEventListener('click', (e) => {
            if(!editMode && !e.target.closest('.thumbnail-edit-overlay')) { openModal(modal); fn(); }
        });
    }
    attachCard('card-users', 'modal-users', loadUsers);
    attachCard('card-holidays', 'modal-holidays', loadHolidays);
    attachCard('card-jobs', 'modal-jobs', loadJobs);
    attachCard('card-locations', 'modal-locations', loadStates);

    // --- 4. FILTER MENUS ---
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
    setupFilter('job-filter-btn', 'job-filter-menu', 'job-status', renderJobs);
    setupFilter('state-filter-btn', 'state-filter-menu', 'state-status', renderStates);
    setupFilter('county-filter-btn', 'county-filter-menu', 'county-status', renderCounties);

    // --- 5. LOGIC: USERS ---
    function loadUsers() {
        const i = document.getElementById('user-search'); i.value=''; i.focus();
        const l = document.getElementById('user-dropdown-list'); l.innerHTML='<div style="padding:10px;">Loading...</div>'; l.classList.remove('hidden');
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users')).then(r => r.json()).then(u => { allUsers = u; renderUserList(u); });
    }
    function renderUserList(users) {
        const l = document.getElementById('user-dropdown-list'); l.innerHTML='';
        if(users.length===0) { l.innerHTML='<div style="padding:10px;">No users</div>'; return; }
        users.forEach(u => {
            const d = document.createElement('div'); d.className='dropdown-item'; d.innerText = u.displayname;
            d.addEventListener('click', () => {
                document.getElementById('user-search').value = u.displayname;
                document.getElementById('selected-user-uid').value = u.uid;
                l.classList.add('hidden'); document.getElementById('btn-view-user').disabled=false;
            });
            l.appendChild(d);
        });
    }
    document.getElementById('user-search').addEventListener('input', function() {
        renderUserList(allUsers.filter(u => u.displayname.toLowerCase().includes(this.value.toLowerCase())));
        document.getElementById('user-dropdown-list').classList.remove('hidden');
    });
    document.addEventListener('click', (e) => { if(!e.target.closest('.searchable-select-wrapper')) document.getElementById('user-dropdown-list').classList.add('hidden'); });
    document.getElementById('btn-view-user').addEventListener('click', () => {
        window.location.href = OC.generateUrl('/apps/stech_timesheet/') + '?target_user=' + document.getElementById('selected-user-uid').value;
    });

    // --- 6. LOGIC: HOLIDAYS ---
    function loadHolidays() {
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays')).then(r => r.json()).then(data => {
            const list = document.getElementById('holiday-list'); list.innerHTML = '';
            data.forEach(h => { list.innerHTML += `<div class="list-item"><div><strong>${h.holiday_name}</strong><br><span style="font-size:11px">${h.holiday_start_date}</span></div><button class="icon-delete" onclick="deleteHoliday(${h.holiday_id})" title="Delete">&times;</button></div>`; });
        });
    }
    document.getElementById('form-holiday').addEventListener('submit', (e) => {
        e.preventDefault();
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'), { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name:document.getElementById('holiday-name').value, start:document.getElementById('holiday-start').value, end:document.getElementById('holiday-end').value}) }).then(() => { document.getElementById('form-holiday').reset(); loadHolidays(); });
    });
    window.deleteHoliday = function(id) { if(confirm('Delete?')) apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays/'+id), { method:'DELETE' }).then(loadHolidays); };

    // --- 7. LOGIC: JOBS ---
    function loadJobs() { apiFetch(OC.generateUrl('/apps/stech_timesheet/api/attributes')).then(r => r.json()).then(d => { allJobs = d.jobs || []; renderJobs(); }); }
    function renderJobs() {
        const term = document.getElementById('job-search-input').value.toLowerCase();
        const status = document.querySelector('input[name="job-status"]:checked').value;
        const list = document.getElementById('job-list'); list.innerHTML = '';
        allJobs.filter(j => {
            const active = j.job_archive == 0;
            if(status === 'active' && !active) return false;
            if(status === 'archived' && active) return false;
            return j.job_name.toLowerCase().includes(term);
        }).forEach(j => {
            const active = j.job_archive == 0;
            list.innerHTML += `<div class="list-item" style="${!active?'opacity:0.6':''}"><span>${j.job_name}</span><label class="admin-switch"><input type="checkbox" ${active?'checked':''} onchange="toggleJob(${j.job_id})"><span class="admin-slider"></span></label></div>`;
        });
    }
    document.getElementById('job-search-input').addEventListener('input', renderJobs);
    document.getElementById('form-job').addEventListener('submit', (e) => {
        e.preventDefault();
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs'), { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name:document.getElementById('job-name').value, description:document.getElementById('job-desc').value}) }).then(() => { document.getElementById('form-job').reset(); loadJobs(); });
    });
    window.toggleJob = function(id) { apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/jobs/'+id+'/toggle'), { method:'POST' }).then(loadJobs); };

    // --- 8. LOGIC: LOCATIONS ---
    function loadStates() { apiFetch(OC.generateUrl('/apps/stech_timesheet/api/attributes')).then(r => r.json()).then(d => { allStates = d.states || []; renderStates(); }); }
    function renderStates() {
        const term = document.getElementById('state-search-input').value.toLowerCase();
        const status = document.querySelector('input[name="state-status"]:checked').value;
        const list = document.getElementById('state-list'); list.innerHTML = '';
        allStates.filter(s => {
            const en = s.is_enabled == 1;
            if(status === 'enabled' && !en) return false;
            if(status === 'disabled' && en) return false;
            return s.state_name.toLowerCase().includes(term);
        }).forEach(s => {
            const div = document.createElement('div'); div.className = 'list-item';
            div.innerHTML = `<span style="cursor:pointer;flex:1">${s.state_name}</span><label class="admin-switch"><input type="checkbox" ${s.is_enabled==1?'checked':''}><span class="admin-slider"></span></label>`;
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
    window.toggleState = function(id) { apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/states/'+id+'/toggle'), { method:'POST' }); };

    function loadCounties(abbr, name) {
        document.getElementById('county-header').innerText = name;
        document.getElementById('county-search-input').disabled = false;
        apiFetch(OC.generateUrl('/apps/stech_timesheet/api/counties/'+abbr)).then(r => r.json()).then(c => { currentCounties = c; renderCounties(); });
    }
    function renderCounties() {
        const term = document.getElementById('county-search-input').value.toLowerCase();
        const status = document.querySelector('input[name="county-status"]:checked').value;
        const list = document.getElementById('county-list'); list.innerHTML = '';
        currentCounties.filter(c => {
            const en = c.is_enabled == 1;
            if(status === 'enabled' && !en) return false;
            if(status === 'disabled' && en) return false;
            return c.county_name.toLowerCase().includes(term);
        }).forEach(c => {
            list.innerHTML += `<div class="list-item"><span>${c.county_name}</span><label class="admin-switch"><input type="checkbox" ${c.is_enabled==1?'checked':''} onchange="toggleCounty(${c.id})"><span class="admin-slider"></span></label></div>`;
        });
    }
    document.getElementById('county-search-input').addEventListener('input', renderCounties);
    window.toggleCounty = function(id) { apiFetch(OC.generateUrl('/apps/stech_timesheet/api/admin/counties/'+id+'/toggle'), { method:'POST' }); };
});