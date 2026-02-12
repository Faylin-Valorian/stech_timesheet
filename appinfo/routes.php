<?php
return [
    'routes' => [
        // Ensure this line exists exactly like this:
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        
        ['name' => 'page#analysis', 'url' => '/analysis', 'verb' => 'GET'],
        ['name' => 'page#admin',    'url' => '/admin',    'verb' => 'GET'],
        
        // ... (rest of your API routes)
        ['name' => 'calendar#get_events',   'url' => '/api/calendar/events',   'verb' => 'GET'],
        ['name' => 'calendar#get_holidays', 'url' => '/api/calendar/holidays', 'verb' => 'GET'],
        ['name' => 'entry#get_attributes', 'url' => '/api/entry/attributes',       'verb' => 'GET'],
        ['name' => 'entry#get_counties',   'url' => '/api/entry/counties/{stateAbbr}', 'verb' => 'GET'],
        ['name' => 'entry#get_entry',      'url' => '/api/entry/{id}',             'verb' => 'GET'],
        ['name' => 'entry#save_entry',     'url' => '/api/entry/save',             'verb' => 'POST'],
        ['name' => 'entry#delete_entry',   'url' => '/api/entry/{id}/delete',      'verb' => 'POST'],
        ['name' => 'entry#restore_entry',  'url' => '/api/entry/{id}/restore',     'verb' => 'POST'],
        ['name' => 'dashboard#get_data', 'url' => '/api/analysis/data', 'verb' => 'GET'],
        ['name' => 'payroll#get_periods', 'url' => '/api/admin/payroll',      'verb' => 'GET'],
        ['name' => 'holiday#get_all',     'url' => '/api/admin/holidays',     'verb' => 'GET'],
        ['name' => 'location#get_all',    'url' => '/api/admin/locations',    'verb' => 'GET'],
        ['name' => 'admin_job#get_all',   'url' => '/api/admin/jobs',         'verb' => 'GET'],
        ['name' => 'user#get_all',        'url' => '/api/admin/users',        'verb' => 'GET'],
    ]
];