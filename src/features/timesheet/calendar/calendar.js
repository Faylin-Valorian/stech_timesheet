import { StechAPI } from '../../../api.js';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import { EntryForm } from '../entry/form.js';

export const CalendarFeature = {
    instance: null,
    archiveMode: 0, 

    init(el) {
        if (!el) return;

        this.instance = new Calendar(el, {
            plugins: [dayGridPlugin, interactionPlugin],
            initialView: 'dayGridMonth',
            firstDay: 0,
            headerToolbar: false,
            height: '100%',
            weekNumbers: true,
            
            eventSources: [
                // Source 1: Entries
                {
                    events: async (info, success, failure) => {
                        try {
                            const params = `start=${info.startStr}&end=${info.endStr}&archive=${this.archiveMode}`;
                            const data = await StechAPI.request('get', `/api/calendar/events?${params}`);
                            success(data);
                        } catch (e) { failure(e); }
                    }
                },
                // Source 2: Holidays
                {
                    events: async (info, success, failure) => {
                        try {
                            const data = await StechAPI.request('get', `/api/calendar/holidays?start=${info.startStr}&end=${info.endStr}`);
                            success(this.processHolidays(data));
                        } catch (e) { failure(e); }
                    }
                }
            ],

            // --- Custom Render Logic ---
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

            eventContent: (arg) => {
                let div = document.createElement('div');
                div.className = 'fc-event-content-box'; 
                div.innerText = arg.event.title;

                if (arg.event.display === 'background') {
                    // BACKGROUND (Holiday/Payroll)
                    div.style.background = 'transparent';
                    div.style.color = 'var(--color-text-maxcontrast)'; 
                    div.style.opacity = '0.85'; 
                    div.style.fontWeight = '800';
                    div.style.fontSize = '0.75em';
                    div.style.textTransform = 'uppercase';
                    div.style.letterSpacing = '0.5px';
                    div.style.paddingTop = '24px'; 
                    div.style.paddingLeft = '6px';
                    div.style.textAlign = 'left';
                    div.style.pointerEvents = 'none'; 
                } else {
                    // FOREGROUND (Timesheet)
                    div.style.backgroundColor = arg.event.backgroundColor;
                    div.style.padding = '2px 4px';
                }
                return { domNodes: [div] };
            },

            dateClick: (info) => EntryForm.open(info.dateStr, null),
            
            eventClick: (info) => {
                const props = info.event.extendedProps;
                if (props.isVisual || info.event.display === 'background') return;
                EntryForm.open(info.event.startStr, info.event.id);
            },

            datesSet: (info) => {
                const el = document.getElementById('current-date-label');
                if(el) el.innerText = info.view.title;
            },
            
            windowResize: () => this.instance.render()
        });

        this.instance.render();
        this.setupControls();
    },

    setupControls() {
        document.getElementById('nav-prev')?.addEventListener('click', () => this.instance.prev());
        document.getElementById('nav-next')?.addEventListener('click', () => this.instance.next());
        document.getElementById('view-month')?.addEventListener('click', (e) => { this.instance.changeView('dayGridMonth'); this.setActive(e.target); });
        document.getElementById('view-week')?.addEventListener('click', (e) => { this.instance.changeView('dayGridWeek'); this.setActive(e.target); });
        document.getElementById('view-today')?.addEventListener('click', () => this.instance.today());

        const archiveBtn = document.getElementById('toggle-archive-view');
        if (archiveBtn) {
            archiveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.archiveMode = (this.archiveMode === 0) ? 1 : 0;
                
                const spanText = archiveBtn.querySelector('span:not([class*="icon"])');
                const spanIcon = archiveBtn.querySelector('span[class*="icon"]');
                
                if (this.archiveMode === 1) {
                    archiveBtn.classList.add('active', 'primary-button');
                    archiveBtn.classList.remove('secondary-button');
                    if(spanText) spanText.innerText = "Back to Active";
                    if(spanIcon) spanIcon.className = 'icon-history';
                } else {
                    archiveBtn.classList.remove('active', 'primary-button');
                    archiveBtn.classList.add('secondary-button');
                    if(spanText) spanText.innerText = "Show Archived";
                    if(spanIcon) spanIcon.className = 'icon-filter';
                }
                this.refresh();
            });
        }
    },

    setActive(btn) {
        document.querySelectorAll('.view-buttons button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    },

    refresh() { if(this.instance) this.instance.refetchEvents(); },

    processHolidays(data) {
        const events = [];
        data.forEach(h => {
            const startDate = new Date(h.holiday_start_date);
            let limitDate = h.holiday_end_date ? new Date(h.holiday_end_date) : new Date(startDate);
            limitDate.setUTCDate(limitDate.getUTCDate() + 1);

            const rawColor = h.holiday_bg || '#e67e22';
            const overlayColor = this.hexToRgba(rawColor, 0.2);

            let loop = new Date(startDate);
            while (loop < limitDate) {
                events.push({
                    id: `holiday-${h.id}-${loop.toISOString()}`,
                    title: h.holiday_name,
                    start: loop.toISOString().split('T')[0],
                    display: 'background',
                    backgroundColor: overlayColor,
                    extendedProps: { isVisual: true }
                });
                loop.setUTCDate(loop.getUTCDate() + 1);
            }
        });
        return events;
    },

    hexToRgba(hex, opacity) {
        if (!hex) return `rgba(230, 126, 34, ${opacity})`;
        if(hex[0] == '#') hex = hex.substring(1);
        let r, g, b;
        if(hex.length === 6) {
            r = parseInt(hex.substring(0,2), 16);
            g = parseInt(hex.substring(2,4), 16);
            b = parseInt(hex.substring(4,6), 16);
        } else { return `rgba(0,0,0,${opacity})`; }
        return `rgba(${r},${g},${b},${opacity})`;
    }
};