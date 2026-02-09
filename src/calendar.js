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
        this.instance = new FullCalendar(el, {
            plugins: [dayGridPlugin, interactionPlugin],
            initialView: 'dayGridMonth',
            firstDay: 0, // Sunday
            headerToolbar: false, 
            height: '100%',
            weekNumbers: true,
            
            eventSources: [
                // SOURCE 1: Timesheets (Editable Data & Payroll Markers)
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
                                // 1. Calculate Date Range
                                const startDate = new Date(h.start || h.holiday_start_date);
                                let limitDate;
                                
                                if (h.end || h.holiday_end_date) {
                                    limitDate = new Date(h.end || h.holiday_end_date);
                                    if (limitDate <= startDate) {
                                        limitDate = new Date(startDate);
                                        limitDate.setUTCDate(limitDate.getUTCDate() + 1);
                                    } else {
                                        limitDate.setUTCDate(limitDate.getUTCDate() + 1);
                                    }
                                } else {
                                    limitDate = new Date(startDate);
                                    limitDate.setUTCDate(limitDate.getUTCDate() + 1);
                                }

                                // 2. Determine Color (Hex -> RGBA)
                                const rawColor = h.holiday_bg || h.bg || '#e67e22';
                                const overlayColor = this.hexToRgba(rawColor, 0.2);

                                // 3. Generate Daily Blocks
                                const loopDate = new Date(startDate);
                                while (loopDate < limitDate) {
                                    const dateStr = loopDate.toISOString().split('T')[0];
                                    
                                    events.push({
                                        id: 'holiday-' + (h.id || Math.random()) + '-' + dateStr,
                                        title: h.name || h.holiday_name,
                                        start: dateStr,
                                        display: 'background',
                                        backgroundColor: overlayColor, 
                                        extendedProps: { 
                                            isVisual: true,
                                            customBg: '' 
                                        }
                                    });
                                    
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

                    info.el.style.pointerEvents = 'none'; 
                    info.el.style.zIndex = '0';
                }
            },

            // PATCH: Render Text Content - FIXING THE OVERLAP HERE
            eventContent: (arg) => {
                let div = document.createElement('div');
                div.className = 'fc-event-content-box'; 
                div.innerText = arg.event.title;

                if (arg.event.display === 'background') {
                    // --- BACKGROUND EVENT (Holiday/Payroll) ---
                    div.style.background = 'transparent';
                    // Force text color for better visibility in FullCalendar
                    div.style.color = 'var(--color-text-maxcontrast)'; 
                    div.style.opacity = '0.85'; 
                    
                    // FONT STYLING
                    div.style.fontWeight = '800';
                    div.style.fontSize = '0.75em';
                    div.style.textTransform = 'uppercase';
                    div.style.letterSpacing = '0.5px';

                    // THE FIX: Push text down 24px to clear the date number
                    div.style.paddingTop = '24px'; 
                    div.style.paddingLeft = '6px';
                    div.style.textAlign = 'left';
                    
                    div.style.pointerEvents = 'none'; 
                } else {
                    // --- FOREGROUND EVENT (Timesheet) ---
                    div.style.backgroundColor = arg.event.backgroundColor;
                    // Standard padding for normal events
                    div.style.padding = '2px 4px';
                }

                return { domNodes: [div] };
            },

            // Handle clicking events
            eventClick: (info) => {
                const props = info.event.extendedProps;
                if (props.isVisual || props.is_visual || info.event.display === 'background') return;

                window.StechTimesheet.API.getTimesheetDetails(info.event.id)
                    .then(data => {
                        if (window.StechTimesheet.Form) {
                            window.StechTimesheet.Form.open(data.timesheet_date, data.timesheet_id);
                        }
                    })
                    .catch(err => console.error('Failed to load record details:', err));
            },

            // FIX: Reset form logic added here
            dateClick: (info) => {
                // RESET FORM: Clear any previous data (like time from an old entry)
                const formEl = document.getElementById('timesheet-form');
                if (formEl) {
                    formEl.reset(); // Standard reset (reverts selects to default --)
                    
                    // Extra safety: Clear hidden time inputs that dropdowns populate
                    const hiddenTimeInputs = formEl.querySelectorAll('.combined-time-input');
                    hiddenTimeInputs.forEach(input => input.value = '');
                }

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
        
        // FIX: Hook up the PHP-rendered filter button (if it exists)
        this.setupArchiveFilter();
    },

    hexToRgba(hex, opacity) {
        if (!hex) return `rgba(230, 126, 34, ${opacity})`; 
        
        let c;
        if(/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)){
            c = hex.substring(1).split('');
            if(c.length === 3){
                c = [c[0], c[0], c[1], c[1], c[2], c[2]];
            }
            c = '0x'+c.join('');
            return 'rgba('+[(c>>16)&255, (c>>8)&255, c&255].join(',')+','+opacity+')';
        }
        return hex;
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

    // FIX: Secure Filter Logic (Updated for Text Button)
    setupArchiveFilter() {
        const btn = document.getElementById('toggle-archive-view');
        if (btn) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Toggle State
                this.archiveMode = (this.archiveMode === 0) ? 1 : 0;
                
                // Visual Update: Dynamic Text/Icon Swapping
                const textSpan = btn.querySelector('span:not([class*="icon"])');
                const iconSpan = btn.querySelector('span[class*="icon"]');
                
                if (this.archiveMode === 1) {
                    btn.classList.add('active'); 
                    btn.classList.remove('secondary-button');
                    btn.classList.add('primary-button'); // Highlight active state
                    
                    if(textSpan) textSpan.innerText = "Back to Active";
                    if(iconSpan) {
                        iconSpan.classList.remove('icon-filter');
                        iconSpan.classList.add('icon-history'); 
                    }
                } else {
                    btn.classList.remove('active');
                    btn.classList.remove('primary-button');
                    btn.classList.add('secondary-button'); // Revert to secondary
                    
                    if(textSpan) textSpan.innerText = "Show Archived";
                    if(iconSpan) {
                        iconSpan.classList.remove('icon-history');
                        iconSpan.classList.add('icon-filter');
                    }
                }
                
                // Refresh Calendar Data
                this.refresh();
            });
        }
    },

    toggleActiveButton(activeBtn) {
        document.querySelectorAll('.view-buttons button').forEach(btn => btn.classList.remove('active'));
        activeBtn.classList.add('active');
    },

    refresh() {
        this.instance.refetchEvents();
    }
};

export const Calendar = TimesheetCalendar;