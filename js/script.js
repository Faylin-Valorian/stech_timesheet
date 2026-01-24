document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    // 1. Initialize the Calendar
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        firstDay: 0, 
        
        // --- THIS HIDES THE DEFAULT BUTTONS ---
        headerToolbar: false, 
        // --------------------------------------

        height: '100%',
        themeSystem: 'standard',
        
        // Update the Sidebar Title (e.g. "January 2026") when view changes
        datesSet: function(info) {
            var titleEl = document.getElementById('current-date-label');
            if (titleEl) {
                titleEl.innerText = info.view.title;
            }
        },

        windowResize: function(view) {
            calendar.render();
        }
    });

    calendar.render();

    // 2. Connect the Sidebar Buttons to the Calendar
    
    // Previous Month Button
    var prevBtn = document.getElementById('nav-prev');
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            calendar.prev();
        });
    }

    // Next Month Button
    var nextBtn = document.getElementById('nav-next');
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            calendar.next();
        });
    }

    // "Month" View Button
    var monthBtn = document.getElementById('view-month');
    if (monthBtn) {
        monthBtn.addEventListener('click', function() {
            calendar.changeView('dayGridMonth');
            toggleActive(monthBtn);
        });
    }

    // "Week" View Button
    var weekBtn = document.getElementById('view-week');
    if (weekBtn) {
        weekBtn.addEventListener('click', function() {
            calendar.changeView('timeGridWeek');
            toggleActive(weekBtn);
        });
    }

    // "Today" Button
    var todayBtn = document.getElementById('view-today');
    if (todayBtn) {
        todayBtn.addEventListener('click', function() {
            calendar.today();
        });
    }

    // Date Picker Input (Hidden)
    var dateLabel = document.getElementById('current-date-label');
    var dateInput = document.getElementById('date-picker-input');

    if (dateLabel && dateInput) {
        // Clicking label opens picker
        dateLabel.addEventListener('click', function() {
            dateInput.showPicker(); 
        });

        // Changing date updates calendar
        dateInput.addEventListener('change', function() {
            if (this.value) {
                calendar.gotoDate(this.value + '-01');
            }
        });
    }

    // Helper to switch the "Active" blue button state
    function toggleActive(activeBtn) {
        var buttons = document.querySelectorAll('.view-buttons button');
        buttons.forEach(function(btn) {
            btn.classList.remove('active');
        });
        activeBtn.classList.add('active');
    }
});