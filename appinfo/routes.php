<?php
declare(strict_types=1);

return [
    'routes' => [
        // =====================================================================
        //  PAGES (Views)
        // =====================================================================
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#analysis', 'url' => '/analysis', 'verb' => 'GET'],
        ['name' => 'admin#index', 'url' => '/admin', 'verb' => 'GET'],

        // =====================================================================
        //  TIMESHEET API
        // =====================================================================
        ['name' => 'timesheet#getAttributes', 'url' => '/api/attributes', 'verb' => 'GET'],
        ['name' => 'timesheet#getCounties', 'url' => '/api/counties/{stateAbbr}', 'verb' => 'GET'],
        ['name' => 'timesheet#getTimesheets', 'url' => '/api/timesheets', 'verb' => 'GET'],
        ['name' => 'timesheet#getTimesheet', 'url' => '/api/timesheets/{id}', 'verb' => 'GET'],
        ['name' => 'timesheet#saveTimesheet', 'url' => '/api/timesheets', 'verb' => 'POST'],

        // =====================================================================
        //  ANALYSIS API
        // =====================================================================
        ['name' => 'analysis#getStats', 'url' => '/api/analysis/stats', 'verb' => 'GET'],
        // [NEW] Filters for Dropdowns (Users, Jobs, States)
        ['name' => 'analysis#getFilters', 'url' => '/api/analysis/filters', 'verb' => 'GET'],

        // =====================================================================
        //  ADMIN API
        // =====================================================================
        ['name' => 'admin#getSettings', 'url' => '/api/admin/settings', 'verb' => 'GET'],
        ['name' => 'admin#saveSetting', 'url' => '/api/admin/settings', 'verb' => 'POST'],

        ['name' => 'admin#getSystemGroups', 'url' => '/api/admin/groups', 'verb' => 'GET'],
        ['name' => 'admin#getAccessRules', 'url' => '/api/admin/access', 'verb' => 'GET'],
        ['name' => 'admin#saveAccessRule', 'url' => '/api/admin/access', 'verb' => 'POST'],

        ['name' => 'admin#getUsers', 'url' => '/api/admin/users', 'verb' => 'GET'],
        ['name' => 'admin#toggleUserStatus', 'url' => '/api/admin/users/toggle', 'verb' => 'POST'],

        ['name' => 'admin#getHolidays', 'url' => '/api/admin/holidays', 'verb' => 'GET'],
        ['name' => 'admin#saveHoliday', 'url' => '/api/admin/holidays', 'verb' => 'POST'],
        ['name' => 'admin#toggleHoliday', 'url' => '/api/admin/holidays/{id}/toggle', 'verb' => 'POST'],
        ['name' => 'admin#deleteHoliday', 'url' => '/api/admin/holidays/{id}', 'verb' => 'DELETE'],

        ['name' => 'admin#getJobs', 'url' => '/api/admin/jobs', 'verb' => 'GET'],
        ['name' => 'admin#saveJob', 'url' => '/api/admin/jobs', 'verb' => 'POST'],
        ['name' => 'admin#toggleJob', 'url' => '/api/admin/jobs/{id}/toggle', 'verb' => 'POST'],

        ['name' => 'admin#getStates', 'url' => '/api/admin/states', 'verb' => 'GET'],
        ['name' => 'admin#getCounties', 'url' => '/api/admin/counties/{stateAbbr}', 'verb' => 'GET'],
        ['name' => 'admin#toggleState', 'url' => '/api/admin/states/{id}/toggle', 'verb' => 'POST'],
        ['name' => 'admin#toggleCounty', 'url' => '/api/admin/counties/{id}/toggle', 'verb' => 'POST'],

        ['name' => 'admin#getThumbnail', 'url' => '/img/thumb/{filename}', 'verb' => 'GET'],
        ['name' => 'admin#uploadThumbnail', 'url' => '/api/admin/thumbnail/{cardId}', 'verb' => 'POST'],
    ]
];