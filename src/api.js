import { generateUrl } from '@nextcloud/router';
import axios from '@nextcloud/axios';

/**
 * API Module for Stech Timesheet
 * Centralized communication layer using Nextcloud's Axios wrapper.
 */
export const StechAPI = {

    /**
     * Retrieves the target user UID from the global hidden input.
     */
    getTargetUser() {
        return document.getElementById('global-target-user')?.value || null;
    },

    /**
     * Core Request Handler
     */
    async request(method, endpoint, data = null) {
        let url = generateUrl('/apps/stech_timesheet' + endpoint);
        const target = this.getTargetUser();
        const isGet = method.toLowerCase() === 'get';
        
        // FIX: Handle Target User injection correctly for GET vs POST
        if (target) {
            if (isGet) {
                // For GET: Add to params object so Axios merges it into the Query String
                data = data || {};
                data['target_user'] = target;
            } else {
                // For POST/PUT: Append to URL string
                url += (url.includes('?') ? '&' : '?') + 'target_user=' + target;
            }
        }

        try {
            let payload = null;

            // Manual Array Handling for PHP (Only for non-GET)
            if (!isGet && data) {
                payload = new URLSearchParams();
                for (const key in data) {
                    if (Array.isArray(data[key])) {
                        data[key].forEach(val => payload.append(`${key}[]`, val));
                    } else {
                        if (data[key] !== null && data[key] !== undefined) {
                            payload.append(key, data[key]);
                        }
                    }
                }
            }

            const response = await axios({
                method: method.toLowerCase(),
                url: url,
                // POST/PUT use 'data'
                data: (!isGet) ? payload : null,
                // GET uses 'params'
                params: (isGet && data) ? data : null
            });
            return response.data;
        } catch (error) {
            console.error(`StechAPI Error on ${endpoint}:`, error);
            const errorMessage = error.response?.data?.error || error.response?.data?.message || "An unexpected error occurred.";
            if (window.OCP && window.OCP.Toast) {
                window.OCP.Toast.error(errorMessage);
            }
            throw error;
        }
    },

    // =========================================================
    //  1. TIMESHEET ENDPOINTS
    // =========================================================
    
    getTimesheets(start, end, archive = 0) {
        return this.request('get', '/api/timesheets', { start, end, archive });
    },

    getTimesheet(id) {
        return this.getTimesheetDetails(id);
    },

    getTimesheetDetails(id) {
        return this.request('get', `/api/timesheets/${id}`);
    },

    saveTimesheet(formData) {
        return this.request('post', '/api/timesheets', formData);
    },

    deleteTimesheet(id) {
        return this.request('delete', `/api/timesheets/${id}`);
    },

    restoreTimesheet(id) {
        return this.request('post', `/api/timesheets/${id}/restore`);
    },

    getAttributes() {
        return this.request('get', '/api/attributes');
    },

    getCounties(stateAbbr) {
        return this.request('get', `/api/counties/${stateAbbr}`);
    },

    // =========================================================
    //  2. ANALYSIS ENDPOINTS
    // =========================================================
    getAnalysisFilters() {
        return this.request('get', '/api/analysis/filters');
    },

    getAnalysisStats(params) {
        return this.request('get', '/api/analysis/stats', params);
    },

    // =========================================================
    //  3. ADMIN ENDPOINTS
    // =========================================================
    getAdminUsers() {
        return this.request('get', '/api/admin/users');
    },

    toggleUserStatus(uid) {
        return this.request('post', '/api/admin/users/toggle', { uid });
    },

    getAdminGroups() {
        return this.request('get', '/api/admin/groups');
    },

    getAdminAccess() {
        return this.request('get', '/api/admin/access');
    },

    saveAccessRule(rule_key, allowed_groups) {
        return this.request('post', '/api/admin/access', { rule_key, allowed_groups });
    },

    getAdminSettings() {
        return this.request('get', '/api/admin/settings');
    },

    saveAdminSetting(key, value) {
        return this.request('post', '/api/admin/settings', { key, value });
    },

    getAdminHolidays() {
        return this.request('get', '/api/admin/holidays');
    },

    saveHoliday(payload) {
        return this.request('post', '/api/admin/holidays', payload);
    },

    toggleHoliday(id) {
        return this.request('post', `/api/admin/holidays/${id}/toggle`);
    },

    getAdminJobs() {
        return this.request('get', '/api/admin/jobs');
    },

    saveJob(payload) {
        return this.request('post', '/api/admin/jobs', payload);
    },

    toggleJob(id) {
        return this.request('post', `/api/admin/jobs/${id}/toggle`);
    },

    getAdminStates() {
        return this.request('get', '/api/admin/states');
    },

    toggleState(id) {
        return this.request('post', `/api/admin/states/${id}/toggle`);
    },

    getAdminCounties(abbr) {
        return this.request('get', `/api/admin/counties/${abbr}`);
    },

    toggleCounty(id) {
        return this.request('post', `/api/admin/counties/${id}/toggle`);
    }
};

window.StechAPI = StechAPI;
window.StechTimesheet = window.StechTimesheet || {};
window.StechTimesheet.API = StechAPI;