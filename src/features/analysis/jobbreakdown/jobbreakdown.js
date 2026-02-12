import { StechAPI } from '../../../api.js';
import Chart from 'chart.js/auto';

export const JobBreakdownFeature = {
    chart: null,

    async load(params) {
        try {
            const data = await StechAPI.request('get', `/api/analysis/jobs/breakdown?${params}`);
            this.renderTable(data.jobs);
            this.renderChart(data.jobs);
        } catch (err) {
            console.error("Failed to load Job Breakdown", err);
        }
    },

    renderTable(jobs) {
        const tbody = document.getElementById('job-breakdown-body');
        if (!tbody) return;

        if (jobs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center">No job activity found.</td></tr>';
            return;
        }

        tbody.innerHTML = jobs.map(j => `
            <tr>
                <td><strong>${j.name}</strong></td>
                <td class="text-right">${j.hours}</td>
                <td class="text-right">${j.percent}%</td>
            </tr>
        `).join('');
    },

    renderChart(jobs) {
        const ctx = document.getElementById('chart-job-breakdown');
        if (!ctx) return;

        if (this.chart) this.chart.destroy();
        
        // Theme Colors
        const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];

        this.chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: jobs.map(j => j.name),
                datasets: [{
                    data: jobs.map(j => j.hours),
                    backgroundColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }
};