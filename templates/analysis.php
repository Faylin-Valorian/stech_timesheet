<?php
use OCP\Util;
Util::addScript('stech_timesheet', 'analysis');
Util::addStyle('stech_timesheet', 'style');
Util::addStyle('stech_timesheet', 'analysis');
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div id="app">
    <div id="app-navigation">
        <ul class="with-icon">
            <li class="nav-item">
                <a class="nav-link" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('stech_timesheet.page.index')); ?>">
                    <span class="icon-history"></span><span>Back to Timesheet</span>
                </a>
            </li>
            <div class="app-navigation-separator"></div>
            
            <li class="nav-section-header"><span>Analytics</span></li>
            <li class="nav-item"><a class="nav-link active" href="#" id="nav-dashboard"><span class="icon-category-monitoring"></span><span>Dashboard</span></a></li>
            <li class="nav-item"><a class="nav-link" href="#" id="nav-jobs"><span class="icon-category-office"></span><span>Job Breakdown</span></a></li>
        </ul>
    </div>

    <div id="app-content" class="analysis-content">
        
        <div class="analysis-toolbar">
            <div class="filter-group">
                <label>Period:</label>
                <select id="period-selector" class="form-control">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 3 Months</option>
                    <option value="365">Last Year</option>
                </select>
            </div>

            <?php if(\OC::$server->getGroupManager()->isAdmin(\OC::$server->getUserSession()->getUser()->getUID())): ?>
            <div class="filter-group">
                <label>Employee:</label>
                <select id="user-selector" class="form-control">
                    <option value="self">Myself</option>
                    <option value="all">All Employees (Aggregate)</option>
                    </select>
            </div>
            <?php endif; ?>

            <button id="btn-refresh" class="primary-button">Refresh Data</button>
        </div>

        <div id="view-dashboard" class="analysis-view">
            <div class="charts-grid">
                
                <div class="metric-card">
                    <h3>Total Hours</h3>
                    <div class="metric-value" id="metric-total-hours">0.0</div>
                    <div class="metric-sub">Selected Period</div>
                </div>
                <div class="metric-card">
                    <h3>Days Worked</h3>
                    <div class="metric-value" id="metric-days-worked">0</div>
                    <div class="metric-sub">Active Days</div>
                </div>
                <div class="metric-card">
                    <h3>Overtime Est.</h3>
                    <div class="metric-value" id="metric-overtime">0.0</div>
                    <div class="metric-sub">> 40h / week</div>
                </div>

                <div class="chart-container full-width">
                    <h3>Hours Worked Trend</h3>
                    <canvas id="chart-trend"></canvas>
                </div>

                <div class="chart-container">
                    <h3>Work vs. Leave</h3>
                    <canvas id="chart-leave"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Job Distribution (Top 5)</h3>
                    <canvas id="chart-jobs-simple"></canvas>
                </div>
            </div>
        </div>

        <div id="view-jobs" class="analysis-view hidden">
            <div class="chart-container full-width" style="height: 500px;">
                <h3>Detailed Job Code Allocation</h3>
                <canvas id="chart-jobs-detailed"></canvas>
            </div>
        </div>

    </div>
</div>