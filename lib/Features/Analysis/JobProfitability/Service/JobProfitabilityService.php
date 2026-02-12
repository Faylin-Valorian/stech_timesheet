<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\JobProfitability\Service;

class JobProfitabilityService {

    public function process(array $rows): array {
        $jobs = [];

        foreach ($rows as $row) {
            if (empty($row['activity_description'])) continue;

            $name = $row['job_name'] ?? $row['activity_description'];
            
            // Initialize if missing
            if (!isset($jobs[$name])) {
                $jobs[$name] = [
                    'name' => $name,
                    'revenue' => (float)($row['job_revenue'] ?? 0),
                    'budget' => (float)($row['job_expense_budget'] ?? 0),
                    'labor_cost' => 0.0,
                    'actual_expenses' => 0.0
                ];
            }

            // Calculations
            $hours = (float)$row['time_total'];
            $percent = (float)($row['activity_percent'] ?? 0);
            $jobHours = $hours * ($percent / 100);
            
            $hourlyRate = (float)($row['job_hourly_cost'] ?? 0);
            $entryExpenses = (float)($row['travel_extra_expenses'] ?? 0);

            $jobs[$name]['labor_cost'] += ($jobHours * $hourlyRate);
            $jobs[$name]['actual_expenses'] += ($entryExpenses * ($percent / 100));
        }

        // Final Profit Calculation per Job
        foreach ($jobs as &$job) {
            $totalCost = $job['labor_cost'] + $job['budget'] + $job['actual_expenses'];
            $job['profit'] = $job['revenue'] - $totalCost;
            $job['margin_percent'] = ($job['revenue'] > 0) ? round(($job['profit'] / $job['revenue']) * 100, 1) : 0;
        }

        return ['jobs' => array_values($jobs)];
    }
}