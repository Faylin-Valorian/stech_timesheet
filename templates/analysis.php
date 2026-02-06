<?php
use OCP\Util;

Util::addStyle('stech_timesheet', 'leaflet');
Util::addScript('stech_timesheet', 'analysis');
Util::addStyle('stech_timesheet', 'style');
Util::addStyle('stech_timesheet', 'analysis');
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

    <div id="app-content">
        <div id="impersonation-banner" style="display:none; background-color: #d9534f; color: white; padding: 10px; justify-content: center; align-items: center; gap: 15px; font-weight: bold;">
            <span>⚠️ You are viewing data for: <span id="impersonation-name">Unknown</span></span>
            <button id="btn-end-impersonation-analysis" style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.5); color: white; padding: 4px 12px; border-radius: 4px; cursor: pointer; font-size: 0.9em;">Close Impersonation</button>
        </div>

        <div class="analysis-container">
            <div class="analysis-header">
                <h2>Time Analysis Dashboard</h2>
                <div class="date-range-selector">
                    <div class="control-group">
                        <select id="range-preset" class="form-control">
                            <option value="this_pay_period">Current Pay Period</option>
                            <option value="last_pay_period">Last Pay Period</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="ytd">Year to Date</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div id="custom-date-inputs" class="hidden control-group">
                        <input type="date" id="analysis-start" class="form-control">
                        <span class="range-separator">to</span>
                        <input type="date" id="analysis-end" class="form-control">
                    </div>
                    
                    <?php if($_['can_view_others']): ?>
                    <div class="control-group">
                        <select id="user-selector" class="form-control" style="width: 200px;">
                            <option value="self">Myself</option>
                            <option value="all">Everyone (Aggregate)</option>
                        </select>
                        <input type="hidden" id="analysis-target-user" value="self">
                    </div>
                    <?php endif; ?>
                    
                    <button id="btn-refresh-analysis" class="primary-button">Update</button>
                </div>
            </div>

            <div class="stats-overview">
                <div class="stat-card"><div class="stat-value" id="stat-total-hours">0.00</div><div class="stat-label">Total Hours</div></div>
                <div class="stat-card"><div class="stat-value" id="stat-reg-hours">0.00</div><div class="stat-label">Regular Work</div></div>
                <div class="stat-card"><div class="stat-value" id="stat-pto-hours">0.00</div><div class="stat-label">PTO / Vacation</div></div>
                <div class="stat-card"><div class="stat-value" id="stat-overtime-hours">0.00</div><div class="stat-label">Overtime (>40h)</div></div>
            </div>

            <div class="tabs-container">
                <div class="tab-headers">
                    <button class="tab-btn active" data-tab="tab-overview">Overview</button>
                    <?php if($_['can_view_travel_analytics']): ?>
                    <button class="tab-btn" data-tab="tab-travel">Travel Activity</button>
                    <?php endif; ?>
                    <?php if($_['can_view_job_breakdown']): ?>
                    <button class="tab-btn" data-tab="tab-jobs">Job Breakdown</button>
                    <button class="tab-btn" data-tab="tab-profitability">Job Profitability</button>
                    <?php endif; ?>
                </div>

                <div class="tab-content">
                    <div id="tab-overview" class="tab-pane active"><div class="chart-wrapper"><canvas id="chart-daily"></canvas></div></div>

                    <?php if($_['can_view_travel_analytics']): ?>
                    <div id="tab-travel" class="tab-pane">
                         <div class="travel-summary-grid">
                            <div class="travel-stat-box"><h4>Total Miles</h4><span id="val-total-miles">0</span></div>
                            <div class="travel-stat-box"><h4>Per Diem Days</h4><span id="val-per-diem">0</span></div>
                            <div class="travel-stat-box"><h4>Overnight Stays</h4><span id="val-overnight">0</span></div>
                            <div class="travel-stat-box"><h4>Expenses Claimed</h4><span id="val-expenses">$0.00</span></div>
                        </div>
                        
                        <div class="travel-map-layout" style="display: grid; grid-template-columns: 350px 1fr; gap: 20px; margin-top: 20px; height: 600px;">
                            <div id="location-detail-panel" style="background: var(--color-main-background); padding: 20px; border: 1px solid var(--color-border); border-radius: var(--border-radius); display: flex; flex-direction: column;">
                                <button id="btn-reset-map" class="secondary-button full-width" style="margin-bottom: 20px; display: none;">← Back to Select State</button>
                                <h3 id="detail-title" style="margin-top: 0; text-align: center; border-bottom: 2px solid var(--color-primary); padding-bottom: 10px;">National Overview</h3>
                                <div id="detail-content" style="flex: 1; overflow-y: auto;">
                                    <p style="text-align: center; color: var(--color-text-maxcontrast); padding-top: 10px;">
                                        Select a State on the map to view County details.
                                    </p>
                                </div>
                            </div>
                            <div id="map-main-container" class="map-wrapper" style="height: 100%; width: 100%;"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($_['can_view_job_breakdown']): ?>
                    <div id="tab-jobs" class="tab-pane">
                        <div class="split-layout-analysis">
                            <div class="chart-wrapper-half"><canvas id="chart-jobs"></canvas></div>
                            <div class="table-wrapper-half"><table class="analysis-table"><thead><tr><th>Job Code</th><th>Hours</th><th>Percent</th></tr></thead><tbody id="job-table-body"></tbody></table></div>
                        </div>
                    </div>
                    
                    <div id="tab-profitability" class="tab-pane">
                        <div class="profitability-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                            <h3>Job Profitability Meter</h3>
                            <div class="control-group">
                                <label for="job-search" style="margin-right:10px; font-size:0.9em; color:var(--color-text-light);">Select Job:</label>
                                <input type="text" id="job-search" list="job-list" class="form-control" placeholder="All Jobs (Overview)" style="width: 250px;">
                                <datalist id="job-list"><option value="All Jobs" data-value="all"></option></datalist>
                                <input type="hidden" id="analysis-job-filter" value="all">
                            </div>
                        </div>
                        <div class="gauge-container" style="position:relative; width:100%; height:400px; display:flex; justify-content:center; align-items:flex-end;">
                            <canvas id="chart-profitability-gauge"></canvas>
                            <div id="gauge-value-display" style="position:absolute; bottom:20px; font-size:24px; font-weight:bold; color:var(--color-main-text);">0 Hrs</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>