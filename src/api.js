import { generateUrl } from '@nextcloud/router';
import axios from '@nextcloud/axios';

export const StechAPI = {
    /**
     * Generic wrapper for Nextcloud API requests.
     * Automatically prepends the app URL base.
     * * @param {string} method - 'get', 'post', 'put', 'delete'
     * @param {string} route - The API route (e.g., '/api/entry/save')
     * @param {object|null} data - JSON data for POST/PUT
     */
    async request(method, route, data = null) {
        try {
            // Ensure we are hitting the app's endpoint
            // Input:  /api/entry/save
            // Output: /apps/stech_timesheet/api/entry/save
            const url = generateUrl(`/apps/stech_timesheet${route}`);

            const response = await axios({
                method: method,
                url: url,
                data: data
            });

            return response.data;
        } catch (error) {
            console.error(`API Error [${method} ${route}]:`, error);
            
            // Standardize error message extraction
            const msg = error.response?.data?.error || error.message || "Unknown error occurred";
            throw new Error(msg);
        }
    }
};