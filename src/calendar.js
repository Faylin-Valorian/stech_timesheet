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
                        window.StechTimesheet.API.request('get', '/api/calendar/holidays', {
                            start: info.startStr,
                            end: info.endStr
                        }).then(data => {
                            const events = [];
                            
                            data.forEach(h => {
                                // PATCH: Split multi-day holidays into individual daily blocks
                                // This ensures text appears on EVERY block.
                                
                                const startDate = new Date(h.start || h.holiday_start_date);
                                // For loop boundary, use exclusive end or fallback to start+1
                                let limitDate;
                                
                                if (h.end || h.holiday_end_date) {
                                    limitDate = new Date(h.end || h.holiday_end_date);
                                    // If start == end in DB, it's 1 day. In loop logic, we need exclusive end.
                                    if (limitDate <= startDate) {
                                        limitDate = new Date(startDate);
                                        limitDate.setUTCDate(limitDate.getUTCDate() + 1);
                                    } else {
                                        // DB stores inclusive end date usually.
                                        limitDate.setUTCDate(limitDate.getUTCDate() + 1);
                                    }
                                } else {
                                    limitDate = new Date(startDate);
                                    limitDate.setUTCDate(limitDate.getUTCDate() + 1);
                                }

                                const loopDate = new Date(startDate);
                                
                                while (loopDate < limitDate) {
                                    const dateStr = loopDate.toISOString().split('T')[0];
                                    
                                    events.push({
                                        id: 'holiday-' + (h.id || Math.random()) + '-' + dateStr,
                                        title: h.name || h.holiday_name,
                                        start: dateStr,
                                        // Background events are always all-day by default in month view
                                        display: 'background',
                                        backgroundColor: 'rgba(255, 165, 0, 0.15)', // Fallback
                                        extendedProps: { 
                                            isVisual: true,
                                            customBg: h.bg || '' // Server now sends calculated RGBA in 'bg' or mapped color
                                        }
                                    });
                                    
                                    // Next Day
                                    loopDate.setUTCDate(loopDate.getUTCDate() + 1);
                                }
                            });
                            
                            successCallback(events);
                        }).catch(err => {
                            console.warn('Failed to fetch holidays', err);
                            successCallback([]);
                        });
                    }
                }
            ],
            
            // PATCH: Visual Styling & Click-Through Logic
            eventDidMount: (info) => {
                if (info.event.display === 'background') {
                    // 1. Apply Custom Backgrounds
                    const customBg = info.event.extendedProps.customBg;
                    if (customBg && customBg.trim() !== '') {
                        info.el.style.background = customBg;
                        if (customBg.includes('url(')) {
                            info.el.style.backgroundSize = 'cover';
                            info.el.style.backgroundPosition = 'center';
                        }
                    } else {
                        info.el.style.backgroundColor = info.event.backgroundColor;
                    }

                    // 2. CRITICAL FIX: Make the background overlay "invisible" to clicks
                    // This allows clicks to pass through to the date cell (for adding entries)
                    // or to any foreground events (Tabs) sitting on top.
                    info.el.style.pointerEvents = 'none'; 
                    info.el.style.zIndex = '0';
                }
            },

            // PATCH: Render Text Content for ALL events (including Backgrounds)
            eventContent: (arg) => {
                let div = document.createElement('div');
                div.className = 'fc-event-content-box'; 
                div.innerText = arg.event.title;

                if (arg.event.display === 'background') {
                    // Background Event Styling:
                    div.style.background = 'transparent';
                    // PATCH: Use Nextcloud Theme Variable
                    div.style.color = 'var(--color-main-text)';
                    div.style.opacity = '0.7'; 
                    
                    div.style.fontWeight = 'bold';
                    div.style.padding = '2px 5px';
                    div.style.textAlign = 'center';
                    div.style.pointerEvents = 'none'; // Ensure text doesn't block clicks either
                } else {
                    // Foreground Event Styling:
                    div.style.backgroundColor = arg.event.backgroundColor;
                }

                return { domNodes: [div] };
            },

            // Handle clicking events
            eventClick: (info) => {
                const props = info.event.extendedProps;

                // Should not trigger for background events due to pointer-events: none,
                // but kept for safety.
                if (props.isVisual || props.is_visual || info.event.display === 'background') {
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

            // Handle clicking empty dates (This will now fire even when clicking "through" a holiday)
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