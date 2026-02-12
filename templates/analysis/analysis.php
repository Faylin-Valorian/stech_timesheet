<?php
/** @var array $_ */
use OCP\Util;

// Load the compiled assets
Util::addScript('stech_timesheet', 'analysis-main');
Util::addStyle('stech_timesheet', 'leaflet'); 
?>

<div id="app">
    <div id="app-navigation">
        <ul class="with-icon">
            <li class="nav-item">
                <a class="nav-link" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.page.index')); ?>">
                    <span class="icon-history"></span><span>Back to Timesheet</span>
                </a>
            </li>
        </ul>
    </div>

    <div id="app-content">
        <div id="impersonation-banner" class="hidden" style="display:none; background-color: #d9534f; color: white; padding: 10px; justify-content: center; align-items: center; gap: 15px; font-weight: bold;">
            <span>⚠️ You are viewing data for: <strong id="impersonation-name">...</strong></span>
            <button id="btn-end-impersonation" class="secondary-button small" style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.5); color: white; padding: 4px 12px; border-radius: 4px; cursor: pointer;">Close View</button>
        </div>

        <div class="analysis-container">
            <div class="analysis-header">
                <h2>Time Analysis</h2>
                
                <div class="controls-bar">
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
                    
                    <div id="custom-date-inputs" class="control-group hidden">
                        <input type="date" id="analysis-start" class="form-control">
                        <span class="separator">to</span>
                        <input type="date" id="analysis-end" class="form-control">
                    </div>

                    <?php if($_['can_view_others']): ?>
                    <div class="control-group">
                        <select id="user-selector" class="form-control">
                            <option value="self">Myself</option>
                            <option value="all">Everyone (Aggregate)</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <button id="btn-refresh-analysis" class="primary-button">Update Report</button>
                </div>
            </div>

            <div class="tabs-container">
                <div class="tab-headers">
                    <button class="tab-btn active" data-tab="overview">Overview</button>
                    
                    <?php if($_['can_view_travel_analytics']): ?>
                    <button class="tab-btn" data-tab="travel">Travel & Maps</button>
                    <?php endif; ?>
                    
                    <?php if($_['can_view_job_breakdown']): ?>
                    <button class="tab-btn" data-tab="breakdown">Job Breakdown</button>
                    <button class="tab-btn" data-tab="profitability">Profitability</button>
                    <?php endif; ?>
                </div>

                <div class="tab-content">
                    <div id="tab-overview" class="tab-pane active">
                        <div class="stats-grid">
                            <div class="stat-card"><span class="val" id="ov-total">0.00</span><span class="lbl">Total Hours</span></div>
                            <div class="stat-card"><span class="val" id="ov-reg">0.00</span><span class="lbl">Regular</span></div>
                            <div class="stat-card"><span class="val" id="ov-pto">0.00</span><span class="lbl">PTO</span></div>
                            <div class="stat-card"><span class="val" id="ov-ot">0.00</span><span class="lbl">Overtime</span></div>
                        </div>
                        <div class="chart-container large" style="height: 400px; margin-top: 20px;">
                            <canvas id="chart-daily-trend"></canvas>
                        </div>
                    </div>

                    <?php if($_['can_view_travel_analytics']): ?>
                    <div id="tab-travel" class="tab-pane">
                        <div class="stats-grid travel-stats">
                            <div class="stat-card"><span class="val" id="tr-miles">0</span><span class="lbl">Miles</span></div>
                            <div class="stat-card"><span class="val" id="tr-perdiem">0</span><span class="lbl">Per Diem Days</span></div>
                            <div class="stat-card"><span class="val" id="tr-overnight">0</span><span class="lbl">Overnight Stays</span></div>
                            <div class="stat-card"><span class="val" id="tr-exp">$0.00</span><span class="lbl">Expenses</span></div>
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
                    <div id="tab-breakdown" class="tab-pane">
                        <div class="split-layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                            <div class="chart-wrapper" style="height: 400px;">
                                <canvas id="chart-job-breakdown"></canvas>
                            </div>
                            <div class="table-wrapper admin-table-wrapper">
                                <table class="admin-table">
                                    <thead><tr><th>Job Name</th><th class="text-right">Hours</th><th class="text-right">%</th></tr></thead>
                                    <tbody id="job-breakdown-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div id="tab-profitability" class="tab-pane">
                         <div class="profit-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                            <h3>Project Profitability</h3>
                            <div class="control-group">
                                <input type="text" id="profit-job-search" placeholder="Search/Select Job..." list="profit-job-list" class="form-control" style="width: 250px;">
                                <datalist id="profit-job-list"></datalist>
                            </div>
                         </div>
                         <div class="gauge-wrapper" style="position:relative; height:400px; display:flex; justify-content:center;">
                            <canvas id="chart-profit-gauge"></canvas>
                            <div id="profit-display-text" style="position:absolute; bottom:20px; width:100%;"></div>
                         </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>