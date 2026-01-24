<?php
use OCP\Util;

// Load Dependencies
Util::addScript('stech_timesheet', 'fullcalendar.global.min');
Util::addScript('stech_timesheet', 'script');
Util::addStyle('stech_timesheet', 'style');
?>

<div id="app">
    <div id="app-navigation">
        <ul class="with-icon">
            
            <li class="nav-section-header">
                <div class="date-controls">
                    <button id="nav-prev" class="icon-action" title="Previous"></button>
                    
                    <div id="date-selector-container">
                        <span id="current-date-label">Loading...</span>
                        <input type="month" id="date-picker-input">
                    </div>

                    <button id="nav-next" class="icon-action" title="Next"></button>
                </div>
            </li>

            <li class="nav-section-views">
                <div class="view-buttons">
                    <button id="view-month" class="primary-button active">Month</button>
                    <button id="view-week" class="primary-button">Week</button>
                    <button id="view-today" class="secondary-button">Today</button>
                </div>
            </li>

            <div class="app-navigation-separator"></div>

            <li class="nav-item">
                <a class="nav-link active" href="#">
                    <span class="icon-history"></span>
                    <span>Timesheet</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <span class="icon-settings-dark"></span>
                    <span>Admin Panel</span>
                </a>
            </li>

        </ul>
    </div>

    <div id="app-content">
        <div id="app-content-wrapper">
            <div id="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>