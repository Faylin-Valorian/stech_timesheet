<?php
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        
        // Debug Route (NEW)
        ['name' => 'timesheet#check_db', 'url' => '/api/debug', 'verb' => 'GET'],

        // API Routes
        ['name' => 'timesheet#get_attributes', 'url' => '/api/attributes', 'verb' => 'GET'],
        ['name' => 'timesheet#get_counties', 'url' => '/api/counties/{stateAbbr}', 'verb' => 'GET'],
        ['name' => 'timesheet#save_timesheet', 'url' => '/api/save', 'verb' => 'POST'],
        ['name' => 'timesheet#get_timesheets', 'url' => '/api/list', 'verb' => 'GET'],
    ]
];