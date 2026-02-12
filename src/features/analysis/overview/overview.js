import { StechAPI } from '../../../api.js';
import Chart from 'chart.js/auto';

export const OverviewFeature = {
    chartInstance: null,

    async load(params) {
        try {
            const data = await StechAPI.request('get', `/api/analysis/overview?${params}`);
            this.renderStats(data);
            this.renderChart(data.trend);
        } catch (err) { console.error(err); }
    },

    renderStats(data) {
        document.getElementById('ov-total').innerText = data.total_hours;
        document.getElementById('ov-reg').innerText = data.stats.regular_hours;
        document.getElementById('ov-pto').innerText = data.stats.pto_hours;
        document.getElementById('ov-ot').innerText = data.stats.overtime_hours;
    },

    renderChart(trend) {
        const ctx = document.getElementById('chart-daily-trend');
        if (!ctx) return;

        if (this.chartInstance) this.chartInstance.destroy();

        this.chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [{
                    label: 'Hours Worked',
                    data: trend.values,
                    borderColor: '#0082c9',
                    backgroundColor: 'rgba(0, 130, 201, 0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
};