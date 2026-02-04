// FIX: Rename the import so it doesn't conflict with your export
import { Calendar as FullCalendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

/**
 * Calendar Module
 * Wraps FullCalendar initialization and event handling.
 */
export const TimesheetCalendar = {
    instance: null,
    archiveMode: 0, // 0 = Active, 1 = Archived

    /**
     * Initialize the calendar
     */
    init(el) {
        // Inject the sidebar toggle button for Archive view
        this.injectArchiveToggle();

        // FIX: Use the renamed import 'FullCalendar'
        this.instance = new FullCalendar(el, {
            plugins: [dayGridPlugin, interactionPlugin],
            initialView: 'dayGridMonth',
            firstDay: 0, // Sunday
            headerToolbar: false, // Custom sidebar buttons used instead
            height: '100%',
            weekNumbers: true,
            
            // Data source for calendar events
            events: (info, successCallback, failureCallback) => {
                // Pass the archiveMode flag to the API
                window.StechTimesheet.API.getTimesheets(info.startStr, info.endStr, this.archiveMode)
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

            // Handle clicking an existing record
            eventClick: (info) => {
                const props = info.event.extendedProps;

                if (props.isVisual || props.is_visual || info.event.display === 'background') {
                    if (window.OCP && window.OCP.Toast) {
                        window.OCP.Toast.info('This record is system generated and cannot be edited manually.');
                    }
                    return;
                }

                window.StechTimesheet.API.getTimesheetDetails(info.event.id)
                    .then(data => {
                        if (window.StechTimesheet.Form) {
                            window.StechTimesheet.Form.open(data.timesheet_date, data.timesheet_id);
                        } else {
                            console.error('StechTimesheet.Form module is not initialized.');
                        }
                    })
                    .catch(err => console.error('Failed to load record details:', err));
            },

            // Handle clicking an empty date
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
        });

        const dateInput = document.getElementById('date-picker-input');
        dateInput?.addEventListener('change', (e) => {
            if (e.target.value) this.instance.gotoDate(e.target.value);
        });
    },

    toggleActiveButton(activeBtn) {
        document.querySelectorAll('.view-buttons button').forEach(btn => btn.classList.remove('active'));
        activeBtn.classList.add('active');
    },

    // Inject the Archive Toggle Button into the sidebar dynamically
    injectArchiveToggle() {
        const container = document.getElementById('app-navigation');
        if (!container) return;

        // Check if it already exists to avoid duplicates
        if (document.getElementById('btn-toggle-archive')) return;

        // Create section for the filter
        const filterSection = document.createElement('ul');
        filterSection.className = 'nav-section-views';
        filterSection.style.marginTop = '20px';
        filterSection.innerHTML = `
            <li class="nav-section-header" style="opacity:0.6; font-size:12px; font-weight:bold; margin-bottom:5px;">FILTER</li>
            <li class="nav-item">
                <button id="btn-toggle-archive" class="secondary-button" style="width:100%; text-align:center;">
                    Show Archived
                </button>
            </li>
        `;

        // Append to sidebar
        container.appendChild(filterSection);

        // Bind click event
        const btn = document.getElementById('btn-toggle-archive');
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleArchiveMode(btn);
        });
    },

    // Handle the toggle logic
    toggleArchiveMode(btn) {
        if (this.archiveMode === 0) {
            // Switch to Archived
            this.archiveMode = 1;
            btn.textContent = "Back to Active";
            btn.classList.remove('secondary-button');
            btn.classList.add('primary-button');
            btn.style.backgroundColor = '#777777'; // Gray to match archive theme
            btn.style.color = '#fff';
        } else {
            // Switch to Active
            this.archiveMode = 0;
            btn.textContent = "Show Archived";
            btn.classList.remove('primary-button');
            btn.classList.add('secondary-button');
            btn.style.backgroundColor = 'transparent';
            btn.style.color = 'var(--color-main-text)';
        }
        
        // Reload events with new filter
        this.refresh();
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