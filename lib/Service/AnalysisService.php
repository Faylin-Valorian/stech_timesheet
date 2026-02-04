<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Service;

use OCA\StechTimesheet\Db\AnalysisMapper;
use OCA\StechTimesheet\Db\TimesheetMapper;
use OCP\IGroupManager;
use OCP\IUserSession;

class AnalysisService {
    private $analysisMapper;
    private $timesheetMapper;
    private $groupManager;
    private $userSession;

    public function __construct(AnalysisMapper $analysisMapper, TimesheetMapper $timesheetMapper, IGroupManager $groupManager, IUserSession $userSession) {
        $this->analysisMapper = $analysisMapper;
        $this->timesheetMapper = $timesheetMapper;
        $this->groupManager = $groupManager;
        $this->userSession = $userSession;
    }

    /**
     * Checks if the current user has access based on Admin Settings rules
     */
    public function checkAccess(string $ruleKey): bool {
        $user = $this->userSession->getUser();
        if (!$user) return false;
        if ($this->groupManager->isAdmin($user->getUID())) return true;

        $settings = $this->timesheetMapper->getAdminSettings();
        $allowedGroups = json_decode($settings['rule_' . $ruleKey] ?? '[]', true);
        
        $userGroups = $this->groupManager->getUserGroupIds($user);
        foreach ($userGroups as $gid) {
            if (in_array($gid, $allowedGroups)) return true;
        }
        return false;
    }

    /**
     * Calculates start/end dates based on pay frequency settings
     */
    public function getPayrollDateRange(string $period): array {
        $settings = $this->timesheetMapper->getAdminSettings();
        $freq = (int)($settings['pay_frequency'] ?? 14);
        $refDate = new \DateTime($settings['pay_start_date'] ?? '2024-01-01');
        $now = new \DateTime();
        
        $diff = $now->diff($refDate)->days;
        if ($now < $refDate) $diff = -$diff;
        $cycles = (int)floor($diff / $freq);
        
        $currentStart = clone $refDate;
        $currentStart->modify('+' . ($cycles * $freq) . ' days');
        $currentEnd = (clone $currentStart)->modify('+' . ($freq - 1) . ' days');

        if ($period === 'this_pay_period') return [$currentStart, $currentEnd];
        if ($period === 'last_pay_period') {
            return [(clone $currentStart)->modify('-' . $freq . ' days'), (clone $currentStart)->modify('-1 day')];
        }
        if ($period === 'this_month') return [new \DateTime('first day of this month'), new \DateTime('last day of this month')];
        if ($period === 'ytd') return [new \DateTime('first day of January this year'), new \DateTime('now')];
        
        return [$currentStart, $currentEnd];
    }

    /**
     * Aggregates raw DB rows into statistics for the frontend
     */
    public function aggregateData(array $results, array $perms): array {
        $totalHours = 0.0; 
        $ptoHours = 0.0;
        $trend = []; 
        $jobs = []; 
        $states = []; 
        $counties = []; 
        $processed = [];
        
        // Financial tracking
        $totalGross = 0.0;
        $totalRevenue = 0.0;
        
        $travel = [
            'total_miles' => 0, 
            'per_diem_days' => 0, 
            'overnight_stays' => 0, 
            'total_expenses' => 0.0
        ];

        // Fetch enabled states from Mapper to flag them in UI
        $enabledStateList = $this->timesheetMapper->getEnabledStates();
        $enabledStates = array_column($enabledStateList, 'state_name');

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $hours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            
            // Process Timesheet-Level Data (Avoid duplicates if multiple activities exist per timesheet)
            if (!in_array($tid, $processed)) {
                $totalHours += $hours;
                $trend[$date] = ($trend[$date] ?? 0) + $hours;

                // Handle Locations
                if ($perms['location']) {
                    $state = $row['full_state_name'] ?? $row['travel_state'] ?? 'Unknown';
                    $isStateEnabled = in_array($state, $enabledStates);
                    
                    $states[$state] = [
                        'count' => ($states[$state]['count'] ?? 0) + 1,
                        'is_enabled' => $isStateEnabled
                    ];

                    $county = trim(str_ireplace(' County', '', $row['travel_county'] ?? ''));
                    if ($county) {
                        $key = $state . '|' . $county;
                        $counties[$key] = [
                            'count' => ($counties[$key]['count'] ?? 0) + 1,
                            'is_enabled' => $isStateEnabled 
                        ];
                    }
                }

                // Handle Travel Stats
                if ($perms['travel']) {
                    $travel['total_miles'] += (int)($row['travel_miles'] ?? 0);
                    if (($row['travel_per_diem'] ?? 0) == 1) $travel['per_diem_days']++;
                    if (($row['travel_overnight'] ?? 0) == 1) $travel['overnight_stays']++;
                    
                    $expense = (float)($row['travel_extra_expenses'] ?? 0);
                    $travel['total_expenses'] += $expense;
                }
                $processed[] = $tid;
            }

            // Process Activity-Level Data
            if (!empty($row['activity_description'])) {
                $percent = (float)($row['activity_percent'] ?? 0);
                $jobHours = $hours * ($percent / 100);

                // Accumulate PTO if this job is flagged as PTO
                if (($row['is_pto'] ?? 0) == 1) {
                    $ptoHours += $jobHours;
                }

                if ($perms['jobs']) {
                    $name = $row['activity_description'];
                    $revenueRate = (float)($row['job_revenue'] ?? 0);
                    $hourlyCost = (float)($row['job_hourly_cost'] ?? 0);

                    if (!isset($jobs[$name])) {
                        $jobs[$name] = [
                            'name' => $name, 
                            'hours' => 0.0, 
                            'revenue' => 0.0,
                            'cost' => 0.0
                        ];
                    }
                    $jobs[$name]['hours'] += $jobHours;
                    $jobs[$name]['revenue'] += ($jobHours * $revenueRate);
                    $jobs[$name]['cost'] += ($jobHours * $hourlyCost);
                    
                    $totalRevenue += ($jobHours * $revenueRate);
                    $totalGross += ($jobHours * $hourlyCost);
                }
            }
        }

        // --- FIX: Overtime Calculation (80 Hour Threshold) ---
        // Overtime is calculated on WORKED hours (Total - PTO).
        $workedHours = $totalHours - $ptoHours;
        $regularHours = $workedHours;
        $overtimeHours = 0.0;

        if ($workedHours > 80) {
            $regularHours = 80.0;
            $overtimeHours = $workedHours - 80.0;
        }

        ksort($trend);

        return [
            'total_hours' => round($totalHours, 2),
            'stats' => [
                'regular_hours' => round($regularHours, 2),
                'overtime_hours' => round($overtimeHours, 2),
                'pto_hours' => round($ptoHours, 2),
                'gross_pay' => round($totalGross, 2),
                'revenue' => round($totalRevenue, 2),
                'profit' => round($totalRevenue - ($totalGross + $travel['total_expenses']), 2)
            ],
            'trend' => ['labels' => array_keys($trend), 'values' => array_values($trend)],
            'jobs' => array_values($jobs),
            'travel' => $travel,
            'states' => $states,
            'counties' => $counties
        ];
    }
}