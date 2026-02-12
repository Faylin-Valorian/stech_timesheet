import './timesheet.scss';
import { CalendarFeature } from './features/timesheet/calendar/calendar.js';
import { EntryForm } from './features/timesheet/entry/form.js';
import { generateUrl } from '@nextcloud/router'; // Ensure this matches your original imports

document.addEventListener('DOMContentLoaded', () => {
    // --- 1. PERSISTENT IMPERSONATION CHECK (Restored) ---
    const storedTarget = sessionStorage.getItem('stech_impersonate');
    const urlParams = new URLSearchParams(window.location.search);
    const currentTarget = urlParams.get('target_user');

    if (storedTarget && storedTarget !== currentTarget) {
        urlParams.set('target_user', storedTarget);
        window.location.search = urlParams.toString();
        return; 
    }
    
    document.getElementById('btn-end-impersonation')?.addEventListener('click', () => {
        sessionStorage.removeItem('stech_impersonate');
        const url = new URL(window.location.href);
        url.searchParams.delete('target_user');
        window.location.href = url.toString();
    });
    // ----------------------------------------------------

    // 2. Initialize Features
    EntryForm.init();
    CalendarFeature.init(document.getElementById('calendar'));
    
    // 3. Navigation Links (Preserved)
    document.querySelectorAll('#app-navigation a').forEach(link => {
        const text = link.innerText.toLowerCase();
        if (text.includes('admin') || text.includes('analysis')) {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                // Assumes Nextcloud routing standard
                const route = text.includes('admin') ? '/apps/stech_timesheet/admin' : '/apps/stech_timesheet/analysis';
                window.location.href = generateUrl ? generateUrl(route) : route;
            });
        }
    });
});