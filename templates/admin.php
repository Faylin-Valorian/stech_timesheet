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

            <li class="nav-section-header"><span>Management</span></li>
            
            <li class="nav-item" data-rule="admin_users">
                <a class="nav-link active" href="#" id="nav-users">
                    <span class="icon-user"></span>
                    <span>Employees</span>
                </a>
            </li>
            
            <li class="nav-item" data-rule="admin_access">
                <a class="nav-link" href="#" id="nav-access">
                    <span class="icon-password"></span>
                    <span>Access Control</span>
                </a>
            </li>

            <li class="nav-item" data-rule="admin_payroll">
                <a class="nav-link" href="#" id="nav-payroll">
                    <span class="icon-money"></span>
                    <span>Payroll Settings</span>
                </a>
            </li>
            
            <li class="nav-item" data-rule="admin_holidays">
                <a class="nav-link" href="#" id="nav-holidays">
                    <span class="icon-calendar-dark"></span>
                    <span>Holidays</span>
                </a>
            </li>
            <li class="nav-item" data-rule="admin_jobs">
                <a class="nav-link" href="#" id="nav-jobs">
                    <span class="icon-category-office"></span>
                    <span>Jobs / Codes</span>
                </a>
            </li>
            <li class="nav-item" data-rule="admin_locations">
                <a class="nav-link" href="#" id="nav-locations">
                    <span class="icon-address"></span>
                    <span>Locations</span>
                </a>
            </li>
        </ul>
    </div>

    <div id="app-content">

        <div id="view-users" class="admin-view">
            <div class="view-header">
                <div class="panel-header-row">
                    <div><h2>Employee Management</h2><p>Manage access, view timesheets, and set active status.</p></div>
                    <div class="action-buttons">
                        <div class="search-filter-wrapper">
                            <button id="user-filter-btn" class="btn-filter-icon"><span class="icon-filter"></span></button>
                            <div id="user-filter-menu" class="filter-menu hidden">
                                <label><input type="radio" name="user-status" value="active" checked> Active</label>
                                <label><input type="radio" name="user-status" value="inactive"> Inactive</label>
                                <label><input type="radio" name="user-status" value="all"> All</label>
                            </div>
                            <input type="text" id="user-search-input" class="filter-input-with-icon" placeholder="Search...">
                        </div>
                    </div>
                </div>
            </div>
            <div class="view-body"><div id="user-grid-container" class="user-grid"></div></div>
        </div>

        <div id="view-access" class="admin-view hidden">
            <div class="view-header"><h2>Access Control</h2><p>Grant permission to specific features.</p></div>
            <div class="view-body" style="max-width: 900px;">
                <div class="split-layout">
                    <div class="split-panel left" style="width: 250px;">
                        <h4 class="panel-title">General</h4>
                        <div class="access-tab active" data-target="panel-adm-global">Admin Panel Button</div>
                        
                        <h4 class="panel-title" style="margin-top:15px;">Admin Sections</h4>
                        <div class="access-tab" data-target="panel-adm-access">Access Control</div>
                        <div class="access-tab" data-target="panel-adm-users">Employees</div>
                        <div class="access-tab" data-target="panel-adm-payroll">Payroll</div>
                        <div class="access-tab" data-target="panel-adm-holidays">Holidays</div>
                        <div class="access-tab" data-target="panel-adm-jobs">Jobs</div>
                        <div class="access-tab" data-target="panel-adm-locations">Locations</div>
                        
                        <h4 class="panel-title" style="margin-top:15px;">Analysis Features</h4>
                        <div class="access-tab" data-target="panel-analysis">Main Analysis Tab</div>
                        <div class="access-tab" data-target="panel-view-others">View All Employees</div>
                        <div class="access-tab" data-target="panel-travel">Travel Tab</div>
                        <div class="access-tab" data-target="panel-financial">Financial Tab</div>
                    </div>

                    <div class="split-panel right">
                        <div id="panel-adm-global" class="access-group-panel">
                            <h3 class="panel-title">Grant Access: Admin Panel Button</h3>
                            <p class="panel-desc">Who can see the "Admin Panel" link in the main navigation? (Admins always have access).</p>
                            <div id="list-access-admin-global" class="group-toggle-list"></div>
                        </div>

                        <div id="panel-adm-access" class="access-group-panel hidden">
                            <h3 class="panel-title">Admin: Access Control Tab</h3>
                            <p class="panel-desc">Who can change permissions inside the panel.</p>
                            <div id="list-access-admin-access" class="group-toggle-list"></div>
                        </div>
                        <div id="panel-adm-users" class="access-group-panel hidden">
                            <h3 class="panel-title">Admin: Employees Tab</h3>
                            <div id="list-access-admin-users" class="group-toggle-list"></div>
                        </div>
                        <div id="panel-adm-payroll" class="access-group-panel hidden">
                            <h3 class="panel-title">Admin: Payroll Tab</h3>
                            <div id="list-access-admin-payroll" class="group-toggle-list"></div>
                        </div>
                        <div id="panel-adm-holidays" class="access-group-panel hidden">
                            <h3 class="panel-title">Admin: Holidays Tab</h3>
                            <div id="list-access-admin-holidays" class="group-toggle-list"></div>
                        </div>
                        <div id="panel-adm-jobs" class="access-group-panel hidden">
                            <h3 class="panel-title">Admin: Jobs Tab</h3>
                            <div id="list-access-admin-jobs" class="group-toggle-list"></div>
                        </div>
                        <div id="panel-adm-locations" class="access-group-panel hidden">
                            <h3 class="panel-title">Admin: Locations Tab</h3>
                            <div id="list-access-admin-locations" class="group-toggle-list"></div>
                        </div>

                        <div id="panel-analysis" class="access-group-panel hidden">
                            <h3 class="panel-title">Analysis Tab</h3>
                            <div id="list-access-analysis-tab" class="group-toggle-list"></div>
                        </div>
                        <div id="panel-view-others" class="access-group-panel hidden">
                            <h3 class="panel-title">View All Employees</h3>
                            <div id="list-access-analysis-others" class="group-toggle-list"></div>
                        </div>
                        <div id="panel-travel" class="access-group-panel hidden">
                            <h3 class="panel-title">Travel Tab</h3>
                            <div id="list-access-analysis-travel" class="group-toggle-list"></div>
                        </div>
                        <div id="panel-financial" class="access-group-panel hidden">
                            <h3 class="panel-title">Financial Tab</h3>
                            <div id="list-access-analysis-financial" class="group-toggle-list"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="view-payroll" class="admin-view hidden">
            <div class="view-header">
                <h2>Payroll Configuration</h2>
                <p>Configure the visual "Payroll" tab appearance on the calendar.</p>
            </div>
            <div class="view-body" style="max-width: 600px;">
                <div class="form-section">
                    <div class="input-group">
                        <label>Pay Frequency</label>
                        <select id="pay-frequency" class="form-control">
                            <option value="14">Bi-Weekly (Every 2 Weeks)</option>
                            <option value="7">Weekly</option>
                            <option value="28">Every 4 Weeks</option>
                            <option value="custom_twice">Custom (Twice a Month)</option>
                        </select>
                    </div>
                    
                    <div id="freq-standard-options" class="input-group" style="margin-top: 15px;">
                        <label>Reference Start Date</label>
                        <p style="font-size: 0.85em; opacity: 0.7; margin-top:0; margin-bottom:5px;">Pick any valid Pay Day.</p>
                        <input type="date" id="pay-start-date" class="form-control">
                    </div>

                    <div id="freq-custom-options" class="hidden" style="margin-top: 15px; border-left: 3px solid var(--color-primary-element); padding-left: 15px;">
                        <label style="margin-bottom:10px;">Recurring Pay Days</label>
                        <div style="display: flex; gap: 15px;">
                            <div style="flex:1;">
                                <label style="font-size: 0.85em; opacity:0.8;">First Pay Day</label>
                                <select id="pay-date-1" class="form-control">
                                    <?php for($i=1; $i<=28; $i++) echo "<option value='$i'>$i" . date("S", mktime(0,0,0,0,$i,0)) . "</option>"; ?>
                                </select>
                            </div>
                            <div style="flex:1;">
                                <label style="font-size: 0.85em; opacity:0.8;">Second Pay Day</label>
                                <select id="pay-date-2" class="form-control">
                                    <?php for($i=1; $i<=28; $i++) echo "<option value='$i'>$i" . date("S", mktime(0,0,0,0,$i,0)) . "</option>"; ?>
                                </select>
                            </div>
                        </div>
                        <p style="font-size: 0.85em; opacity: 0.7; margin-top:10px;">Select the two days of the month employees are paid.</p>
                    </div>

                    <div style="margin-top: 25px; display: flex; align-items: center; gap: 15px;">
                        <button id="btn-save-payroll" class="primary-button">Save Settings</button>
                        <span id="payroll-msg" style="color: var(--color-success); font-weight: bold; display: none;">Saved!</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="view-holidays" class="admin-view hidden">
            <div class="view-header"><h2>Holiday Calendar</h2><p>Define company holidays.</p></div>
            <div class="split-layout">
                <div class="split-panel left">
                    <div class="panel-header-row">
                        <span class="panel-title">Holiday List</span>
                        <div class="search-filter-wrapper" style="width: auto;">
                            <button id="holiday-filter-btn" class="btn-filter-icon"><span class="icon-filter"></span></button>
                            <div id="holiday-filter-menu" class="filter-menu hidden">
                                <label><input type="radio" name="holiday-status" value="active" checked> Active</label>
                                <label><input type="radio" name="holiday-status" value="archived"> Archived</label>
                            </div>
                        </div>
                    </div>
                    <input type="text" id="holiday-search-input" class="form-control" placeholder="Search holidays..." style="margin-bottom: 10px;">
                    <div id="holiday-list" class="scroll-list"></div>
                </div>
                <div class="split-panel right">
                    <h3 id="holiday-form-title" class="panel-title">Add Holiday</h3>
                    <form id="form-holiday" class="max-width-600">
                        <input type="hidden" id="holiday-id">
                        <div class="input-group"><label>Holiday Name</label><input type="text" id="holiday-name" class="form-control" required></div>
                        <div class="input-group"><label>Start Date</label><input type="date" id="holiday-start" class="form-control" required></div>
                        <div class="input-group"><label>End Date (Optional)</label><input type="date" id="holiday-end" class="form-control"></div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" id="btn-save-holiday" class="primary-button full-width">Add Holiday</button>
                            <button type="button" id="btn-cancel-holiday" class="secondary-button hidden">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="view-jobs" class="admin-view hidden">
            <div class="view-header"><h2>Job Codes</h2><p>Manage job codes.</p></div>
            <div class="split-layout">
                <div class="split-panel left">
                    <div class="panel-header-row">
                        <span class="panel-title">Job List</span>
                        <div class="search-filter-wrapper" style="width: auto;">
                            <button id="job-filter-btn" class="btn-filter-icon"><span class="icon-filter"></span></button>
                            <div id="job-filter-menu" class="filter-menu hidden">
                                <label><input type="radio" name="job-status" value="active" checked> Active Jobs</label>
                                <label><input type="radio" name="job-status" value="archived"> Archived Jobs</label>
                            </div>
                        </div>
                    </div>
                    <input type="text" id="job-search-input" class="form-control" placeholder="Search jobs..." style="margin-bottom: 10px;">
                    <div id="job-list" class="scroll-list"></div>
                </div>
                <div class="split-panel right">
                    <h3 id="job-form-title" class="panel-title">Create Job</h3>
                    <form id="form-job" class="max-width-600">
                        <input type="hidden" id="job-id">
                        <div class="input-group"><label>Job Code / Name</label><input type="text" id="job-name" class="form-control" required></div>
                        <div class="input-group"><label>Description</label><textarea id="job-desc" class="form-control" rows="3"></textarea></div>
                        <div class="input-group" style="display: flex; align-items: center; gap: 10px; border: 1px solid var(--color-border); padding: 10px; border-radius: 4px; background: var(--color-main-background);">
                            <label class="admin-switch" style="margin:0;"><input type="checkbox" id="job-is-pto"><span class="admin-slider"></span></label>
                            <span style="font-weight: bold; font-size: 0.9em; opacity: 0.8;">Is Vacation / Sick Record?</span>
                        </div>
                        
                        <div class="form-separator"></div>
                        <h4>Financials</h4>
                        
                        <div class="input-group">
                            <label>Estimated Revenue ($)</label>
                            <input type="number" step="0.01" id="job-revenue" class="form-control">
                        </div>
                        <div class="input-group">
                            <label>Expense Budget ($)</label>
                            <input type="number" step="0.01" id="job-expense" class="form-control">
                        </div>
                        <div class="input-group">
                            <label>Hourly Cost Estimate ($)</label>
                            <input type="number" step="0.01" id="job-hourly" class="form-control">
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button type="submit" id="btn-save-job" class="primary-button full-width">Create Job</button>
                            <button type="button" id="btn-cancel-job" class="secondary-button hidden">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="view-locations" class="admin-view hidden">
            <div class="view-header"><h2>Locations</h2><p>Enable or disable states and counties.</p></div>
            <div class="split-layout">
                <div class="split-panel left">
                    <div class="panel-header-row">
                        <span class="panel-title">States</span>
                        <div class="search-filter-wrapper" style="width: auto;">
                            <button id="state-filter-btn" class="btn-filter-icon"><span class="icon-filter"></span></button>
                            <div id="state-filter-menu" class="filter-menu hidden">
                                <label><input type="radio" name="state-status" value="enabled" checked> Active States</label>
                                <label><input type="radio" name="state-status" value="disabled"> Inactive States</label>
                            </div>
                        </div>
                    </div>
                    <input type="text" id="state-search-input" class="form-control" placeholder="Search states..." style="margin-bottom: 10px;">
                    <div id="state-list" class="scroll-list"></div>
                </div>
                <div class="split-panel right">
                    <div class="panel-header-row">
                        <span id="county-header" class="panel-title">Counties (Select a State)</span>
                        <div class="search-filter-wrapper" style="width: auto;">
                            <button id="county-filter-btn" class="btn-filter-icon"><span class="icon-filter"></span></button>
                            <div id="county-filter-menu" class="filter-menu hidden">
                                <label><input type="radio" name="county-status" value="enabled" checked> Active Counties</label>
                                <label><input type="radio" name="county-status" value="disabled"> Inactive Counties</label>
                            </div>
                        </div>
                    </div>
                    <input type="text" id="county-search-input" class="form-control" placeholder="Search counties..." style="margin-bottom: 10px;" disabled>
                    <div id="county-list" class="scroll-list"></div>
                </div>
            </div>
        </div>

    </div>
</div>