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

        <div id="timesheet-modal-overlay" class="modal-overlay" style="display: none;">
            <div class="modal-card">
                <form id="timesheet-form">
                    
                    <div class="modal-header">
                        <div>
                            <h2 id="modal-date-title">New Entry</h2>
                            <span class="modal-subtitle">Daily Work Record</span>
                        </div>
                        <button type="button" class="btn-close-custom" id="modal-close-btn" title="Close">&times;</button>
                    </div>

                    <div class="modal-body">

                        <div class="form-section">
                            <div class="form-row-4">
                                <div class="input-group">
                                    <label>Date</label>
                                    <input type="date" id="entry-date" name="date" class="form-control" readonly>
                                </div>
                                <div class="input-group">
                                    <label>Time In</label>
                                    <input type="time" id="time-in" name="time_in" class="form-control calc-time" required>
                                </div>
                                <div class="input-group">
                                    <label>Time Out</label>
                                    <input type="time" id="time-out" name="time_out" class="form-control calc-time">
                                </div>
                                <div class="input-group">
                                    <label>Break (min)</label>
                                    <input type="number" id="break-min" name="break_min" placeholder="0" class="form-control calc-time">
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

                            <div id="work-rows-container">
                                </div>
                        </div>

                        <div class="form-separator"></div>

                        <div class="form-section toggle-row-container">
                            <div class="toggle-wrapper">
                                <input type="checkbox" id="toggle-vacation" name="is_vacation" class="toggle-checkbox">
                                <label for="toggle-vacation" class="toggle-button">
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
                                        <label class="switch"><input type="checkbox" name="req_per_diem"><span class="slider round"></span></label>
                                    </div>
                                    <div class="switch-wrapper">
                                        <label class="switch-label">Road Scanning</label>
                                        <label class="switch"><input type="checkbox" name="road_scanning"><span class="slider round"></span></label>
                                    </div>
                                    <div class="switch-wrapper">
                                        <label class="switch-label">First / Last Day</label>
                                        <label class="switch"><input type="checkbox" name="first_last_day"><span class="slider round"></span></label>
                                    </div>
                                    <div class="switch-wrapper">
                                        <label class="switch-label">Overnight Stay</label>
                                        <label class="switch"><input type="checkbox" name="overnight"><span class="slider round"></span></label>
                                    </div>
                                </div>

                                <div class="form-row-3">
                                    <div class="input-group">
                                        <label>State</label>
                                        <select name="state" id="travel-state" class="form-control">
                                            <option value="">Select State...</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label>County</label>
                                        <select name="county" id="travel-county" class="form-control">
                                            <option value="">Select County...</option>
                                        </select>
                                    </div>
                                     <div class="input-group">
                                        <label>Total Miles</label>
                                        <input type="number" name="miles" class="form-control" placeholder="0">
                                    </div>
                                </div>

                                <div class="form-row-1">
                                    <div class="input-group">
                                        <label>Extra Expenses Request</label>
                                        <div class="currency-group">
                                            <span class="currency-symbol">$</span>
                                            <input type="number" name="extra_expense" placeholder="0.00" step="0.01" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-separator"></div>

                        <div class="form-section">
                            <div class="input-group">
                                <label>Additional Comments</label>
                                <textarea name="comments" rows="3" class="form-control" placeholder="Add details..."></textarea>
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="secondary-button" id="btn-cancel">Cancel</button>
                        <button type="submit" class="primary-button" id="btn-save">Save Entry</button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>