import Chart from 'chart.js/auto';

/**
 * StechTimesheet.AnalysisGauges
 */
export const AnalysisGauges = {
    chart: null,

    update(jobs, selectedJobName) {
        const ctx = document.getElementById('chart-profitability-gauge');
        if (!ctx) return;

        let revenue = 0;
        let laborCost = 0;
        let expenses = 0; // Budget + Actuals

        // 1. Aggregate Data
        if (!selectedJobName || selectedJobName === 'all' || selectedJobName === 'All Jobs') {
            if (jobs && jobs.length > 0) {
                jobs.forEach(j => {
                    revenue += parseFloat(j.revenue || 0);
                    laborCost += parseFloat(j.labor_cost || 0);
                    // Add Fixed Budget AND Variable Allocated Expenses
                    expenses += (parseFloat(j.budget || 0) + parseFloat(j.actual_expenses || 0));
                });
                const titleEl = document.getElementById('profit-job-title');
                if (titleEl) titleEl.innerText = "All Active Jobs (Combined)";
            }
        } else {
            const job = jobs ? jobs.find(j => j.name === selectedJobName) : null;
            if (job) {
                revenue = parseFloat(job.revenue || 0);
                laborCost = parseFloat(job.labor_cost || 0);
                expenses = (parseFloat(job.budget || 0) + parseFloat(job.actual_expenses || 0));
                const titleEl = document.getElementById('profit-job-title');
                if (titleEl) titleEl.innerText = job.name;
            }
        }

        // 2. Calculations
        // Total Cost = Labor + (Budget + Actuals)
        const totalCost = laborCost + expenses;
        const profit = revenue - totalCost;
        
        // 3. Update Text Display (IMMEDIATE)
        const displayEl = document.getElementById('gauge-value-display');
        if (displayEl) {
            displayEl.innerHTML = `
                <div style="text-align:center">
                    <div style="font-size:24px; font-weight:bold; color:${profit >= 0 ? '#2ecc71' : '#e74c3c'}">
                        $${profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} Net Profit
                    </div>
                    <div style="font-size:12px; color:var(--color-text-light); margin-top:5px;">
                        Rev: $${revenue.toLocaleString(undefined, {maximumFractionDigits:0})} | 
                        Exp: $${expenses.toLocaleString(undefined, {maximumFractionDigits:0})} | 
                        Labor: $${laborCost.toLocaleString(undefined, {maximumFractionDigits:0})}
                    </div>
                </div>
            `;
        }

        // 4. Chart Rendering
        if (typeof Chart === 'undefined') {
            console.warn("Chart.js not loaded");
            return;
        }

        if (this.chart) this.chart.destroy();

        // Grey ring if empty
        let chartData = [Math.max(0, profit), laborCost, expenses];
        let chartColors = ['#2ecc71', '#3498db', '#e67e22']; // Green, Blue, Orange
        let labels = ['Profit', 'Labor Cost', 'Expenses'];

        if (revenue === 0 && totalCost === 0) {
            chartData = [1]; 
            chartColors = ['#444']; 
            labels = ['No Data'];
        }

        this.chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
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
                cutout: '80%',
                rotation: -90,
                circumference: 180,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: (revenue !== 0 || totalCost !== 0),
                        callbacks: {
                            label: function(context) {
                                let val = context.raw;
                                return ' $' + val.toLocaleString();
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'centerText',
                beforeDraw: function(chart) {
                    const width = chart.width, height = chart.height, ctx = chart.ctx;
                    ctx.restore();
                    
                    const fontSize = (height / 114).toFixed(2);
                    ctx.font = "bold " + fontSize + "em sans-serif";
                    ctx.textBaseline = "middle";
                    
                    let text = "--%";
                    let fillStyle = '#888';
                    
                    if (revenue !== 0 || totalCost !== 0) {
                        const marginVal = revenue > 0 ? ((profit / revenue) * 100) : 0;
                        text = marginVal.toFixed(1) + "%";
                        fillStyle = profit >= 0 ? '#2ecc71' : '#e74c3c';
                    }

                    const textX = Math.round((width - ctx.measureText(text).width) / 2);
                    const textY = height / 1.5; 
                    
                    ctx.fillStyle = fillStyle;
                    ctx.fillText(text, textX, textY);
                    ctx.save();
                }
            }]
        });
    }
};