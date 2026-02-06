import { UserAdmin } from './admin/users.js';
import { AccessAdmin } from './admin/access.js';
import { PayrollAdmin } from './admin/payroll.js';
import { HolidayAdmin } from './admin/holidays.js';
import { JobAdmin } from './admin/jobs.js';
import { LocationAdmin } from './admin/locations.js';

document.addEventListener('DOMContentLoaded', () => {
    
    // --- CRITICAL PATCH: CLEAR IMPERSONATION ---
    // Ensure we stop impersonating users when entering the Admin Panel
    sessionStorage.removeItem('stech_impersonate');

    // --- 1. Navigation ---
    const navItems = ['users', 'access', 'payroll', 'holidays', 'jobs', 'locations'];
    navItems.forEach(viewId => {
        document.getElementById('nav-' + viewId)?.addEventListener('click', (e) => {
            // PATCH: Prevent default anchor behavior (stops # in URL)
            e.preventDefault();
            switchAdminView(viewId);
        });
    });

    // --- 2. Filter Menus ---
    const filterPairs = [
        { btn: 'user-filter-btn', menu: 'user-filter-menu' },
        { btn: 'holiday-filter-btn', menu: 'holiday-filter-menu' },
        { btn: 'job-filter-btn', menu: 'job-filter-menu' },
        { btn: 'state-filter-btn', menu: 'state-filter-menu' },
        { btn: 'county-filter-btn', menu: 'county-filter-menu' }
    ];

    filterPairs.forEach(pair => {
        const btn = document.getElementById(pair.btn);
        const menu = document.getElementById(pair.menu);
        if (btn && menu) {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });
        }
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('.filter-menu').forEach(menu => menu.classList.add('hidden'));
    });

    // --- 3. Filter Radio Listeners ---
    const filterNames = ['user-status', 'holiday-status', 'job-status', 'state-status', 'county-status'];
    filterNames.forEach(name => {
        document.querySelectorAll(`input[name="${name}"]`).forEach(radio => {
            radio.addEventListener('change', () => {
                if (name.includes('user')) UserAdmin.render();
                else if (name.includes('holiday')) HolidayAdmin.render();
                else if (name.includes('job')) JobAdmin.render();
                else if (name.includes('state')) LocationAdmin.renderStates();
                else if (name.includes('county')) LocationAdmin.renderCounties();
            });
        });
    });

    // --- 4. Access Control Tabs ---
    document.querySelectorAll('.access-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.access-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const targetId = tab.getAttribute('data-target');
            document.querySelectorAll('.access-group-panel').forEach(panel => panel.classList.add('hidden'));
            document.getElementById(targetId)?.classList.remove('hidden');
        });
    });

    // --- 5. Forms & Search ---
    document.getElementById('user-search-input')?.addEventListener('input', () => UserAdmin.render());
    document.getElementById('btn-save-payroll')?.addEventListener('click', () => PayrollAdmin.save());
    document.getElementById('form-holiday')?.addEventListener('submit', (e) => HolidayAdmin.submit(e));
    document.getElementById('holiday-search-input')?.addEventListener('input', () => HolidayAdmin.render());
    document.getElementById('btn-cancel-holiday')?.addEventListener('click', () => HolidayAdmin.resetForm());
    document.getElementById('form-job')?.addEventListener('submit', (e) => JobAdmin.submit(e));
    document.getElementById('job-search-input')?.addEventListener('input', () => JobAdmin.render());
    document.getElementById('btn-cancel-job')?.addEventListener('click', () => JobAdmin.resetForm());
    document.getElementById('state-search-input')?.addEventListener('input', () => LocationAdmin.renderStates());
    document.getElementById('county-search-input')?.addEventListener('input', () => LocationAdmin.renderCounties());

    function switchAdminView(viewId) {
        document.querySelectorAll('.admin-view').forEach(el => el.classList.add('hidden'));
        document.getElementById('view-' + viewId)?.classList.remove('hidden');
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        document.getElementById('nav-' + viewId)?.classList.add('active');

        const loaders = {
            users: () => UserAdmin.load(),
            access: () => AccessAdmin.load(),
            payroll: () => PayrollAdmin.load(),
            holidays: () => HolidayAdmin.load(),
            jobs: () => JobAdmin.load(),
            locations: () => LocationAdmin.loadStates()
        };
        if (loaders[viewId]) loaders[viewId]();
    }

    switchAdminView('users');
});