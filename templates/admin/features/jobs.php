<div id="admin-job-settings" class="section">
    <div class="section-header-row">
        <h2 class="app-name">Job Codes & Budgets</h2>
        <button id="btn-add-job" class="primary-button">+ Add Job Code</button>
    </div>

    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Job Name</th>
                    <th>Revenue</th>
                    <th>Expense Budget</th>
                    <th>Hourly Cost</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="job-table-body">
                </tbody>
        </table>
    </div>
</div>

<div id="modal-job" class="modal-overlay" style="display:none;">
    <div class="modal-card small">
        <div class="modal-header">
            <h3>Job Details</h3>
            <button class="close-job-modal btn-close-custom">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="j-id">
            
            <div class="admin-form-group">
                <label>Job Code / Name</label>
                <input type="text" id="j-name" class="form-control" placeholder="e.g. 24-105 Project Alpha">
            </div>

            <div class="form-row-2">
                <div class="admin-form-group">
                    <label>Revenue ($)</label>
                    <input type="number" id="j-revenue" class="form-control" step="0.01">
                </div>
                <div class="admin-form-group">
                    <label>Expense Budget ($)</label>
                    <input type="number" id="j-expense" class="form-control" step="0.01">
                </div>
            </div>

            <div class="form-row-2">
                <div class="admin-form-group">
                    <label>Hourly Cost ($)</label>
                    <input type="number" id="j-hourly" class="form-control" step="0.01">
                </div>
                <div class="admin-form-group toggle-container" style="margin-top: 25px;">
                    <input type="checkbox" id="j-pto">
                    <label for="j-pto">Is PTO / Vacation?</label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button id="btn-save-job-exec" class="primary-button">Save Job</button>
        </div>
    </div>
</div>