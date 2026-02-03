/**
 * Analysis Gauges Module
 * Handles the Job Profitability Needle visualization.
 */
export const AnalysisGauges = {
    instance: null,

    /**
     * Updates the gauge and value displays based on job data and filter
     */
    update(jobs, filterName) {
        const canvas = document.getElementById('chart-profitability-gauge');
        if (!canvas) return;

        let revenue = 0, expenses = 0, laborCost = 0, profit = 0, label = "";

        // Calculate totals or specific job data
        if (filterName === 'All Jobs' || filterName === 'all' || filterName === '') {
            jobs.forEach(j => {
                revenue += j.revenue; 
                expenses += j.budget; 
                laborCost += (j.hours * j.hourly_cost);
            });
            profit = revenue - expenses - laborCost;
            label = "$" + profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " Net Profit";
        } else {
            const job = jobs.find(x => x.name === filterName);
            if (job) {
                revenue = job.revenue; 
                expenses = job.budget; 
                laborCost = job.hours * job.hourly_cost;
                profit = revenue - expenses - laborCost;
                label = "$" + profit.toLocaleString() + " Net Profit";
            }
        }

        // Update Text Display
        const displayEl = document.getElementById('gauge-value-display');
        if (displayEl) {
            displayEl.innerHTML = `
                <div style="text-align:center">
                    <div style="font-size:24px; font-weight:bold; color:${profit >= 0 ? '#2ecc71' : '#e74c3c'}">${label}</div>
                    <div style="font-size:12px; color:#888; margin-top:5px;">
                        Rev: $${revenue.toLocaleString()} | Exp: $${expenses.toLocaleString()} | Labor: $${laborCost.toLocaleString()}
                    </div>
                </div>
            `;
        }

        if (this.instance) this.instance.destroy();

        // Custom Needle Plugin logic
        const needlePlugin = this.getNeedlePlugin(revenue, profit);

        this.instance = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: ['Loss', 'Break Even', 'Profitable'],
                datasets: [{
                    data: [33, 33, 34], 
                    backgroundColor: ['#e9322d', '#ffd60a', '#46ba6f'], 
                    borderWidth: 0
                }]
            },
            options: {
                rotation: -90, 
                circumference: 180, 
                responsive: true, 
                maintainAspectRatio: false, 
                cutout: '70%',
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            },
            plugins: [needlePlugin]
        });
    },

    /**
     * Logic for the needle positioning based on profit margins
     */
    getNeedlePlugin(revenue, profit) {
        const isDark = document.body.classList.contains('theme--dark');
        const textColor = isDark ? '#ddd' : '#666';

        return {
            id: 'needle',
            afterDatasetDraw(chart) {
                const { ctx, chartArea: { width, height } } = chart;
                ctx.save();
                let ratio = 0;
                
                if (revenue > 0) {
                    const margin = profit / revenue; 
                    if (margin < 0) {
                        let lossSeverity = Math.min(Math.abs(margin), 0.5) / 0.5; 
                        ratio = 0.33 - (lossSeverity * 0.33); 
                    } else {
                        let success = Math.min(margin, 0.5) / 0.5; 
                        ratio = 0.33 + (success * 0.67);
                    }
                } else {
                    ratio = profit < 0 ? 0.1 : 0.5;
                }
                
                if (ratio < 0) ratio = 0; if (ratio > 1) ratio = 1;

                const angle = Math.PI + (ratio * Math.PI);
                const cx = width / 2;
                const cy = chart._metasets[0].data[0].y;

                ctx.translate(cx, cy);
                ctx.rotate(angle);
                ctx.beginPath();
                ctx.moveTo(0, -2);
                ctx.lineTo(height - (ctx.canvas.offsetTop + 40), 0);
                ctx.lineTo(0, 2);
                ctx.fillStyle = textColor;
                ctx.fill();
                ctx.rotate(-angle);
                ctx.beginPath();
                ctx.arc(0, 0, 5, 0, 10);
                ctx.fillStyle = textColor;
                ctx.fill();
                ctx.restore();
            }
        };
    }
};