console.log('Stech Timesheet: JS Loaded');

// This function checks for the element every 100ms until it finds it
function waitForCalendar() {
    var calendarEl = document.getElementById('calendar');

    if (calendarEl) {
        console.log('Stech Timesheet: Found #calendar div. Initializing...');
        initCalendar(calendarEl);
    } else {
        // Not found yet, try again in 100ms
        setTimeout(waitForCalendar, 100);
    }
}

function initCalendar(element) {
    // Safety check: Don't double-initialize if already running
    if (element.innerHTML !== '') return;

    if (typeof FullCalendar === 'undefined') {
        console.error('Stech Timesheet: FullCalendar library missing. Check CDN/Internet.');
        element.innerHTML = '<h2 style="color:red; padding:20px;">Error: Calendar Library Failed to Load</h2>';
        return;
    }

    var calendar = new FullCalendar.Calendar(element, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        firstDay: 0, 
        height: '100%',
        windowResize: function(view) {
            calendar.render();
        }
    });

    calendar.render();
    console.log('Stech Timesheet: Render complete.');
}

// Start looking for the calendar immediately
waitForCalendar();