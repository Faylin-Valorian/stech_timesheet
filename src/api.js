import { generateUrl } from '@nextcloud/router';
import axios from '@nextcloud/axios';

/**
 * API Module for Stech Timesheet
 * Centralized communication layer using Nextcloud's Axios wrapper.
 */
export const StechAPI = {

    /**
     * Retrieves the target user UID from the global hidden input.
     * Used for Admin/Manager views to fetch another user's data.
     */
    getTargetUser() {
        return document.getElementById('global-target-user')?.value || null;
    },

    /**
     * Core Request Handler
     * FIX: Handles array serialization for PHP backends.
     */
    async request(method, endpoint, data = null) {
        let url = generateUrl('/apps/stech_timesheet' + endpoint);
        const target = this.getTargetUser();
        
        if (target) {
            url += (url.includes('?') ? '&' : '?') + 'target_user=' + target;
        }

        try {
            let payload = null;

            if (method.toLowerCase() !== 'get' && data) {
                // Use URLSearchParams for POST/PUT requests
                payload = new URLSearchParams();
                for (const key in data) {
                    if (Array.isArray(data[key])) {
                        // FIX: Append array keys with [] so PHP parses them as arrays
                        data[key].forEach(val => payload.append(`${key}[]`, val));
                    } else {
                        payload.append(key, data[key]);
                    }
                }
            }

            const response = await axios({
                method: method.toLowerCase(),
                url: url,
                data: payload,
                params: (method.toLowerCase() === 'get' && data) ? data : null
            });
            return response.data;
        } catch (error) {
            console.error(`StechAPI Error on ${endpoint}:`, error);
            
            const errorMessage = error.response?.data?.error || error.response?.data?.message || "An unexpected error occurred.";
            
            if (window.OCP && window.OCP.Toast) {
                window.OCP.Toast.error(errorMessage);
            } else {
                console.error("Critical API Failure: " + errorMessage);
            }
            
            throw error;
        }
    },

    // =========================================================
    //  1. TIMESHEET ENDPOINTS
    // =========================================================
    getTimesheets(start, end) {
        return this.request('get', '/api/timesheets', { start, end });
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