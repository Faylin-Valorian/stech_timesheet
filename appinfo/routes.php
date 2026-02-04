<?php
return [
    'routes' => [
        // Page Routes
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#admin_page', 'url' => '/admin', 'verb' => 'GET'],
        ['name' => 'page#analysis_page', 'url' => '/analysis', 'verb' => 'GET'],

        // API: Attributes
        ['name' => 'timesheet#getAttributes', 'url' => '/api/attributes', 'verb' => 'GET'],
        ['name' => 'timesheet#getCounties', 'url' => '/api/counties/{stateAbbr}', 'verb' => 'GET'],

        // API: Timesheets
        ['name' => 'timesheet#getTimesheets', 'url' => '/api/timesheets', 'verb' => 'GET'],
        ['name' => 'timesheet#saveTimesheet', 'url' => '/api/timesheets', 'verb' => 'POST'],
        ['name' => 'timesheet#getTimesheet', 'url' => '/api/timesheets/{id}', 'verb' => 'GET'],
        
        // FIX: Explicitly allow DELETE verb for this route
        ['name' => 'timesheet#deleteTimesheet', 'url' => '/api/timesheets/{id}', 'verb' => 'DELETE'],

        // API: Analysis
        ['name' => 'analysis#getFilters', 'url' => '/api/analysis/filters', 'verb' => 'GET'],
        ['name' => 'analysis#getStats', 'url' => '/api/analysis/stats', 'verb' => 'GET'],

        // API: Admin - Users & Groups
        ['name' => 'admin#getUsers', 'url' => '/api/admin/users', 'verb' => 'GET'],
        ['name' => 'admin#toggleUser', 'url' => '/api/admin/users/toggle', 'verb' => 'POST'],
        ['name' => 'admin#getGroups', 'url' => '/api/admin/groups', 'verb' => 'GET'],
        ['name' => 'admin#getAccess', 'url' => '/api/admin/access', 'verb' => 'GET'],
        ['name' => 'admin#saveAccess', 'url' => '/api/admin/access', 'verb' => 'POST'],

        // API: Admin - Settings
        ['name' => 'admin#getSettings', 'url' => '/api/admin/settings', 'verb' => 'GET'],
        ['name' => 'admin#saveSetting', 'url' => '/api/admin/settings', 'verb' => 'POST'],

        // API: Admin - Holidays
        ['name' => 'admin#getHolidays', 'url' => '/api/admin/holidays', 'verb' => 'GET'],
        ['name' => 'admin#saveHoliday', 'url' => '/api/admin/holidays', 'verb' => 'POST'],
        ['name' => 'admin#toggleHoliday', 'url' => '/api/admin/holidays/{id}/toggle', 'verb' => 'POST'],

        // API: Admin - Jobs
        ['name' => 'admin#getJobs', 'url' => '/api/admin/jobs', 'verb' => 'GET'],
        ['name' => 'admin#saveJob', 'url' => '/api/admin/jobs', 'verb' => 'POST'],
        ['name' => 'admin#toggleJob', 'url' => '/api/admin/jobs/{id}/toggle', 'verb' => 'POST'],

        // API: Admin - States & Counties
        ['name' => 'admin#getStates', 'url' => '/api/admin/states', 'verb' => 'GET'],
        ['name' => 'admin#toggleState', 'url' => '/api/admin/states/{id}/toggle', 'verb' => 'POST'],
        ['name' => 'admin#getCounties', 'url' => '/api/admin/counties/{abbr}', 'verb' => 'GET'],
        ['name' => 'admin#toggleCounty', 'url' => '/api/admin/counties/{id}/toggle', 'verb' => 'POST'],
    ]
];