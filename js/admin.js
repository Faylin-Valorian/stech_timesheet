document.addEventListener('DOMContentLoaded', function() {
    
    // --- TAB NAVIGATION ---
    const tabs = document.querySelectorAll('.nav-link[data-tab]');
    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            // Remove active class
            document.querySelectorAll('.nav-link').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.admin-tab-content').forEach(c => c.classList.add('hidden'));
            
            // Activate clicked
            e.currentTarget.classList.add('active');
            const target = e.currentTarget.dataset.tab;
            document.getElementById('tab-' + target).classList.remove('hidden');
            
            // Load Data if needed
            if(target === 'users') loadUsers();
            if(target === 'holidays') loadHolidays();
            if(target === 'jobs') loadAttributes(); // Reuse logic
            if(target === 'locations') loadAttributes();
        });
    });

    // --- USERS ---
    function loadUsers() {
        const select = document.getElementById('user-select');
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/users'))
            .then(r => r.json())
            .then(users => {
                select.innerHTML = '<option value="">Select User...</option>';
                users.forEach(u => {
                    select.innerHTML += `<option value="${u.uid}">${u.displayname} (${u.uid})</option>`;
                });
            });
    }

    document.getElementById('btn-view-user').addEventListener('click', () => {
        const uid = document.getElementById('user-select').value;
        if(uid) {
            // Redirect to main app with query param
            window.location.href = OC.generateUrl('/apps/stech_timesheet/') + '?target_user=' + uid;
        }
    });

    // --- HOLIDAYS ---
    function loadHolidays() {
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'))
            .then(r => r.json())
            .then(data => {
                const tbody = document.querySelector('#holiday-table tbody');
                tbody.innerHTML = '';
                data.forEach(h => {
                    const row = `<tr>
                        <td>${h.holiday_name}</td>
                        <td>${h.holiday_start_date}</td>
                        <td>${h.holiday_end_date}</td>
                        <td>
                            <button class="icon-delete" onclick="deleteHoliday(${h.holiday_id})"></button>
                        </td>
                    </tr>`;
                    tbody.innerHTML += row;
                });
            });
    }

    document.getElementById('btn-add-holiday').addEventListener('click', () => {
        document.getElementById('holiday-id').value = '';
        document.getElementById('holiday-form').reset();
        document.getElementById('holiday-modal').classList.remove('hidden');
    });
    
    document.getElementById('btn-save-holiday').addEventListener('click', () => {
        const payload = {
            id: document.getElementById('holiday-id').value,
            name: document.getElementById('holiday-name').value,
            start: document.getElementById('holiday-start').value,
            end: document.getElementById('holiday-end').value
        };
        fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays'), {
            method: 'POST',
            headers: {'requesttoken': OC.requestToken, 'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(() => {
            document.getElementById('holiday-modal').classList.add('hidden');
            loadHolidays();
        });
    });

    // --- JOBS & LOCATIONS (ATTRIBUTES) ---
    function loadAttributes() {
        // We reuse the public attributes API but maybe we need a dedicated one for admin to see disabled items?
        // Actually, let's create a dedicated one or just modify the controller. 
        // For now, assume the public one works but we want to fetch EVERYTHING.
        // Let's implement the fetching logic here specifically for admin
        
        // Fetch Jobs logic
        // ... (Similar fetch pattern as above for Jobs and Locations)
        // ... (Click listeners for toggles calling the /toggle API endpoints)
    }
    
    // Global exposure for onclick handlers
    window.deleteHoliday = function(id) {
        if(confirm('Delete this holiday?')) {
            fetch(OC.generateUrl('/apps/stech_timesheet/api/admin/holidays/' + id), {
                method: 'DELETE',
                headers: {'requesttoken': OC.requestToken}
            }).then(loadHolidays);
        }
    };

    // Initial Load
    loadUsers();
});