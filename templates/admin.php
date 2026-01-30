<?php
use OCP\Util;
Util::addScript('stech_timesheet', 'admin');
Util::addStyle('stech_timesheet', 'style'); 
Util::addStyle('stech_timesheet', 'admin'); 
// Fallback image
$fallbackImg = \OC::$server->getURLGenerator()->imagePath('core', 'places/picture.svg'); 

// Pre-generate routes for cleaner HTML
$urlUsers = \OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.admin.getThumbnail', ['filename' => 'thumb-users.png']);
$urlHolidays = \OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.admin.getThumbnail', ['filename' => 'thumb-holidays.png']);
$urlJobs = \OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.admin.getThumbnail', ['filename' => 'thumb-jobs.png']);
$urlLocations = \OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.admin.getThumbnail', ['filename' => 'thumb-locations.png']);
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
            <li class="nav-section-header"><span>Admin Dashboard</span></li>
            <li class="nav-item"><a class="nav-link active" href="#"><span class="icon-settings-dark"></span><span>Overview</span></a></li>
        </ul>
    </div>

    <div id="app-content">
        <div class="admin-page-header">
             <h2 class="admin-page-title">Administration Panel</h2>
             <div class="edit-mode-toggle-wrapper">
                 <label for="admin-edit-mode" class="toggle-label">Edit Mode</label>
                 <label class="admin-switch">
                     <input type="checkbox" id="admin-edit-mode">
                     <span class="admin-slider"></span>
                 </label>
             </div>
        </div>

        <div id="app-content-wrapper" class="admin-wrapper">
            <div class="admin-grid">
                
                <div class="admin-card" id="card-users" data-card-id="users">
                    <div class="card-thumbnail-wrapper">
                        <img src="<?php p($urlUsers); ?>" 
                             data-orig-src="<?php p($urlUsers); ?>"
                             data-fallback-src="<?php p($fallbackImg); ?>"
                             class="card-thumbnail-img" id="thumb-img-users">
                        <div class="thumbnail-edit-overlay">
                            <label for="file-upload-users" class="btn-upload">Change Image</label>
                            <input type="file" id="file-upload-users" class="hidden-file-input" accept="image/png, image/jpeg">
                        </div>
                    </div>
                    <div class="card-details">
                        <h3>User Management</h3>
                        <p id="desc-users" class="editable-desc">Search and impersonate employees.</p>
                    </div>
                </div>

                <div class="admin-card" id="card-holidays" data-card-id="holidays">
                    <div class="card-thumbnail-wrapper">
                        <img src="<?php p($urlHolidays); ?>" 
                             data-orig-src="<?php p($urlHolidays); ?>"
                             data-fallback-src="<?php p($fallbackImg); ?>"
                             class="card-thumbnail-img" id="thumb-img-holidays">
                        <div class="thumbnail-edit-overlay">
                            <label for="file-upload-holidays" class="btn-upload">Change Image</label>
                            <input type="file" id="file-upload-holidays" class="hidden-file-input" accept="image/png, image/jpeg">
                        </div>
                    </div>
                    <div class="card-details">
                        <h3>Holidays</h3>
                        <p id="desc-holidays" class="editable-desc">Manage company holidays.</p>
                    </div>
                </div>

                <div class="admin-card" id="card-jobs" data-card-id="jobs">
                    <div class="card-thumbnail-wrapper">
                        <img src="<?php p($urlJobs); ?>" 
                             data-orig-src="<?php p($urlJobs); ?>"
                             data-fallback-src="<?php p($fallbackImg); ?>"
                             class="card-thumbnail-img" id="thumb-img-jobs">
                        <div class="thumbnail-edit-overlay">
                            <label for="file-upload-jobs" class="btn-upload">Change Image</label>
                            <input type="file" id="file-upload-jobs" class="hidden-file-input" accept="image/png, image/jpeg">
                        </div>
                    </div>
                    <div class="card-details">
                        <h3>Job Codes</h3>
                        <p id="desc-jobs" class="editable-desc">Create and archive job codes.</p>
                    </div>
                </div>

                <div class="admin-card" id="card-locations" data-card-id="locations">
                    <div class="card-thumbnail-wrapper">
                        <img src="<?php p($urlLocations); ?>" 
                             data-orig-src="<?php p($urlLocations); ?>"
                             data-fallback-src="<?php p($fallbackImg); ?>"
                             class="card-thumbnail-img" id="thumb-img-locations">
                        <div class="thumbnail-edit-overlay">
                            <label for="file-upload-locations" class="btn-upload">Change Image</label>
                            <input type="file" id="file-upload-locations" class="hidden-file-input" accept="image/png, image/jpeg">
                        </div>
                    </div>
                    <div class="card-details">
                        <h3>Locations</h3>
                        <p id="desc-locations" class="editable-desc">Enable States and Counties.</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div id="modal-users" class="modal-overlay" style="display: none;">
    <div class="modal-card admin-modal-standard">
        <div class="modal-header">
            <h2>User Management</h2>
            <button class="btn-close-custom close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-section">
                <label>Select Employee</label>
                <div class="searchable-select-wrapper">
                    <input type="text" id="user-search" class="form-control" placeholder="Type to search users..." autocomplete="off">
                    <div id="user-dropdown-list" class="custom-dropdown-list hidden"></div>
                    <input type="hidden" id="selected-user-uid">
                </div>
                <p class="hint-text" style="margin-top:15px; font-size:13px; opacity:0.7;">
                    Clicking "Open Calendar" will redirect you to the timesheet interface acting as this user.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="secondary-button close-modal">Cancel</button>
            <button id="btn-view-user" class="primary-button" disabled>Open Calendar</button>
        </div>
    </div>
</div>

<div id="modal-holidays" class="modal-overlay" style="display: none;">
    <div class="modal-card admin-modal-split">
        <div class="modal-header">
            <h2>Holidays</h2>
            <button class="btn-close-custom close-modal">&times;</button>
        </div>
        <div class="modal-body split-view-body">
            <div class="split-left">
                <h4 class="subsection-title">Add Holiday</h4>
                <form id="form-holiday">
                    <div class="input-group">
                        <label>Holiday Name</label>
                        <input type="text" id="holiday-name" required class="form-control">
                    </div>
                    <div class="input-group">
                        <label>Start Date</label>
                        <input type="date" id="holiday-start" required class="form-control">
                    </div>
                    <div class="input-group">
                        <label>End Date</label>
                        <input type="date" id="holiday-end" required class="form-control">
                    </div>
                    <button type="submit" class="primary-button full-width" style="margin-top:15px;">Add Holiday</button>
                </form>
            </div>
            <div class="split-right">
                <h4 class="subsection-title">Upcoming Holidays</h4>
                <div class="scroll-list" id="holiday-list"></div>
            </div>
        </div>
    </div>
</div>

<div id="modal-jobs" class="modal-overlay" style="display: none;">
    <div class="modal-card admin-modal-split">
        <div class="modal-header">
            <h2>Job Codes</h2>
            <button class="btn-close-custom close-modal">&times;</button>
        </div>
        <div class="modal-body split-view-body">
            <div class="split-left">
                <h4 class="subsection-title">Create Job</h4>
                <form id="form-job">
                    <div class="input-group">
                        <label>Job Name</label>
                        <input type="text" id="job-name" required class="form-control">
                    </div>
                    <div class="input-group">
                        <label>Description</label>
                        <textarea id="job-desc" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="primary-button full-width" style="margin-top:15px;">Create Job</button>
                </form>
            </div>
            <div class="split-right">
                <h4 class="subsection-title">Active Jobs</h4>
                <div class="search-filter-wrapper">
                    <input type="text" id="job-search-input" class="filter-input-with-icon" placeholder="Search jobs...">
                    <button class="btn-filter-icon" id="job-filter-btn" title="Filter"><span class="icon-filter"></span></button>
                    <div class="filter-menu hidden" id="job-filter-menu">
                        <label><input type="radio" name="job-status" value="active" checked> Active Only</label>
                        <label><input type="radio" name="job-status" value="archived"> Archived</label>
                        <label><input type="radio" name="job-status" value="all"> Show All</label>
                    </div>
                </div>
                <div class="scroll-list" id="job-list"></div>
            </div>
        </div>
    </div>
</div>

<div id="modal-locations" class="modal-overlay" style="display: none;">
    <div class="modal-card admin-modal-split">
        <div class="modal-header">
            <h2>Location Settings</h2>
            <button class="btn-close-custom close-modal">&times;</button>
        </div>
        <div class="modal-body split-view-body">
            <div class="split-left">
                <h4 class="subsection-title">States</h4>
                <div class="search-filter-wrapper">
                    <input type="text" id="state-search-input" class="filter-input-with-icon" placeholder="Filter states...">
                    <button class="btn-filter-icon" id="state-filter-btn"><span class="icon-filter"></span></button>
                    <div class="filter-menu hidden" id="state-filter-menu">
                        <label><input type="radio" name="state-status" value="all" checked> Show All</label>
                        <label><input type="radio" name="state-status" value="enabled"> Enabled Only</label>
                        <label><input type="radio" name="state-status" value="disabled"> Disabled</label>
                    </div>
                </div>
                <div class="scroll-list" id="state-list"></div>
            </div>
            <div class="split-right">
                <h4 class="subsection-title" id="county-header">Counties</h4>
                <div class="search-filter-wrapper">
                    <input type="text" id="county-search-input" class="filter-input-with-icon" placeholder="Filter counties..." disabled>
                    <button class="btn-filter-icon" id="county-filter-btn"><span class="icon-filter"></span></button>
                    <div class="filter-menu hidden" id="county-filter-menu">
                        <label><input type="radio" name="county-status" value="all" checked> Show All</label>
                        <label><input type="radio" name="county-status" value="enabled"> Enabled Only</label>
                        <label><input type="radio" name="county-status" value="disabled"> Disabled</label>
                    </div>
                </div>
                <div class="scroll-list" id="county-list">
                    <div style="padding:20px; text-align:center; opacity:0.6;">Select a state to view counties.</div>
                </div>
            </div>
        </div>
    </div>
</div>