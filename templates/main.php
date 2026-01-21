<?php
use OCP\Util;

// Load our custom script and style
Util::addScript('stech_timesheet', 'script');
Util::addStyle('stech_timesheet', 'style');

// Load FullCalendar dependencies (using CDN for "start small" phase)
?>
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<div id="app">
    <div id="app-content">
        <div id="app-content-wrapper">
            <div id="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>