<?php
return [
    'routes' => [
        // Pages
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#analysis', 'url' => '/analysis', 'verb' => 'GET'], // [NEW] Analysis Page
        ['name' => 'admin#index', 'url' => '/admin', 'verb' => 'GET'],

        // Time Entry API
        ['name' => 'timesheet#getAttributes', 'url' => '/api/attributes', 'verb' => 'GET'],
        ['name' => 'timesheet#getTimesheets', 'url' => '/api/timesheets', 'verb' => 'GET'],
        ['name' => 'timesheet#getTimesheet', 'url' => '/api/timesheets/{id}', 'verb' => 'GET'],
        ['name' => 'timesheet#saveTimesheet', 'url' => '/api/timesheets', 'verb' => 'POST'],
        ['name' => 'timesheet#getCounties', 'url' => '/api/counties/{stateAbbr}', 'verb' => 'GET'],

        // [UPDATED] Analysis API - Points to AnalysisController
        ['name' => 'analysis#getStats', 'url' => '/api/analysis/stats', 'verb' => 'GET'],

        // Admin API - Settings & Thumbnails
        ['name' => 'admin#getSettings', 'url' => '/api/admin/settings', 'verb' => 'GET'],
        ['name' => 'admin#saveSetting', 'url' => '/api/admin/settings', 'verb' => 'POST'],
        ['name' => 'admin#uploadThumbnail', 'url' => '/api/admin/thumbnails/{cardId}', 'verb' => 'POST'],
        ['name' => 'admin#getThumbnail', 'url' => '/api/admin/thumbnails/{filename}', 'verb' => 'GET'],

        // Admin API - Holidays
        ['name' => 'admin#getHolidays', 'url' => '/api/admin/holidays', 'verb' => 'GET'],
        ['name' => 'admin#saveHoliday', 'url' => '/api/admin/holidays', 'verb' => 'POST'],
        ['name' => 'admin#toggleHoliday', 'url' => '/api/admin/holidays/{id}/toggle', 'verb' => 'POST'],
        ['name' => 'admin#deleteHoliday', 'url' => '/api/admin/holidays/{id}', 'verb' => 'DELETE'],

        // Admin API - Jobs
        ['name' => 'admin#getJobs', 'url' => '/api/admin/jobs', 'verb' => 'GET'],
        ['name' => 'admin#saveJob', 'url' => '/api/admin/jobs', 'verb' => 'POST'],
        ['name' => 'admin#toggleJob', 'url' => '/api/admin/jobs/{id}/toggle', 'verb' => 'POST'],

        // Admin API - Locations
        ['name' => 'admin#getStates', 'url' => '/api/admin/states', 'verb' => 'GET'],
        ['name' => 'admin#toggleState', 'url' => '/api/admin/states/{id}/toggle', 'verb' => 'POST'],
        ['name' => 'admin#getCounties', 'url' => '/api/admin/counties/{stateAbbr}', 'verb' => 'GET'],
        ['name' => 'admin#toggleCounty', 'url' => '/api/admin/counties/{id}/toggle', 'verb' => 'POST'],

        // Admin API - Users
        ['name' => 'admin#getUsers', 'url' => '/api/admin/users', 'verb' => 'GET'],
        ['name' => 'admin#toggleUserStatus', 'url' => '/api/admin/users/toggle', 'verb' => 'POST'],
    ]
];