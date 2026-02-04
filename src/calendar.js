/**
 * Calendar Module
 * Wraps FullCalendar initialization and event handling.
 */
export const TimesheetCalendar = {
    instance: null,

    /**
     * Initialize the calendar
     */
    init(el) {
        this.instance = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            firstDay: 0, // Sunday
            headerToolbar: false, // Custom sidebar buttons used instead
            height: '100%',
            themeSystem: 'standard',
            
            // Data source for calendar events
            events: (info, successCallback, failureCallback) => {
                // Grounded in window.StechTimesheet.API initialized in main.js
                window.StechTimesheet.API.getTimesheets(info.startStr, info.endStr)
                    .then(data => successCallback(data))
                    .catch(err => failureCallback(err));
            },
            
            // Custom event rendering
            eventContent: (arg) => {
                let div = document.createElement('div');
                div.className = 'fc-event-content-box'; 
                
                const customBg = arg.event.extendedProps.customBg;
                if (customBg && customBg.trim() !== '') {
                    div.style.background = customBg;
                    if (customBg.includes('url(')) {
                        div.style.backgroundSize = 'cover';
                        div.style.backgroundPosition = 'center';
                        div.style.textShadow = '0 1px 2px rgba(0,0,0,0.8)'; 
                    }
                } else {
                    div.style.backgroundColor = arg.event.backgroundColor;
                }

                div.innerText = arg.event.title;
                return { domNodes: [div] };
            },

            // FIX: Handle clicking an existing record
            eventClick: (info) => {
                if (info.event.extendedProps.isVisual) {
                    if (window.OCP && window.OCP.Toast) {
                        window.OCP.Toast.info('This record is system generated and cannot be edited manually.');
                    }
                    return;
                }

                // FIX: Ensure we use the exact method name from your api.js
                window.StechTimesheet.API.getTimesheetDetails(info.event.id)
                    .then(data => {
                        // FIX: Verify Form exists before calling open to prevent TypeError
                        if (window.StechTimesheet.Form) {
                            window.StechTimesheet.Form.open(data.timesheet_date, data.timesheet_id);
                        } else {
                            console.error('StechTimesheet.Form module is not initialized.');
                        }
                    })
                    .catch(err => console.error('Failed to load record details:', err));
            },

            // FIX: Handle clicking an empty date
            dateClick: (info) => {
                if (window.StechTimesheet.Form) {
                    window.StechTimesheet.Form.open(info.dateStr, null);
                } else {
                    console.error('StechTimesheet.Form module is not initialized.');
                }
            },

            datesSet: (info) => {
                const titleEl = document.getElementById('current-date-label');
                if (titleEl) titleEl.innerText = info.view.title;
            },

            windowResize: () => {
                this.instance.render();
            }
        });

        this.instance.render();
        this.setupSidebarNavigation();
    },

    /**
     * Connects sidebar buttons to the calendar instance
     */
    setupSidebarNavigation() {
        document.getElementById('nav-prev')?.addEventListener('click', () => this.instance.prev());
        document.getElementById('nav-next')?.addEventListener('click', () => this.instance.next());
        
        document.getElementById('view-month')?.addEventListener('click', (e) => {
            this.instance.changeView('dayGridMonth');
            this.toggleActiveButton(e.target);
        });
        
        document.getElementById('view-week')?.addEventListener('click', (e) => {
            this.instance.changeView('dayGridWeek');
            this.toggleActiveButton(e.target);
        });

        document.getElementById('view-today')?.addEventListener('click', () => {
            this.instance.today();
            const todayStr = new Date().toISOString().split('T')[0];
            if (window.StechTimesheet.Form) {
                window.StechTimesheet.Form.open(todayStr, null);
            }
        });

        const dateInput = document.getElementById('date-picker-input');
        dateInput?.addEventListener('change', (e) => {
            if (e.target.value) this.instance.gotoDate(e.target.value + '-01');
        });
    },

    toggleActiveButton(activeBtn) {
        document.querySelectorAll('.view-buttons button').forEach(btn => btn.classList.remove('active'));
        activeBtn.classList.add('active');
    },

    /**
     * Refresh the calendar data
     */
    refresh() {
        this.instance.refetchEvents();
    }
};

// Aliased for export to main.js
export const Calendar = TimesheetCalendar;