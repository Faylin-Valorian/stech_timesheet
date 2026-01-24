document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    // --- Elements ---
    const modal = document.getElementById('timesheet-modal');
    const form = document.getElementById('timesheet-form');
    
    // --- 1. Initialize Calendar ---
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        firstDay: 0,
        headerToolbar: false,
        height: '100%',
        themeSystem: 'standard',
        
        dateClick: function(info) {
            openModal(info.dateStr);
        },

        datesSet: function(info) {
            var titleEl = document.getElementById('current-date-label');
            if (titleEl) titleEl.innerText = info.view.title;
        },
        windowResize: function(view) { calendar.render(); }
    });
    calendar.render();

    // --- 2. Modal Functions ---
    
    function openModal(dateStr) {
        // Populate Date Field
        document.getElementById('entry-date').value = dateStr;
        
        // Reset form & dynamic rows
        form.reset();
        document.getElementById('work-rows-container').innerHTML = ''; 
        addWorkRow(); // Start with 1 row
        
        // Reset Travel Section Visibility
        document.getElementById('travel-fields-container').classList.remove('visible');

        // Show Modal
        if (typeof modal.showModal === "function") {
            modal.showModal();
        } else {
            alert("Browser not supported");
        }
    }

    // Close buttons
    document.getElementById('btn-cancel').addEventListener('click', () => modal.close());
    document.getElementById('modal-close-btn').addEventListener('click', () => modal.close());

    // --- 3. Auto-Calculate Hours ---
    // Listen to changes on time-in, time-out, and break
    const timeInputs = document.querySelectorAll('.calc-time');
    timeInputs.forEach(input => {
        input.addEventListener('change', calculateTotalHours);
    });

    function calculateTotalHours() {
        const inStr = document.getElementById('time-in').value;
        const outStr = document.getElementById('time-out').value;
        const breakMin = parseInt(document.getElementById('break-min').value) || 0;
        const totalEl = document.getElementById('total-hours');

        if (inStr && outStr) {
            // Create dates to compare (arbitrary date, just need time diff)
            let dateIn = new Date(`2000-01-01T${inStr}`);
            let dateOut = new Date(`2000-01-01T${outStr}`);

            // Handle overnight (if out is before in, assume next day)
            if (dateOut < dateIn) {
                dateOut.setDate(dateOut.getDate() + 1);
            }

            let diffMs = dateOut - dateIn;
            let diffMins = Math.floor(diffMs / 60000); // Total minutes worked
            
            // Subtract break
            diffMins -= breakMin;

            if (diffMins < 0) diffMins = 0;

            // Convert to decimal hours (e.g. 8.50)
            let hours = (diffMins / 60).toFixed(2);
            totalEl.value = hours;
        } else {
            totalEl.value = "0.00";
        }
    }

    // --- 4. Dynamic Work Rows ---
    document.getElementById('btn-add-row').addEventListener('click', addWorkRow);

    function addWorkRow() {
        const container = document.getElementById('work-rows-container');
        const index = container.children.length + 1; // Simple index
        
        const row = document.createElement('div');
        row.className = 'work-row';
        row.innerHTML = `
            <input type="text" name="work_desc[]" class="form-control" placeholder="Description...">
            <input type="number" name="work_percent[]" class="form-control text-center" placeholder="0" min="0" max="100">
            <div class="btn-remove-row" title="Remove">×</div>
        `;
        
        // Add remove listener
        row.querySelector('.btn-remove-row').addEventListener('click', function() {
            row.remove();
        });

        container.appendChild(row);
    }

    // --- 5. Travel Toggle Logic ---
    const travelToggle = document.getElementById('toggle-travel');
    const travelContainer = document.getElementById('travel-fields-container');

    travelToggle.addEventListener('change', function() {
        if (this.checked) {
            travelContainer.classList.add('visible');
        } else {
            travelContainer.classList.remove('visible');
        }
    });

    // --- 6. Sidebar Logic ---
    setupSidebarButtons(calendar);
});

// Sidebar Helper
function setupSidebarButtons(calendar) {
    var prevBtn = document.getElementById('nav-prev');
    if (prevBtn) prevBtn.addEventListener('click', () => calendar.prev());

    var nextBtn = document.getElementById('nav-next');
    if (nextBtn) nextBtn.addEventListener('click', () => calendar.next());

    var monthBtn = document.getElementById('view-month');
    if (monthBtn) monthBtn.addEventListener('click', function() {
        calendar.changeView('dayGridMonth');
        toggleActive(this);
    });

    var weekBtn = document.getElementById('view-week');
    if (weekBtn) weekBtn.addEventListener('click', function() {
        calendar.changeView('dayGridWeek');
        toggleActive(this);
    });

    // "Today" Button -> Go to today AND open modal
    var todayBtn = document.getElementById('view-today');
    if (todayBtn) todayBtn.addEventListener('click', function() {
        calendar.today();
        // Open modal for today's date
        var todayStr = new Date().toISOString().split('T')[0];
        // We need to call the internal openModal. 
        // Since we are outside the scope, we manually trigger the date field set and modal show
        // Note: For cleaner code, openModal should be accessible, but for this snippet:
        // We will just re-simulate a date click on today if possible, or manual open:
        document.getElementById('entry-date').value = todayStr;
        document.getElementById('timesheet-form').reset();
        document.getElementById('work-rows-container').innerHTML = '';
        
        // Add one empty row
        // We can't access 'addWorkRow' here easily without refactoring, 
        // so let's just create one manually or rely on user to click add.
        // Better fix: trigger a click on a hidden button inside the scope if needed.
        // For now, let's just show the modal:
        document.getElementById('timesheet-modal').showModal();
        
        // Quick fix to ensure at least one row exists:
        const container = document.getElementById('work-rows-container');
        if (container.children.length === 0) {
            document.getElementById('btn-add-row').click();
        }
    });

    // Date Picker
    var dateLabel = document.getElementById('current-date-label');
    var dateInput = document.getElementById('date-picker-input');
    if (dateLabel && dateInput) {
        dateLabel.addEventListener('click', () => dateInput.showPicker());
        dateInput.addEventListener('change', function() {
            if (this.value) calendar.gotoDate(this.value + '-01');
        });
    }

    function toggleActive(activeBtn) {
        document.querySelectorAll('.view-buttons button').forEach(btn => btn.classList.remove('active'));
        activeBtn.classList.add('active');
    }
}