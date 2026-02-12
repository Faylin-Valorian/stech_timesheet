import './analysis.scss';
import { DashboardFeature } from './features/analysis/dashboard/dashboard.js';

document.addEventListener('DOMContentLoaded', () => {
    // The Dashboard feature handles the tab logic and sub-feature loading
    DashboardFeature.init();
});