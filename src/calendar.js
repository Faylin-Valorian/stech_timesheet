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
        this.injectArchiveToggle();

        this.instance = new FullCalendar(el, {
            plugins: [dayGridPlugin, interactionPlugin],
            initialView: 'dayGridMonth',
            firstDay: 0, // Sunday
            headerToolbar: false, 
            height: '100%',
            weekNumbers: true,
            
            // PATCH: Use eventSources to allow multiple data streams (Timesheets + Holidays)
            eventSources: [
                // SOURCE 1: Timesheets (Editable Data)
                {
                    events: (info, successCallback, failureCallback) => {
                        window.StechTimesheet.API.getTimesheets(info.startStr, info.endStr, this.archiveMode)
                            .then(data => successCallback(data))
                            .catch(err => failureCallback(err));
                    }
                },
                // SOURCE 2: Holidays (Visual Background Tags)
                {
                    events: (info, successCallback, failureCallback) => {
                        // Ensure your API Controller has a route for '/api/calendar/holidays'
                        window.StechTimesheet.API.request('get', '/api/calendar/holidays', {
                            start: info.startStr,
                            end: info.endStr
                        }).then(data => {
                            // Map API response to FullCalendar Background Events
                            const events = data.map(h => ({
                                id: 'holiday-' + (h.id || Math.random()),
                                title: h.name || h.holiday_name,
                                start: h.start || h.holiday_start_date,
                                end: h.end || h.holiday_end_date, // FullCalendar end is exclusive
                                display: 'background',
                                backgroundColor: 'rgba(255, 165, 0, 0.15)', // Distinct Orange Tint
                                classNames: ['fc-holiday-bg'],
                                extendedProps: { 
                                    isVisual: true,
                                    customBg: '' 
                                }
                            }));
                            successCallback(events);
                        }).catch(err => {
                            // Suppress errors if API is missing (keeps calendar functional)
                            console.warn('Failed to fetch holidays for calendar background', err);
                            successCallback([]);
                        });
                    }
                }
            ],
            
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

            // Handle clicking events
            eventClick: (info) => {
                const props = info.event.extendedProps;

                // Check for visual/background events (like Holidays)
                if (props.isVisual || props.is_visual || info.event.display === 'background') {
                    if (window.OCP && window.OCP.Toast && info.event.title) {
                        window.OCP.Toast.info(info.event.title);
                    }
                    return;
                }

                window.StechTimesheet.API.getTimesheetDetails(info.event.id)
                    .then(data => {
                        if (window.StechTimesheet.Form) {
                            window.StechTimesheet.Form.open(data.timesheet_date, data.timesheet_id);
                        }
                    })
                    .catch(err => console.error('Failed to load record details:', err));
            },

            // Handle clicking empty dates
            dateClick: (info) => {
                if (window.StechTimesheet.Form) {
                    window.StechTimesheet.Form.open(info.dateStr, null);
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

    injectArchiveToggle() {
        const container = document.getElementById('app-navigation');
        if (!container || document.getElementById('btn-toggle-archive')) return;

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
        container.appendChild(filterSection);

        const btn = document.getElementById('btn-toggle-archive');
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleArchiveMode(btn);
        });
    },

    toggleArchiveMode(btn) {
        if (this.archiveMode === 0) {
            this.archiveMode = 1;
            btn.textContent = "Back to Active";
            btn.classList.remove('secondary-button');
            btn.classList.add('primary-button');
            btn.style.backgroundColor = '#777777'; 
            btn.style.color = '#fff';
        } else {
            this.archiveMode = 0;
            btn.textContent = "Show Archived";
            btn.classList.remove('primary-button');
            btn.classList.add('secondary-button');
            btn.style.backgroundColor = 'transparent';
            btn.style.color = 'var(--color-main-text)';
        }
        this.refresh();
    },

    refresh() {
        this.instance.refetchEvents();
    }
};

export const Calendar = TimesheetCalendar;