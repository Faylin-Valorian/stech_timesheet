<?php
use OCP\Util;

Util::addScript('stech_timesheet', 'script');
Util::addStyle('stech_timesheet', 'style');
?>
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js' nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()); ?>"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<div id="app">
    <div id="app-navigation">
        <ul class="with-icon">
            <li>
                <a href="#" class="active">
                    <span class="icon icon-calendar-dark"></span>
                    <span>Calendar</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <span class="icon icon-settings-dark"></span>
                    <span>Settings</span>
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