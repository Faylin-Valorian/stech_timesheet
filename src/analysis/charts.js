/**
 * Analysis Charts Module
 * Handles Overview, Travel, and Job-specific Chart.js visualizations.
 */
export const AnalysisCharts = {
    instances: { daily: null, travelState: null, travelCounty: null, jobs: null },

    /**
     * Helper to determine chart theme based on Nextcloud dark mode
     */
    getTheme() {
        const isDark = document.body.classList.contains('theme--dark');
        return { 
            text: isDark ? '#ddd' : '#666',
            grid: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)'
        };
    },

    /**
     * Renders the main Daily Hours trend line
     */
    renderOverview(trend) {
        const ctx = document.getElementById('chart-daily').getContext('2d');
        if (this.instances.daily) this.instances.daily.destroy();
        const theme = this.getTheme();

        this.instances.daily = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [{
                    label: 'Hours Worked', 
                    data: trend.values,
                    borderColor: '#0082c9', 
                    backgroundColor: 'rgba(0, 130, 201, 0.2)',
                    fill: true, 
                    tension: 0.3, 
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true, 
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text } },
                    x: { grid: { display: false }, ticks: { color: theme.text } }
                },
                plugins: { legend: { display: false } }
            }
        });
    },

    /**
     * Renders the State and County doughnut charts for travel visits
     */
    renderTravelDoughnuts(states, counties) {
        const theme = this.getTheme();
        
        // State Doughnut
        const ctxS = document.getElementById('chart-travel-state');
        if (ctxS) {
            if (this.instances.travelState) this.instances.travelState.destroy();
            this.instances.travelState = new Chart(ctxS, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(states),
                    datasets: [{
                        data: Object.values(states),
                        backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF']
                    }]
                },
                options: {
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { color: theme.text, boxWidth: 10 } } }
                }
            });
        }

        // County Doughnut
        const ctxC = document.getElementById('chart-travel-county');
        if (ctxC) {
            if (this.instances.travelCounty) this.instances.travelCounty.destroy();
            this.instances.travelCounty = new Chart(ctxC, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(counties),
                    datasets: [{
                        data: Object.values(counties),
                        backgroundColor: ['#FF9F40', '#9966FF', '#4BC0C0', '#36A2EB', '#FF6384']
                    }]
                },
                options: {
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { color: theme.text, boxWidth: 10 } } }
                }
            });
        }
    },

    /**
     * Renders Job breakdown chart and populates the data table
     */
    renderJobTable(jobs, total) {
        const ctx = document.getElementById('chart-jobs');
        if (!ctx) return;
        if (this.instances.jobs) this.instances.jobs.destroy();
        const theme = this.getTheme();

        this.instances.jobs = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: jobs.map(j => j.name),
                datasets: [{
                    data: jobs.map(j => j.hours),
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40']
                }]
            },
            options: {
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { color: theme.text } } }
            }
        });

        const tbody = document.getElementById('job-table-body');
        if (!tbody) return;
        tbody.innerHTML = jobs.length === 0 
            ? '<tr><td colspan="3" style="text-align:center;">No data available</td></tr>'
            : jobs.map(j => {
                const pct = total > 0 ? ((j.hours / total) * 100).toFixed(1) : 0;
                return `<tr><td>${j.name}</td><td>${j.hours.toFixed(2)}</td><td>${pct}%</td></tr>`;
            }).join('');
    }
};