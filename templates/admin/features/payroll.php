<div id="admin-payroll-settings" class="section">
    <h2 class="app-name">Payroll Settings</h2>
    
    <div class="admin-form-group">
        <label>Payroll Overlay Status</label>
        <div class="toggle-container">
            <input type="checkbox" id="pay-enabled" name="pay_enabled">
            <label for="pay-enabled">Enable Payroll Overlay on Calendar</label>
        </div>
    </div>

    <div class="admin-form-group">
        <label for="pay-frequency">Pay Frequency</label>
        <select id="pay-frequency" class="form-control">
            <option value="7">Weekly</option>
            <option value="14">Bi-Weekly</option>
            <option value="custom_twice">Twice a Month (Fixed Dates)</option>
        </select>
    </div>

    <div id="freq-standard-options" class="admin-form-group">
        <label for="pay-start-date">Reference Pay Date (Any payday)</label>
        <input type="date" id="pay-start-date" class="form-control">
    </div>

    <div id="freq-custom-options" class="hidden">
        <div class="form-row-2">
            <div class="admin-form-group">
                <label for="pay-date-1">First Pay Date (Day of Month)</label>
                <input type="number" id="pay-date-1" min="1" max="31" class="form-control">
            </div>
            <div class="admin-form-group">
                <label for="pay-date-2">Second Pay Date (Day of Month)</label>
                <input type="number" id="pay-date-2" min="1" max="31" class="form-control">
            </div>
        </div>
    </div>

    <div class="admin-form-group">
        <label>Calendar Overlay Color</label>
        <div class="color-picker-container">
            <input type="color" id="pay-color" class="color-swatch">
            <input type="text" id="pay-color-text" class="form-control color-text" placeholder="#34495e">
        </div>
    </div>

    <div class="admin-footer">
        <button id="btn-save-payroll" class="primary-button">Save Payroll Settings</button>
        <span id="payroll-msg" class="save-msg" style="display:none;">✔️ Saved</span>
    </div>
</div>