<div id="admin-holiday-settings" class="section">
    <div class="section-header-row">
        <h2 class="app-name">Company Holidays</h2>
        <button id="btn-add-holiday" class="primary-button">+ Add Holiday</button>
    </div>

    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="holiday-table-body">
                </tbody>
        </table>
    </div>
</div>

<div id="modal-holiday" class="modal-overlay" style="display:none;">
    <div class="modal-card small">
        <div class="modal-header">
            <h3>Holiday Details</h3>
            <button class="close-holiday-modal btn-close-custom">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="h-id">
            <div class="admin-form-group">
                <label>Holiday Name</label>
                <input type="text" id="h-name" class="form-control">
            </div>
            <div class="form-row-2">
                <div class="admin-form-group">
                    <label>Start Date</label>
                    <input type="date" id="h-start" class="form-control">
                </div>
                <div class="admin-form-group">
                    <label>End Date</label>
                    <input type="date" id="h-end" class="form-control">
                </div>
            </div>
            <div class="admin-form-group">
                <label>Calendar Color</label>
                <input type="color" id="h-bg" class="form-control" style="height: 40px; padding: 2px;">
            </div>
        </div>
        <div class="modal-footer">
            <button id="btn-save-holiday-exec" class="primary-button">Save Holiday</button>
        </div>
    </div>
</div>