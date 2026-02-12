<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\JobBreakdown\Service;

class JobBreakdownService {

    public function process(array $rows): array {
        $jobs = [];
        $totalHours = 0.0;

        foreach ($rows as $row) {
            if (empty($row['activity_description'])) continue;

            $hours = (float)$row['time_total'];
            $percent = (float)($row['activity_percent'] ?? 0);
            $jobHours = $hours * ($percent / 100);
            
            $totalHours += $jobHours;

            $name = $row['job_name'] ?? $row['activity_description'];

            if (!isset($jobs[$name])) {
                $jobs[$name] = ['name' => $name, 'hours' => 0.0, 'percent' => 0.0];
            }
            $jobs[$name]['hours'] += $jobHours;
        }

        // Calculate Percentages
        foreach ($jobs as &$job) {
            if ($totalHours > 0) {
                $job['percent'] = round(($job['hours'] / $totalHours) * 100, 1);
            }
            $job['hours'] = round($job['hours'], 2);
        }

        // Sort by Hours Descending
        usort($jobs, function($a, $b) {
            return $b['hours'] <=> $a['hours'];
        });

        return [
            'jobs' => array_values($jobs),
            'total_hours' => round($totalHours, 2)
        ];
    }
}