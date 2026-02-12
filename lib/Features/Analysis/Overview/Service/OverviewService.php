<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\Overview\Service;

class OverviewService {

    /**
     * Processes raw timesheet rows into Overview Stats and Trend Chart data
     */
    public function process(array $rows): array {
        $totalHours = 0.0; 
        $ptoHours = 0.0;
        $trend = []; 
        $processedTids = [];
        
        // Financial Accumulators
        $jobsFound = []; // To ensure we only count fixed revenue/budget ONCE per job type
        $totalLaborCost = 0.0;
        $totalAllocatedExpenses = 0.0;

        foreach ($rows as $row) {
            $tid = $row['timesheet_id'];
            $hours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            
            // --- 1. General Time Stats (Per Timesheet) ---
            if (!in_array($tid, $processedTids)) {
                $totalHours += $hours;
                $trend[$date] = ($trend[$date] ?? 0) + $hours;
                $processedTids[] = $tid;
            }

            // --- 2. Financials & PTO (Per Activity Split) ---
            if (!empty($row['activity_description'])) {
                $percent = (float)($row['activity_percent'] ?? 0);
                $jobHours = $hours * ($percent / 100);

                // PTO Check
                if (((int)($row['is_pto'] ?? 0)) === 1) {
                    $ptoHours += $jobHours;
                }

                // Financials
                $jobName = $row['job_name'] ?? $row['activity_description'];
                $hourlyRate = (float)($row['job_hourly_cost'] ?? 0);
                $entryExpenses = (float)($row['travel_extra_expenses'] ?? 0);
                
                $laborCost = $jobHours * $hourlyRate;
                $allocatedExp = $entryExpenses * ($percent / 100);

                $totalLaborCost += $laborCost;
                $totalAllocatedExpenses += $allocatedExp;

                // Track Unique Jobs for Fixed Revenue/Budget calculation
                if (!isset($jobsFound[$jobName])) {
                    $jobsFound[$jobName] = [
                        'revenue' => (float)($row['job_revenue'] ?? 0),
                        'budget' => (float)($row['job_expense_budget'] ?? 0)
                    ];
                }
            }
        }

        // --- 3. Final Aggregations ---
        $totalFixedRevenue = 0.0;
        $totalFixedBudgets = 0.0;

        foreach ($jobsFound as $j) {
            $totalFixedRevenue += $j['revenue'];
            $totalFixedBudgets += $j['budget'];
        }

        // Profit = Total Revenue - (Labor + Fixed Budgets + Actual Expenses)
        $globalProfit = $totalFixedRevenue - ($totalLaborCost + $totalFixedBudgets + $totalAllocatedExpenses);

        // Overtime Calculation (> 80 hours in period)
        $workedHours = $totalHours - $ptoHours;
        $regularHours = ($workedHours > 80) ? 80.0 : $workedHours;
        $overtimeHours = ($workedHours > 80) ? ($workedHours - 80.0) : 0.0;

        // Sort Trend by Date
        ksort($trend);

        return [
            'total_hours' => round($totalHours, 2),
            'stats' => [
                'regular_hours' => round($regularHours, 2),
                'overtime_hours' => round($overtimeHours, 2),
                'pto_hours' => round($ptoHours, 2),
                'gross_pay' => round($totalLaborCost, 2),
                'revenue' => round($totalFixedRevenue, 2),
                'profit' => round($globalProfit, 2)
            ],
            'trend' => [
                'labels' => array_keys($trend), 
                'values' => array_values($trend)
            ]
        ];
    }
}