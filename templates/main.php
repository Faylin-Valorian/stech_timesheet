<?php
/** @var array $_ */
use OCP\Util;

// Load Dependencies
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
                    
                    <?php if(!empty($_['can_toggle_archive'])): ?>
                    <button id="toggle-archive-view" class="secondary-button" title="Toggle Archived/Active Records" style="margin-left: 10px; display: inline-flex; align-items: center; justify-content: center; padding: 0 12px;">
                        <span class="icon-filter"></span>
                    </button>
                    <?php endif; ?>
                </div>
            </li>
            <div class="app-navigation-separator"></div>
            
            <li class="nav-item">
                <a class="nav-link active" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.page.index')); ?>">
                    <span class="icon-history"></span><span>Timesheet</span>
                </a>
            </li>

            <?php if(!empty($_['can_view_analysis'])): ?>
            <li class="nav-item">
                <a class="nav-link" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.page.analysis')); ?>">
                    <span class="icon-category-monitoring"></span><span>Time Analysis</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if(!empty($_['can_view_admin'])): ?>
            <li class="nav-item">
                <a class="nav-link" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.admin.index')); ?>">
                    <span class="icon-settings-dark"></span><span>Admin Panel</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <div id="app-content">
        <?php if(!empty($_['target_user'])): ?>
        <div id="impersonation-banner" style="background-color: #d9534f; color: white; padding: 10px; display: flex; justify-content: center; align-items: center; gap: 15px; font-weight: bold;">
            <span>⚠️ You are viewing the timesheet for user: <?php p($_['target_user']); ?></span>
            <button id="btn-end-impersonation" style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.5); color: white; padding: 4px 12px; border-radius: 4px; cursor: pointer; font-size: 0.9em;">Close Impersonation</button>
        </div>
        <input type="hidden" id="global-target-user" value="<?php p($_['target_user']); ?>">
        <?php endif; ?>

        <div id="app-content-wrapper">
            <div id="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>

        <div id="timesheet-modal" class="modal-overlay" style="display: none;">
            <div class="modal-card">
                <form id="timesheet-form">
                    <input type="hidden" id="timesheet-id" name="timesheet_id">

                    <div class="modal-header">
                        <div>
                            <h2 id="modal-date-title">Entry Details</h2>
                            <span class="modal-subtitle">Daily Work Record</span>
                        </div>
                        <button type="button" class="close-modal" title="Close">&times;</button>
                    </div>

                    <div class="modal-body">
                        <div class="form-section">
                            <div class="form-row-4">
                                <div class="input-group">
                                    <label>Date</label>
                                    <input type="date" id="timesheet-date" name="date" class="form-control readonly-highlight" readonly>
                                </div>
                                
                                <div class="input-group">
                                    <label>Time In</label>
                                    <div class="time-split-widget">
                                        <input type="hidden" name="time_in" id="time-in" class="combined-time-input">
                                        
                                        <select class="time-part hour-select">
                                            <option value="" disabled selected>--</option>
                                            <?php for($h=1; $h<=12; $h++) { 
                                                $val = str_pad($h, 2, '0', STR_PAD_LEFT);
                                                echo "<option value='$val'>$val</option>"; 
                                            } ?>
                                        </select>
                                        
                                        <span class="time-separator">:</span>
                                        
                                        <select class="time-part minute-select">
                                            <option value="" disabled selected>--</option>
                                            <?php for($m=0; $m<60; $m++) { 
                                                $val = str_pad($m, 2, '0', STR_PAD_LEFT);
                                                echo "<option value='$val'>$val</option>"; 
                                            } ?>
                                        </select>

                                        <select class="time-part ampm-select">
                                            <option value="AM">AM</option>
                                            <option value="PM">PM</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="input-group">
                                    <label>Time Out</label>
                                    <div class="time-split-widget">
                                        <input type="hidden" name="time_out" id="time-out" class="combined-time-input">
                                        
                                        <select class="time-part hour-select">
                                            <option value="" disabled selected>--</option>
                                            <?php for($h=1; $h<=12; $h++) { 
                                                $val = str_pad($h, 2, '0', STR_PAD_LEFT);
                                                echo "<option value='$val'>$val</option>"; 
                                            } ?>
                                        </select>
                                        
                                        <span class="time-separator">:</span>
                                        
                                        <select class="time-part minute-select">
                                            <option value="" disabled selected>--</option>
                                            <?php for($m=0; $m<60; $m++) { 
                                                $val = str_pad($m, 2, '0', STR_PAD_LEFT);
                                                echo "<option value='$val'>$val</option>"; 
                                            } ?>
                                        </select>

                                        <select class="time-part ampm-select">
                                            <option value="AM">AM</option>
                                            <option value="PM">PM</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="input-group">
                                    <label>Break (min)</label>
                                    <input type="number" id="break-min" name="break_min" value="0" class="form-control">
                                </div>
                            </div>
                            <div class="form-row-1" style="margin-top: 10px;">
                                <div class="input-group">
                                    <label>Total Hours Worked</label>
                                    <input type="text" id="total-hours" name="total_hours" class="form-control readonly-highlight" readonly value="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="form-separator"></div>

                        <div class="form-section">
                            <div class="section-header-row">
                                <h3 class="section-title">Work Breakdown</h3>
                                <button type="button" id="btn-add-row" class="text-button">+ Add Item</button>
                            </div>
                            <div class="work-grid-header">
                                <span>Description</span>
                                <span class="text-center">Percent (%)</span>
                                <span></span>
                            </div>
                            <div id="work-rows-container"></div>
                        </div>

                        <div class="form-separator"></div>

                        <div class="form-section toggle-row-container">
                            <div class="toggle-wrapper">
                                <input type="checkbox" id="toggle-pto" name="is_vacation" class="toggle-checkbox">
                                <label for="toggle-pto" class="toggle-button">
                                    <span class="icon-vacation"></span> Vacation / PTO
                                </label>
                            </div>
                            <div class="toggle-wrapper">
                                <input type="checkbox" id="toggle-travel" name="has_travel" class="toggle-checkbox">
                                <label for="toggle-travel" class="toggle-button">
                                    <span class="icon-travel"></span> Travel Records
                                </label>
                            </div>
                        </div>

                        <div id="travel-fields-container" class="hidden-section">
                            <div class="travel-box">
                                <h4 class="subsection-title">Travel Details</h4>
                                <div class="travel-toggles-grid">
                                    <div class="switch-wrapper">
                                        <label class="switch-label">Request Per Diem</label>
                                        <label class="switch"><input type="checkbox" id="req-per-diem" name="req_per_diem"><span class="slider round"></span></label>
                                    </div>
                                    <div class="switch-wrapper">
                                        <label class="switch-label">Road Scanning</label>
                                        <label class="switch"><input type="checkbox" id="road-scanning" name="road_scanning"><span class="slider round"></span></label>
                                    </div>
                                    <div class="switch-wrapper">
                                        <label class="switch-label">First / Last Day</label>
                                        <label class="switch"><input type="checkbox" id="first-last-day" name="first_last_day"><span class="slider round"></span></label>
                                    </div>
                                    <div class="switch-wrapper">
                                        <label class="switch-label">Overnight Stay</label>
                                        <label class="switch"><input type="checkbox" id="overnight" name="overnight"><span class="slider round"></span></label>
                                    </div>
                                </div>

                                <div class="form-row-3">
                                    <div class="input-group">
                                        <label>State</label>
                                        <input list="state-options" id="travel-state" name="state" class="form-control" placeholder="Search state..." autocomplete="off">
                                        <datalist id="state-options"></datalist>
                                    </div>
                                    <div class="input-group">
                                        <label>County</label>
                                        <input list="county-options" id="travel-county" name="county" class="form-control" placeholder="Search county..." autocomplete="off">
                                        <datalist id="county-options"></datalist>
                                    </div>
                                    <div class="input-group">
                                        <label>Total Miles</label>
                                        <input type="number" id="travel-miles" name="miles" value="0" class="form-control">
                                    </div>
                                </div>

                                <div class="form-row-1">
                                    <div class="input-group">
                                        <label>Extra Expenses Request</label>
                                        <div class="currency-group">
                                            <span class="currency-symbol">$</span>
                                            <input type="number" id="travel-extra-expense" name="extra_expense" placeholder="0.00" step="0.01" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-separator"></div>

                        <div class="form-section">
                            <div class="input-group">
                                <label>Additional Comments</label>
                                <textarea id="additional-comments" name="comments" rows="3" class="form-control" placeholder="Add details..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="error-button" id="btn-delete" style="display: none; margin-right: auto;">Delete Entry</button>
                        <button type="button" class="secondary-button close-modal">Cancel</button>
                        <button type="submit" class="primary-button" id="btn-save">Save Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>