import { StechAPI } from '../../../api.js';
import Chart from 'chart.js/auto';

export const JobProfitabilityFeature = {
    chart: null,
    cachedJobs: [],
    
    async load(params) {
        try {
            const data = await StechAPI.request('get', `/api/analysis/jobs/profitability?${params}`);
            this.cachedJobs = data.jobs;
            
            this.setupSearch();
            // Default to 'all' or currently selected value if exists
            const currentSearch = document.getElementById('profit-job-search')?.value || 'all';
            this.renderGauge(currentSearch);
        } catch (err) {
            console.error("Failed to load Profitability", err);
        }
    },

    setupSearch() {
        const list = document.getElementById('profit-job-list');
        if (!list) return;

        // Populate Datalist
        list.innerHTML = this.cachedJobs.map(j => `<option value="${j.name}">`).join('');
        
        // Bind Input Listener
        const input = document.getElementById('profit-job-search');
        if (input) {
            // Remove old listener to prevent duplicates if re-initialized
            const newNode = input.cloneNode(true);
            input.parentNode.replaceChild(newNode, input);
            
            newNode.onchange = () => this.renderGauge(newNode.value || 'all');
        }
    },

    renderGauge(selectedName) {
        const ctx = document.getElementById('chart-profit-gauge');
        if (!ctx) return;

        // 1. Calculate Aggregates
        let revenue = 0, labor = 0, expenses = 0;
        let jobTitle = "All Active Jobs";

        if (selectedName && selectedName !== 'all' && selectedName !== 'All Jobs') {
            const job = this.cachedJobs.find(j => j.name === selectedName);
            if (job) {
                revenue = job.revenue;
                labor = job.labor_cost;
                expenses = job.budget + job.actual_expenses;
                jobTitle = job.name;
            }
        } else {
            this.cachedJobs.forEach(j => {
                revenue += j.revenue;
                labor += j.labor_cost;
                expenses += (j.budget + j.actual_expenses);
            });
        }

        const totalCost = labor + expenses;
        const profit = revenue - totalCost;

        // 2. Update Text Display
        const displayEl = document.getElementById('profit-display-text');
        if (displayEl) {
            const color = profit >= 0 ? '#2ecc71' : '#e74c3c'; // Green or Red
            displayEl.innerHTML = `
                <div style="text-align:center">
                    <h4 style="margin:0 0 10px 0;">${jobTitle}</h4>
                    <div style="font-size:24px; font-weight:bold; color:${color}">
                        $${profit.toLocaleString(undefined, {minimumFractionDigits: 2})} Net Profit
                    </div>
                    <div style="font-size:12px; opacity:0.8; margin-top:5px;">
                        Rev: $${revenue.toLocaleString()} | 
                        Exp: $${expenses.toLocaleString()} | 
                        Labor: $${labor.toLocaleString()}
                    </div>
                </div>
            `;
        }

        // 3. Render Chart
        if (this.chart) this.chart.destroy();

        // Data structure for the gauge (Profit segment vs Cost segment)
        // If profit is negative, the chart logic gets tricky, so we simplify visualization:
        // Segments: [Profit (Green), Labor (Blue), Expenses (Orange)]
        // If Profit < 0, we show 0 for profit segment.
        
        const chartData = [Math.max(0, profit), labor, expenses];
        const chartColors = ['#2ecc71', '#3498db', '#e67e22'];
        
        // Handle "No Data" case
        if (revenue === 0 && totalCost === 0) {
            chartData[0] = 1; // Dummy fill
            chartColors[0] = '#eee'; 
        }

        this.chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Net Profit', 'Labor Cost', 'Expenses'],
                datasets: [{
                    data: chartData,
                    backgroundColor: chartColors,
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                rotation: -90,
                circumference: 180, // Half circle
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let val = context.raw;
                                if (context.label === 'Net Profit' && profit < 0) val = profit; // Show actual negative in tooltip
                                return ` ${context.label}: $${val.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });
    }
};