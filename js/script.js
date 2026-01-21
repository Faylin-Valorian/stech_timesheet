document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth', // Default to Monthly view
        firstDay: 0, // 0 = Sunday
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek' // The two requested tabs
        },
        buttonText: {
            today: 'Today',
            month: 'Month',
            week: 'Week'
        },
        height: '100%',
        themeSystem: 'standard', // We will style this with CSS to match NC
        
        // This makes it feel "Modern" and responsive
        windowResize: function(view) {
            calendar.render();
        },
        
        // Placeholder for future click events
        dateClick: function(info) {
            console.log('Clicked on: ' + info.dateStr);
        }
    });

    calendar.render();
});