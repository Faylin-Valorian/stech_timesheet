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
     * The Master Check Function
     * Returns TRUE if the user is a Super Admin OR belongs to an allowed group defined in DB.
     */
    public function checkAccess(string $ruleKey): bool {
        $user = $this->userSession->getUser();
        if (!$user) return false;
        
        // 1. Super Admin ALWAYS has access to everything
        if ($this->groupManager->isAdmin($user->getUID())) return true;

        // 2. Fetch allowed groups from Database (using the new Mapper method)
        $allowedGroups = $this->timesheetMapper->getAccessRule($ruleKey);
        
        // 3. If no rule exists or list is empty, default to FALSE
        if (empty($allowedGroups)) return false;

        // 4. Check if user is in any of the allowed groups
        $userGroups = $this->groupManager->getUserGroupIds($user);
        foreach ($userGroups as $gid) {
            if (in_array($gid, $allowedGroups)) return true;
        }

        return false;
    }

    public function getPayrollDateRange(string $period): array {
        $settings = $this->timesheetMapper->getAdminSettings();
        
        // PATCH: Handle "Custom Twice a Month" logic
        if (($settings['pay_frequency'] ?? '') === 'custom_twice') {
            return $this->calcCustomTwiceRange($period, $settings);
        }

        // Standard Frequency Logic
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
        if ($period === 'last_pay_period') return [(clone $currentStart)->modify('-' . $freq . ' days'), (clone $currentStart)->modify('-1 day')];
        if ($period === 'this_month') return [new \DateTime('first day of this month'), new \DateTime('last day of this month')];
        if ($period === 'ytd') return [new \DateTime('first day of January this year'), new \DateTime('now')];
        
        return [$currentStart, $currentEnd];
    }

    // PATCH: Helper for Custom Twice Monthly Logic
    private function calcCustomTwiceRange(string $period, array $settings): array {
        $d1 = (int)($settings['pay_date_1'] ?? 1);
        $d2 = (int)($settings['pay_date_2'] ?? 15);
        
        $now = new \DateTime();
        $y = $now->format('Y');
        $m = $now->format('m');

        // Construct the two target cutoff dates for THIS month
        $dateA = new \DateTime("$y-$m-$d1");
        $dateB = new \DateTime("$y-$m-$d2");

        // Ensure A is always before B
        if ($dateA > $dateB) { $temp = $dateA; $dateA = $dateB; $dateB = $temp; }

        // Determine CURRENT range based on "Today"
        if ($now < $dateA) {
            // Before first cutoff -> Period is [Prev Month B + 1] to [A]
            $prevB = clone $dateB; 
            $prevB->modify('-1 month');
            $start = (clone $prevB)->modify('+1 day');
            $end = $dateA;
        } elseif ($now >= $dateA && $now < $dateB) {
            // Between A and B -> Period is [A + 1] to [B]
            $start = (clone $dateA)->modify('+1 day');
            $end = $dateB;
        } else {
            // After B -> Period is [B + 1] to [Next Month A]
            $start = (clone $dateB)->modify('+1 day');
            $nextA = clone $dateA; 
            $nextA->modify('+1 month');
            $end = $nextA;
        }

        if ($period === 'this_pay_period') return [$start, $end];
        
        // Calculate "Last Period" (Flip back one cycle)
        if ($period === 'last_pay_period') {
            // Simple approximation: Go back 15 days and recalculate
            $checkDate = clone $start;
            $checkDate->modify('-5 days'); // Move safely into previous block
            
            // Re-run logic for that past date
            $pY = $checkDate->format('Y');
            $pM = $checkDate->format('m');
            $pDateA = new \DateTime("$pY-$pM-$d1");
            $pDateB = new \DateTime("$pY-$pM-$d2");
            if ($pDateA > $pDateB) { $t = $pDateA; $pDateA = $pDateB; $pDateB = $t; }

            if ($checkDate < $pDateA) {
                $prevB = clone $pDateB; $prevB->modify('-1 month');
                return [(clone $prevB)->modify('+1 day'), $pDateA];
            } elseif ($checkDate >= $pDateA && $checkDate < $pDateB) {
                return [(clone $pDateA)->modify('+1 day'), $pDateB];
            } else {
                $nextA = clone $pDateA; $nextA->modify('+1 month');
                return [(clone $pDateB)->modify('+1 day'), $nextA];
            }
        }

        if ($period === 'this_month') return [new \DateTime('first day of this month'), new \DateTime('last day of this month')];
        if ($period === 'ytd') return [new \DateTime('first day of January this year'), new \DateTime('now')];

        return [$start, $end];
    }

    public function aggregateData(array $results, array $perms): array {
        $totalHours = 0.0; 
        $ptoHours = 0.0;
        $trend = []; 
        $jobs = []; 
        $states = []; 
        $counties = []; 
        $processed = [];
        
        // Financial tracking
        $totalRevenue = 0.0;
        $totalLaborCost = 0.0;
        
        $travel = [
            'total_miles' => 0, 
            'per_diem_days' => 0, 
            'overnight_stays' => 0, 
            'total_expenses' => 0.0
        ];

        $enabledStates = array_column($this->timesheetMapper->getEnabledStates(), 'state_name');

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $hours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            // Capture User Name (or ID) for visitor tracking
            $userName = $row['userid'] ?? 'Unknown';
            
            // --- 1. Process Timesheet-Level Data ---
            if (!in_array($tid, $processed)) {
                $totalHours += $hours;
                $trend[$date] = ($trend[$date] ?? 0) + $hours;

                if ($perms['location']) {
                    $state = $row['full_state_name'] ?? $row['travel_state'] ?? 'Unknown';
                    
                    // Initialize State Entry
                    if (!isset($states[$state])) {
                        $states[$state] = [
                            'count' => 0, 
                            'is_enabled' => in_array($state, $enabledStates),
                            'visitors' => [] 
                        ];
                    }
                    $states[$state]['count']++;
                    
                    // Add/Increment User Visit
                    if (!isset($states[$state]['visitors'][$userName])) {
                        $states[$state]['visitors'][$userName] = 0;
                    }
                    $states[$state]['visitors'][$userName]++;

                    // Handle Counties
                    $county = trim(str_ireplace(' County', '', $row['travel_county'] ?? ''));
                    if ($county) {
                        $key = $state . '|' . $county;
                        
                        // Initialize County Entry
                        if (!isset($counties[$key])) {
                            $counties[$key] = [
                                'count' => 0, 
                                'is_enabled' => in_array($state, $enabledStates),
                                'visitors' => [] 
                            ];
                        }
                        $counties[$key]['count']++;
                        
                        // Add/Increment User Visit for County
                        if (!isset($counties[$key]['visitors'][$userName])) {
                            $counties[$key]['visitors'][$userName] = 0;
                        }
                        $counties[$key]['visitors'][$userName]++;
                    }
                }

                if ($perms['travel']) {
                    $travel['total_miles'] += (int)($row['travel_miles'] ?? 0);
                    if (($row['travel_per_diem'] ?? 0) == 1) $travel['per_diem_days']++;
                    if (($row['travel_overnight'] ?? 0) == 1) $travel['overnight_stays']++;
                    $travel['total_expenses'] += (float)($row['travel_extra_expenses'] ?? 0);
                }
                $processed[] = $tid;
            }

            // --- 2. Process Activity-Level Data (Job Splits) ---
            if (!empty($row['activity_description'])) {
                $percent = (float)($row['activity_percent'] ?? 0);
                $jobHours = $hours * ($percent / 100);

                if (($row['is_pto'] ?? 0) == 1) {
                    $ptoHours += $jobHours;
                }

                if ($perms['jobs']) {
                    $name = $row['job_name'] ?? $row['activity_description'];
                    
                    // Fixed Values from DB
                    $fixedRevenue = (float)($row['job_revenue'] ?? 0);
                    $fixedBudget = (float)($row['job_expense_budget'] ?? 0);
                    $hourlyRate = (float)($row['job_hourly_cost'] ?? 0);

                    // Initialize Job Entry if missing
                    if (!isset($jobs[$name])) {
                        $jobs[$name] = [
                            'name' => $name, 
                            'hours' => 0.0, 
                            'revenue' => $fixedRevenue, // Fixed Contract Amount
                            'budget' => $fixedBudget,   // Fixed Upfront Budget
                            'labor_cost' => 0.0,        // Accumulated Hourly Cost
                            'actual_expenses' => 0.0    // Accumulated Travel Expenses
                        ];
                    }
                    
                    $jobs[$name]['hours'] += $jobHours;
                    
                    // Calculated Values based on Hours
                    $laborCalc = ($jobHours * $hourlyRate);
                    
                    // Allocated Travel Expenses based on percent
                    $entryTravelExp = (float)($row['travel_extra_expenses'] ?? 0);
                    $allocatedExp = $entryTravelExp * ($percent / 100);

                    // PATCH: Fix revenue accumulation bug. We treat Revenue as fixed per Job (set in init),
                    // we do NOT add it per hour here.
                    $jobs[$name]['labor_cost'] += $laborCalc;
                    $jobs[$name]['actual_expenses'] += $allocatedExp;
                    
                    $totalLaborCost += $laborCalc;
                }
            }
        }

        // --- 3. Final Calculations ---
        
        // Sum up Total Budgets & Revenue (Fixed Cost per Job found in this period)
        $totalJobBudgets = 0.0;
        $totalJobRevenue = 0.0;
        $totalJobExpenses = 0.0;

        foreach($jobs as $job) {
            $totalJobBudgets += $job['budget'];
            $totalJobRevenue += $job['revenue'];
            $totalJobExpenses += $job['actual_expenses'];
        }

        // PROFIT FORMULA: Revenue - (Labor + Budget + Actual Expenses)
        $globalProfit = $totalJobRevenue - ($totalLaborCost + $totalJobBudgets + $totalJobExpenses);

        // Overtime Calculation
        $workedHours = $totalHours - $ptoHours;
        $regularHours = ($workedHours > 80) ? 80.0 : $workedHours;
        $overtimeHours = ($workedHours > 80) ? ($workedHours - 80.0) : 0.0;

        ksort($trend);

        return [
            'total_hours' => round($totalHours, 2),
            'stats' => [
                'regular_hours' => round($regularHours, 2),
                'overtime_hours' => round($overtimeHours, 2),
                'pto_hours' => round($ptoHours, 2),
                'gross_pay' => round($totalLaborCost, 2),
                'revenue' => round($totalJobRevenue, 2),
                'profit' => round($globalProfit, 2)
            ],
            'trend' => ['labels' => array_keys($trend), 'values' => array_values($trend)],
            'jobs' => array_values($jobs), 
            'travel' => $travel,
            'states' => $states,
            'counties' => $counties
        ];
    }
}