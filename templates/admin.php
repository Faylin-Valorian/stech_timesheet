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
            <div class="app-navigation-separator"></div>
            
            <li class="nav-section-header"><span>Management</span></li>
            
            <li class="nav-item">
                <a class="nav-link active" href="#" data-tab="users">
                    <span class="icon-user"></span>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-tab="holidays">
                    <span class="icon-calendar-dark"></span>
                    <span>Holidays</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-tab="jobs">
                    <span class="icon-category-office"></span>
                    <span>Jobs</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-tab="locations">
                    <span class="icon-address"></span>
                    <span>Locations</span>
                </a>
            </li>
        </ul>
    </div>

    <div id="app-content">
        <div id="tab-users" class="admin-tab-content">
            <div class="admin-header">
                <h2>User Management</h2>
                <p>View and edit timesheets for other users.</p>
            </div>
            <div class="admin-card">
                <h3>Select User</h3>
                <div class="user-selector-wrapper">
                    <select id="user-select" class="form-control">
                        <option value="">Loading users...</option>
                    </select>
                    <button id="btn-view-user" class="primary-button">View Calendar</button>
                </div>
                <div class="hint-text">
                    Clicking "View Calendar" will redirect you to the main timesheet interface acting as the selected user.
                </div>
            </div>
        </div>

        <div id="tab-holidays" class="admin-tab-content hidden">
            <div class="admin-header">
                <h2>Holidays</h2>
                <button id="btn-add-holiday" class="primary-button">+ Add Holiday</button>
            </div>
            <div class="admin-card">
                <table class="admin-table" id="holiday-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div id="tab-jobs" class="admin-tab-content hidden">
            <div class="admin-header">
                <h2>Job Codes</h2>
            </div>
            <div class="grid-layout">
                <div class="admin-card">
                    <h3>Add New Job</h3>
                    <form id="form-add-job">
                        <div class="input-group">
                            <label>Job Name</label>
                            <input type="text" id="new-job-name" required class="form-control">
                        </div>
                        <div class="input-group">
                            <label>Description</label>
                            <input type="text" id="new-job-desc" class="form-control">
                        </div>
                        <button type="submit" class="primary-button full-width">Create Job</button>
                    </form>
                </div>
                <div class="admin-card">
                    <h3>Active Jobs</h3>
                    <ul id="job-list" class="simple-list"></ul>
                </div>
            </div>
        </div>

        <div id="tab-locations" class="admin-tab-content hidden">
            <div class="admin-header">
                <h2>Locations (States & Counties)</h2>
            </div>
            <div class="grid-layout-2col">
                <div class="admin-card">
                    <h3>States</h3>
                    <div class="scroll-list" id="state-list"></div>
                </div>
                <div class="admin-card">
                    <h3>Counties <span id="county-title-suffix"></span></h3>
                    <div class="scroll-list" id="county-list">
                        <div class="empty-state">Select a state to view counties</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="holiday-modal" class="modal-overlay hidden">
    <div class="modal-card small-card">
        <h3>Edit Holiday</h3>
        <input type="hidden" id="holiday-id">
        <div class="input-group">
            <label>Name</label>
            <input type="text" id="holiday-name" class="form-control">
        </div>
        <div class="input-group">
            <label>Start</label>
            <input type="date" id="holiday-start" class="form-control">
        </div>
        <div class="input-group">
            <label>End</label>
            <input type="date" id="holiday-end" class="form-control">
        </div>
        <div class="modal-footer">
            <button id="btn-cancel-holiday" class="secondary-button">Cancel</button>
            <button id="btn-save-holiday" class="primary-button">Save</button>
        </div>
    </div>
</div>