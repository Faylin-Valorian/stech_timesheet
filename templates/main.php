<?php
use OCP\Util;

// 1. Load FullCalendar JS (The CSS is built-in to this file for v6)
// This looks for: /apps/stech_timesheet/js/fullcalendar.global.min.js
Util::addScript('stech_timesheet', 'fullcalendar.global.min');

// 2. Load your custom script and style
Util::addScript('stech_timesheet', 'script');
Util::addStyle('stech_timesheet', 'style');
?>

<div id="app">
    <div id="app-content">
        <div id="app-content-wrapper">
            <div id="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>