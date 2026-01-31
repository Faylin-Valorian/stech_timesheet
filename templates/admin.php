<?php
use OCP\Util;
Util::addScript('stech_timesheet', 'admin');
Util::addStyle('stech_timesheet', 'style'); 
Util::addStyle('stech_timesheet', 'admin'); 
?>

<div id="app">
    <div id="app-navigation">
        <ul class="with-icon">
            <li class="nav-item">
                <a class="nav-link" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.page.index')); ?>">
                    <span class="icon-history"></span>
                    <span>Back to Timesheet</span>
                </a>
            </li>
            <div class="app-navigation-separator"></div>
            <li class="nav-section-header"><span>Administration</span></li>
            
            <li class="nav-item">
                <a class="nav-link active" href="#" id="nav-users">
                    <span class="icon-user"></span><span>User Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" id="nav-holidays">
                    <span class="icon-calendar-dark"></span><span>Holidays</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" id="nav-jobs">
                    <span class="icon-category-office"></span><span>Job Codes</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" id="nav-locations">
                    <span class="icon-address"></span><span>Locations</span>
                </a>
            </li>
        </ul>
    </div>

    <div id="app-content">
        
        <div id="view-users" class="admin-view">
            <div class="view-header"><h2>User Management</h2><p>Search for an employee to view their timesheet.</p></div>
            <div class="view-body">
                <div class="form-section max-width-600">
                    <label>Select Employee</label>
                    <div class="searchable-select-wrapper">
                        <input type="text" id="user-search" class="form-control" placeholder="Type name..." autocomplete="off">
                        <div id="user-dropdown-list" class="custom-dropdown-list hidden"></div>
                        <input type="hidden" id="selected-user-uid">
                    </div>
                    <div style="margin-top:20px;">
                        <button id="btn-view-user" class="primary-button" disabled>Open Timesheet Calendar</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="view-holidays" class="admin-view hidden">
            <div class="view-header"><h2>Holiday Management</h2></div>
            <div class="view-body split-layout">
                <div class="split-panel left">
                    <h3 class="panel-title">Add Holiday</h3>
                    <form id="form-holiday">
                        <div class="input-group"><label>Name</label><input type="text" id="holiday-name" required class="form-control"></div>
                        <div class="input-group"><label>Start</label><input type="date" id="holiday-start" required class="form-control"></div>
                        <div class="input-group"><label>End</label><input type="date" id="holiday-end" required class="form-control"></div>
                        <button type="submit" class="primary-button full-width">Add Holiday</button>
                    </form>
                </div>
                <div class="split-panel right">
                    <h3 class="panel-title">Upcoming Holidays</h3>
                    <div class="scroll-list" id="holiday-list"></div>
                </div>
            </div>
        </div>

        <div id="view-jobs" class="admin-view hidden">
            <div class="view-header"><h2>Job Codes</h2></div>
            <div class="view-body split-layout">
                <div class="split-panel left">
                    <h3 class="panel-title">Create Job</h3>
                    <form id="form-job">
                        <div class="input-group"><label>Name / Code</label><input type="text" id="job-name" required class="form-control"></div>
                        <div class="input-group"><label>Description</label><textarea id="job-desc" class="form-control" rows="3"></textarea></div>
                        <button type="submit" class="primary-button full-width">Create Job</button>
                    </form>
                </div>
                <div class="split-panel right">
                    <div class="panel-header-row">
                        <h3 class="panel-title">Job List</h3>
                        <div class="filter-controls">
                            <input type="text" id="job-search-input" class="filter-input" placeholder="Search...">
                            <div class="radio-group">
                                <label><input type="radio" name="job-status" value="active" checked> Active</label>
                                <label><input type="radio" name="job-status" value="archived"> Archived</label>
                                <label><input type="radio" name="job-status" value="all"> All</label>
                            </div>
                        </div>
                    </div>
                    <div class="scroll-list" id="job-list"></div>
                </div>
            </div>
        </div>

        <div id="view-locations" class="admin-view hidden">
            <div class="view-header"><h2>Location Settings</h2></div>
            <div class="view-body split-layout">
                <div class="split-panel left">
                    <div class="panel-header-row">
                        <h3 class="panel-title">States</h3>
                        <input type="text" id="state-search-input" class="filter-input" placeholder="Filter states...">
                    </div>
                    <div class="scroll-list" id="state-list"></div>
                </div>
                <div class="split-panel right">
                    <div class="panel-header-row">
                        <h3 class="panel-title" id="county-header">Counties</h3>
                        <input type="text" id="county-search-input" class="filter-input" placeholder="Filter counties..." disabled>
                    </div>
                    <div class="scroll-list" id="county-list">
                        <div class="empty-msg">Select a state to view counties.</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>