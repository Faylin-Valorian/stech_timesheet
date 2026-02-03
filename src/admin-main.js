import { UserAdmin } from './admin/users.js';
import { AccessAdmin } from './admin/access.js';
import { PayrollAdmin } from './admin/payroll.js';
import { HolidayAdmin } from './admin/holidays.js';
import { JobAdmin } from './admin/jobs.js';
import { LocationAdmin } from './admin/locations.js';

/**
 * Entry Point for Admin Panel
 * Orchestrates navigation and event listeners for all admin modules.
 */
document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. Setup View Switching Navigation ---
    const navItems = ['users', 'access', 'payroll', 'holidays', 'jobs', 'locations'];
    navItems.forEach(viewId => {
        document.getElementById('nav-' + viewId)?.addEventListener('click', () => switchAdminView(viewId));
    });

    // --- 2. Global Event Listeners for Forms and Search ---
    
    // User Management
    document.getElementById('user-search-input')?.addEventListener('input', () => UserAdmin.render());
    
    // Access Control
    // Toggles inside AccessAdmin handle their own 'change' events during render

    // Payroll Settings
    document.getElementById('btn-save-payroll')?.addEventListener('click', () => PayrollAdmin.save());
    
    // Holiday Management
    document.getElementById('form-holiday')?.addEventListener('submit', (e) => HolidayAdmin.submit(e));
    document.getElementById('holiday-search-input')?.addEventListener('input', () => HolidayAdmin.render());
    document.getElementById('btn-cancel-holiday')?.addEventListener('click', () => HolidayAdmin.resetForm());

    // Job Management
    document.getElementById('form-job')?.addEventListener('submit', (e) => JobAdmin.submit(e));
    document.getElementById('job-search-input')?.addEventListener('input', () => JobAdmin.render());
    document.getElementById('btn-cancel-job')?.addEventListener('click', () => JobAdmin.resetForm());

    // Location Management
    document.getElementById('state-search-input')?.addEventListener('input', () => LocationAdmin.renderStates());
    document.getElementById('county-search-input')?.addEventListener('input', () => LocationAdmin.renderCounties());

    // --- 3. View Switcher Logic ---
    function switchAdminView(viewId) {
        // UI: Toggle visibility
        document.querySelectorAll('.admin-view').forEach(el => el.classList.add('hidden'));
        document.getElementById('view-' + viewId)?.classList.remove('hidden');
        
        // UI: Toggle active nav state
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        document.getElementById('nav-' + viewId)?.classList.add('active');

        // Logic: Trigger specific module loaders
        const loaders = {
            users: () => UserAdmin.load(),
            access: () => AccessAdmin.load(),
            payroll: () => PayrollAdmin.load(),
            holidays: () => HolidayAdmin.load(),
            jobs: () => JobAdmin.load(),
            locations: () => LocationAdmin.loadStates()
        };
        
        if (loaders[viewId]) {
            loaders[viewId]();
        }
    }

    // Default view to load on entry
    switchAdminView('users');
});