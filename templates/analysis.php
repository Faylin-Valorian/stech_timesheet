<?php
use OCP\Util;
// Use CDN for Chart.js to ensure it loads correctly
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
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
                        <select id="analysis-target-user" class="form-control">
                            <option value="self">Myself</option>
                            <option value="all">All Employees</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <button id="btn-refresh-analysis" class="primary-button">Update</button>
                </div>
            </div>

            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-value" id="stat-total-hours">0.00</div>
                    <div class="stat-label">Total Hours</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-reg-hours">0.00</div>
                    <div class="stat-label">Regular Work</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-pto-hours">0.00</div>
                    <div class="stat-label">PTO / Vacation</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-overtime-hours">0.00</div>
                    <div class="stat-label">Overtime (>40h)</div>
                </div>
            </div>

            <div class="tabs-container">
                <div class="tab-headers">
                    <button class="tab-btn active" data-tab="tab-overview">Overview</button>
                    
                    <?php if($_['can_view_job_breakdown']): ?>
                    <button class="tab-btn" data-tab="tab-jobs">Job Breakdown</button>
                    <?php endif; ?>
                    
                    <button class="tab-btn" data-tab="tab-travel">Travel & Expenses</button>
                </div>

                <div class="tab-content">
                    <div id="tab-overview" class="tab-pane active">
                        <div class="chart-wrapper">
                            <canvas id="chart-daily"></canvas>
                        </div>
                    </div>

                    <?php if($_['can_view_job_breakdown']): ?>
                    <div id="tab-jobs" class="tab-pane">
                        <div class="split-layout-analysis">
                            <div class="chart-wrapper-half">
                                <canvas id="chart-jobs"></canvas>
                            </div>
                            <div class="table-wrapper-half">
                                <table class="analysis-table">
                                    <thead>
                                        <tr>
                                            <th>Job Code</th>
                                            <th>Hours</th>
                                            <th>Percent</th>
                                        </tr>
                                    </thead>
                                    <tbody id="job-table-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="tab-travel" class="tab-pane">
                        <div class="travel-summary-grid">
                            <div class="travel-stat-box">
                                <h4>Total Miles</h4>
                                <span id="val-total-miles">0</span>
                            </div>
                            <div class="travel-stat-box">
                                <h4>Per Diem Days</h4>
                                <span id="val-per-diem">0</span>
                            </div>
                            <div class="travel-stat-box">
                                <h4>Overnight Stays</h4>
                                <span id="val-overnight">0</span>
                            </div>
                            <div class="travel-stat-box">
                                <h4>Expenses Claimed</h4>
                                <span id="val-expenses">$0.00</span>
                            </div>
                        </div>
                        
                        <h3 style="margin-top:30px; margin-bottom:15px; border-bottom:1px solid var(--color-border); padding-bottom:10px;">Location Summary</h3>
                        <table class="analysis-table">
                            <thead>
                                <tr>
                                    <th>State</th>
                                    <th>County</th>
                                    <th>Visits</th>
                                </tr>
                            </thead>
                            <tbody id="location-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>