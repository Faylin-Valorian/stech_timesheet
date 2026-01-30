<?php
use OCP\Util;
Util::addScript('stech_timesheet', 'admin');
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
        </ul>
    </div>

    <div id="app-content" class="admin-dashboard-container">
        
        <div class="dashboard-header">
            <h2>Administration</h2>
            <p>Manage system settings, users, and configuration.</p>
        </div>

        <div class="dashboard-grid">
            
            <div class="dash-card" id="card-users">
                <div class="card-icon"><span class="icon-user"></span></div>
                <h3>User Management</h3>
                <p>View calendars, edit records, and impersonate users.</p>
            </div>

            <div class="dash-card" id="card-holidays">
                <div class="card-icon"><span class="icon-calendar-dark"></span></div>
                <h3>Holidays</h3>
                <p>Add or remove company holidays and non-working days.</p>
            </div>

            <div class="dash-card" id="card-jobs">
                <div class="card-icon"><span class="icon-category-office"></span></div>
                <h3>Job Codes</h3>
                <p>Add new job codes and archive old ones.</p>
            </div>

            <div class="dash-card" id="card-locations">
                <div class="card-icon"><span class="icon-address"></span></div>
                <h3>Locations</h3>
                <p>Enable/Disable specific States and Counties.</p>
            </div>

        </div>
    </div>
</div>

<div id="modal-users" class="admin-modal hidden">
    <div class="admin-modal-card">
        <div class="modal-header">
            <h3>Manage Users</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <label>Select Employee</label>
            <div class="input-group-row">
                <select id="user-select" class="form-control">
                    <option value="">Loading users...</option>
                </select>
                <button id="btn-view-user" class="primary-button">Open Calendar</button>
            </div>
            <p class="hint">This will open the main timesheet interface as this user.</p>
        </div>
    </div>
</div>

<div id="modal-holidays" class="admin-modal hidden">
    <div class="admin-modal-card large-card">
        <div class="modal-header">
            <h3>Holiday Management</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body split-view">
            <div class="split-left">
                <h4>Add New Holiday</h4>
                <form id="form-holiday">
                    <div class="form-group">
                        <label>Holiday Name</label>
                        <input type="text" id="holiday-name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" id="holiday-start" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" id="holiday-end" required class="form-control">
                    </div>
                    <button type="submit" class="primary-button full-width">Add Holiday</button>
                </form>
            </div>
            <div class="split-right">
                <h4>Upcoming Holidays</h4>
                <div class="list-container" id="holiday-list">
                    </div>
            </div>
        </div>
    </div>
</div>

<div id="modal-jobs" class="admin-modal hidden">
    <div class="admin-modal-card large-card">
        <div class="modal-header">
            <h3>Job Codes</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body split-view">
            <div class="split-left">
                <h4>Create Job</h4>
                <form id="form-job">
                    <div class="form-group">
                        <label>Job Code / Name</label>
                        <input type="text" id="job-name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="job-desc" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="primary-button full-width">Create Job</button>
                </form>
            </div>
            <div class="split-right">
                <h4>Active Jobs</h4>
                <div class="list-container" id="job-list">
                    </div>
            </div>
        </div>
    </div>
</div>

<div id="modal-locations" class="admin-modal hidden">
    <div class="admin-modal-card xlarge-card">
        <div class="modal-header">
            <h3>Location Management</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body split-view">
            <div class="split-left">
                <h4>Enabled States</h4>
                <div class="list-container" id="state-list">
                    </div>
            </div>
            <div class="split-right">
                <h4 id="county-header">Counties (Select State)</h4>
                <div class="list-container" id="county-list">
                    <div class="empty-msg">Select a state on the left to manage counties.</div>
                </div>
            </div>
        </div>
    </div>
</div>