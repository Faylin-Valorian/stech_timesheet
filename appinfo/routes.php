<?php
return [
    'routes' => [
        // --- Pages ---
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'admin#index', 'url' => '/admin', 'verb' => 'GET'],

        // --- Public API ---
        ['name' => 'timesheet#getAttributes', 'url' => '/api/attributes', 'verb' => 'GET'],
        ['name' => 'timesheet#getCounties', 'url' => '/api/counties/{stateAbbr}', 'verb' => 'GET'],
        ['name' => 'timesheet#getTimesheets', 'url' => '/api/list', 'verb' => 'GET'],
        ['name' => 'timesheet#getTimesheet', 'url' => '/api/get/{id}', 'verb' => 'GET'],
        ['name' => 'timesheet#saveTimesheet', 'url' => '/api/save', 'verb' => 'POST'],

        // --- Admin API ---
        ['name' => 'admin#getUsers', 'url' => '/api/admin/users', 'verb' => 'GET'],
        
        // Settings & Thumbnails
        ['name' => 'admin#getSettings', 'url' => '/api/admin/settings', 'verb' => 'GET'],
        ['name' => 'admin#saveSetting', 'url' => '/api/admin/settings', 'verb' => 'POST'],
        ['name' => 'admin#getThumbnail', 'url' => '/thumbnail/{filename}', 'verb' => 'GET'],
        ['name' => 'admin#uploadThumbnail', 'url' => '/api/admin/thumbnail/{cardId}', 'verb' => 'POST'],

        // Holidays
        ['name' => 'admin#getHolidays', 'url' => '/api/admin/holidays', 'verb' => 'GET'],
        ['name' => 'admin#saveHoliday', 'url' => '/api/admin/holidays', 'verb' => 'POST'],
        ['name' => 'admin#deleteHoliday', 'url' => '/api/admin/holidays/{id}', 'verb' => 'DELETE'],

        // Jobs
        ['name' => 'admin#saveJob', 'url' => '/api/admin/jobs', 'verb' => 'POST'],
        ['name' => 'admin#toggleJob', 'url' => '/api/admin/jobs/{id}/toggle', 'verb' => 'POST'],

        // Locations
        ['name' => 'admin#toggleState', 'url' => '/api/admin/states/{id}/toggle', 'verb' => 'POST'],
        ['name' => 'admin#toggleCounty', 'url' => '/api/admin/counties/{id}/toggle', 'verb' => 'POST'],
    ]
];