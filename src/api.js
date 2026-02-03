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
     * Automatically handles URLs, Target Users, and Nextcloud Request Tokens.
     */
    async request(method, endpoint, data = null) {
        let url = generateUrl('/apps/stech_timesheet' + endpoint);
        const target = this.getTargetUser();
        
        // Append target_user to query string if present
        if (target) {
            url += (url.includes('?') ? '&' : '?') + 'target_user=' + target;
        }

        try {
            const response = await axios({
                method,
                url,
                // If it's a POST/PUT and we have data, send as URLSearchParams
                data: (method.toLowerCase() !== 'get' && data) ? new URLSearchParams(data) : null,
                // If it's a GET and we have data (params), axios handles it via 'params' key
                params: (method.toLowerCase() === 'get' && data) ? data : null
            });
            return response.data;
        } catch (error) {
            console.error(`API Error on ${endpoint}:`, error);
            throw error;
        }
    },

    // =========================================================
    //  1. TIMESHEET ENDPOINTS
    // =========================================================
    getTimesheets(start, end) {
        return this.request('get', '/api/timesheets', { start, end });
    },

    getTimesheetDetails(id) {
        return this.request('get', `/api/timesheets/${id}`);
    },

    saveTimesheet(formData) {
        return this.request('post', '/api/timesheets', formData);
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
    /**
     * Fetches filtering options (Users, Jobs, States) for Analysis
     */
    getAnalysisFilters() {
        return this.request('get', '/api/analysis/filters');
    },

    /**
     * Fetches stats based on period and target user
     * queryParams: { period, start, end, target_user }
     */
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