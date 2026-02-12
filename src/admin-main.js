import './admin.scss';

// Note the change from Plural (HolidaysFeature) to Singular (HolidayFeature)
// based on your error logs suggesting "possible exports: HolidayFeature"
import { PayrollFeature } from './features/admin/payroll/payroll.js';
import { HolidayFeature } from './features/admin/holidays/holidays.js'; 
import { LocationFeature } from './features/admin/locations/locations.js'; 
import { JobFeature } from './features/admin/jobs/jobs.js'; 
import { UserFeature } from './features/admin/users/users.js'; 

document.addEventListener('DOMContentLoaded', () => {
    // Initialize
    PayrollFeature.init();
    HolidayFeature.init();
    LocationFeature.init();
    JobFeature.init();
    UserFeature.init();
});