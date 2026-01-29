<?php
return [
    'routes' => [
        // Page Route
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // API Routes (Must match Controller Method Names exactly!)
        ['name' => 'timesheet#getAttributes', 'url' => '/api/attributes', 'verb' => 'GET'],
        ['name' => 'timesheet#getCounties', 'url' => '/api/counties/{stateAbbr}', 'verb' => 'GET'],
        ['name' => 'timesheet#getTimesheets', 'url' => '/api/list', 'verb' => 'GET'],
        
        // This was missing in your file but required for the "Edit" feature
        ['name' => 'timesheet#getTimesheet', 'url' => '/api/get/{id}', 'verb' => 'GET'],
        
        ['name' => 'timesheet#saveTimesheet', 'url' => '/api/save', 'verb' => 'POST'],
    ]
];