<div id="admin-user-settings" class="section">
    <h2 class="app-name">Access Control & Users</h2>

    <div class="location-panel" style="margin-bottom: 30px;">
        <h3 class="subsection-title">Feature Permissions</h3>
        <p class="settings-hint">Select which groups can access specific admin features.</p>
        
        <div id="access-control-container" class="access-grid">
            </div>
    </div>

    <div class="section-header-row">
        <h3 class="subsection-title">System Users</h3>
        <input type="text" id="user-search" class="form-control" placeholder="Search users..." style="width: 200px;">
    </div>

    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Last Login</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="user-table-body">
                </tbody>
        </table>
    </div>
</div>