document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    // --- Overlay Elements ---
    const overlay = document.getElementById('timesheet-modal-overlay');
    const form = document.getElementById('timesheet-form');
    
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

    // --- Modal Functions ---
    
    function openModal(dateStr) {
        var dateInput = document.getElementById('entry-date');
        
        // Reset Logic
        form.reset();
        
        // Force Date Set (Must happen after reset)
        if (dateInput) {
            dateInput.value = dateStr;
        }
        
        document.getElementById('work-rows-container').innerHTML = ''; 
        addWorkRow(); 
        document.getElementById('travel-fields-container').classList.remove('visible');

        // Show Overlay
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    function closeModal() {
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    // Close Listeners
    document.getElementById('btn-cancel').addEventListener('click', closeModal);
    document.getElementById('modal-close-btn').addEventListener('click', closeModal);

    // --- Auto-Calculate Hours ---
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
            let dateIn = new Date(`2000-01-01T${inStr}`);
            let dateOut = new Date(`2000-01-01T${outStr}`);
            if (dateOut < dateIn) dateOut.setDate(dateOut.getDate() + 1);

            let diffMins = Math.floor((dateOut - dateIn) / 60000) - breakMin;
            if (diffMins < 0) diffMins = 0;
            totalEl.value = (diffMins / 60).toFixed(2);
        } else {
            totalEl.value = "0.00";
        }
    }

    // --- Dynamic Rows ---
    document.getElementById('btn-add-row').addEventListener('click', addWorkRow);

    function addWorkRow() {
        const container = document.getElementById('work-rows-container');
        const row = document.createElement('div');
        row.className = 'work-row';
        row.innerHTML = `
            <input type="text" name="work_desc[]" class="form-control" placeholder="Description...">
            <input type="number" name="work_percent[]" class="form-control text-center" placeholder="0" min="0" max="100">
            <div class="btn-remove-row" title="Remove">&times;</div>
        `;
        row.querySelector('.btn-remove-row').addEventListener('click', () => row.remove());
        container.appendChild(row);
    }

    // --- Travel Toggle ---
    document.getElementById('toggle-travel').addEventListener('change', function() {
        const container = document.getElementById('travel-fields-container');
        if (this.checked) container.classList.add('visible');
        else container.classList.remove('visible');
    });

    setupSidebarButtons(calendar);
});

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

    var todayBtn = document.getElementById('view-today');
    if (todayBtn) todayBtn.addEventListener('click', function() {
        calendar.today();
        
        // Manual Open for Today
        var now = new Date();
        var offset = now.getTimezoneOffset();
        var today = new Date(now.getTime() - (offset*60*1000));
        var todayStr = today.toISOString().split('T')[0];

        // Ensure date populates
        var dateInput = document.getElementById('entry-date');
        var form = document.getElementById('timesheet-form');
        var overlay = document.getElementById('timesheet-modal-overlay');

        if (dateInput && form && overlay) {
            form.reset();
            dateInput.value = todayStr; // Force Value
            
            document.getElementById('work-rows-container').innerHTML = '';
            document.getElementById('btn-add-row').click(); 
            document.getElementById('travel-fields-container').classList.remove('visible');
            
            overlay.style.display = 'flex';
        }
    });

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