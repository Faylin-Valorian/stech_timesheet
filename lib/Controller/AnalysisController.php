<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IGroupManager;

class AnalysisController extends Controller {
    private $db;
    private $userSession;
    private $groupManager;

    public function __construct(IRequest $request, IDBConnection $db, IUserSession $userSession, IGroupManager $groupManager) {
        parent::__construct('stech_timesheet', $request);
        $this->db = $db;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
    }

    private function checkAccess($uid, $ruleKey): bool {
        if (!$uid) return false;
        if ($this->groupManager->isAdmin($uid)) return true;

        try {
            $qb = $this->db->getQueryBuilder();
            $result = $qb->select('allowed_groups')
                         ->from('stech_access_rules')
                         ->where($qb->expr()->eq('rule_key', $qb->createNamedParameter($ruleKey)))
                         ->executeQuery()
                         ->fetch();

            if (!$result) return false;
            $allowedGroups = json_decode($result['allowed_groups'], true);
            if (!is_array($allowedGroups) || empty($allowedGroups)) return false;
            
            $userGroups = $this->groupManager->getUserGroupIds($this->userSession->getUser());
            foreach ($userGroups as $gid) {
                if (in_array($gid, $allowedGroups)) return true;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getFilters(): DataResponse {
        $currentUser = $this->userSession->getUser()->getUID();
        
        $users = [];
        if ($this->groupManager->isAdmin($currentUser) || $this->checkAccess($currentUser, 'analysis_view_others')) {
            $allUsers = \OC::$server->getUserManager()->search('');
            foreach($allUsers as $u) {
                $users[] = ['uid' => $u->getUID(), 'displayname' => $u->getDisplayName()];
            }
        } else {
            $u = $this->userSession->getUser();
            $users[] = ['uid' => $u->getUID(), 'displayname' => $u->getDisplayName()];
        }

        $qb = $this->db->getQueryBuilder();
        $jobs = $qb->select('job_id', 'job_name')
                   ->from('stech_jobs')
                   ->where($qb->expr()->eq('job_archive', $qb->createNamedParameter(0)))
                   ->orderBy('job_name', 'ASC')
                   ->executeQuery()
                   ->fetchAll();

        $qb = $this->db->getQueryBuilder();
        $states = $qb->select('state_abbr', 'state_name')
                     ->from('stech_states')
                     ->where($qb->expr()->eq('is_enabled', $qb->createNamedParameter(1)))
                     ->orderBy('state_name', 'ASC')
                     ->executeQuery()
                     ->fetchAll();

        return new DataResponse([
            'users' => $users,
            'jobs' => $jobs,
            'states' => $states
        ]);
    }

    /**
     * Helper: Calculate Dates based on Payroll Settings
     */
    private function getPayrollDateRange($period) {
        $freq = 14; 
        $refDateStr = '2024-01-01'; 
        
        try {
            $qb = $this->db->getQueryBuilder();
            $settings = $qb->select('*')->from('stech_settings')->executeQuery()->fetchAll();
            foreach($settings as $s) {
                if ($s['setting_key'] === 'pay_frequency') $freq = (int)$s['setting_value'];
                if ($s['setting_key'] === 'pay_start_date') $refDateStr = $s['setting_value'];
            }
        } catch(\Exception $e) {}

        $now = new \DateTime();
        $refDate = new \DateTime($refDateStr);
        
        $diff = $now->diff($refDate)->days;
        if ($now < $refDate) $diff = -$diff;
        
        // Calculate cycles
        $cycles = floor($diff / $freq);
        
        // Start of CURRENT active cycle
        $currentStart = clone $refDate;
        $currentStart->modify('+' . ($cycles * $freq) . ' days');
        $currentEnd = clone $currentStart;
        $currentEnd->modify('+' . ($freq - 1) . ' days');

        if ($period === 'this_pay_period') {
            return [$currentStart, $currentEnd];
        } elseif ($period === 'last_pay_period') {
            $lastStart = clone $currentStart;
            $lastStart->modify('-' . $freq . ' days');
            $lastEnd = clone $currentStart;
            $lastEnd->modify('-1 day');
            return [$lastStart, $lastEnd];
        }
        
        if ($period === 'this_month') return [new \DateTime('first day of this month'), new \DateTime('last day of this month')];
        if ($period === 'last_month') return [new \DateTime('first day of last month'), new \DateTime('last day of last month')];
        if ($period === 'ytd') return [new \DateTime('first day of January this year'), new \DateTime('now')];
        
        return [$currentStart, $currentEnd];
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getStats(string $period, string $target_user = 'self'): DataResponse {
        $currentUser = $this->userSession->getUser()->getUID();
        
        if (!$this->checkAccess($currentUser, 'analysis_tab')) {
            return new DataResponse(['error' => 'Access Denied'], 403);
        }

        $canViewTravel = $this->checkAccess($currentUser, 'analysis_travel');
        $canViewFinancial = $this->checkAccess($currentUser, 'analysis_financial');
        $canViewLocation = $this->checkAccess($currentUser, 'analysis_location');
        $canViewJobBreakdown = $this->checkAccess($currentUser, 'analysis_job_breakdown');
        if ($canViewFinancial) $canViewJobBreakdown = true;

        // User Selection
        $isAdmin = $this->groupManager->isAdmin($currentUser);
        $uid = $currentUser;
        if ($target_user !== 'self') {
            if ($isAdmin || $this->checkAccess($currentUser, 'analysis_view_others')) {
                $uid = ($target_user === 'all') ? null : $target_user;
            } else {
                $uid = $currentUser;
            }
        }

        // Date Range
        if ($period === 'custom') {
            $s = $this->request->getParam('start');
            $e = $this->request->getParam('end');
            $startDate = ($s) ? new \DateTime($s) : new \DateTime();
            $endDate = ($e) ? new \DateTime($e) : new \DateTime();
        } else {
            list($startDate, $endDate) = $this->getPayrollDateRange($period);
        }

        // QUERY
        $qb = $this->db->getQueryBuilder();
        $qb->select('t.*', 
                    'a.activity_description', 'a.activity_percent', 
                    'j.job_id', 'j.job_name', 'j.job_revenue', 'j.job_expense_budget', 'j.job_hourly_cost', 'j.is_pto',
                    'st.state_name as full_state_name')
           ->from('stech_timesheets', 't')
           ->leftJoin('t', 'stech_activity', 'a', 't.timesheet_id = a.timesheet_id')
           ->leftJoin('a', 'stech_jobs', 'j', 'a.activity_description = j.job_name')
           ->leftJoin('t', 'stech_states', 'st', 't.travel_state = st.state_abbr')
           ->where($qb->expr()->gte('t.timesheet_date', $qb->createNamedParameter($startDate->format('Y-m-d'))))
           ->andWhere($qb->expr()->lte('t.timesheet_date', $qb->createNamedParameter($endDate->format('Y-m-d'))));

        if ($uid !== null) {
            $qb->andWhere($qb->expr()->eq('t.userid', $qb->createNamedParameter($uid)));
        }

        $results = $qb->executeQuery()->fetchAll();

        // AGGREGATION
        $totalHours = 0;
        $ptoHours = 0;
        $trendData = []; 
        $jobStats = []; 
        $stateStats = []; 
        $countyStats = []; 
        $processedTimesheets = []; 

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $hours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            
            if (!in_array($tid, $processedTimesheets)) {
                $totalHours += $hours;
                if (!isset($trendData[$date])) $trendData[$date] = 0;
                $trendData[$date] += $hours;

                // Location Logic
                if ($canViewLocation) {
                    $stateName = $row['full_state_name'] ?? $row['travel_state'] ?? '';
                    if ($stateName) {
                        if (!isset($stateStats[$stateName])) $stateStats[$stateName] = 0;
                        $stateStats[$stateName]++;
                        
                        $county = $row['travel_county'] ?? '';
                        if ($county) {
                            // [FIX] Strip " County" from DB string so it matches Map Key "Chambers"
                            $cleanCounty = trim(str_ireplace(' County', '', $county));
                            $key = $stateName . '|' . $cleanCounty;
                            if (!isset($countyStats[$key])) $countyStats[$key] = 0;
                            $countyStats[$key]++;
                        }
                    }
                }
                $processedTimesheets[] = $tid;
            }

            // Activity Logic
            if (!empty($row['activity_description'])) {
                $percent = (float)$row['activity_percent'];
                $jobHours = $hours * ($percent / 100);

                // Check is_pto flag
                if (isset($row['is_pto']) && $row['is_pto'] == 1) {
                    $ptoHours += $jobHours;
                }

                if ($canViewJobBreakdown) {
                    $jobName = $row['activity_description'];
                    if (!isset($jobStats[$jobName])) {
                        $jobStats[$jobName] = [
                            'name' => $jobName, 
                            'hours' => 0,
                            'revenue' => (float)($row['job_revenue'] ?? 0),
                            'budget' => (float)($row['job_expense_budget'] ?? 0),
                            'hourly_cost' => (float)($row['job_hourly_cost'] ?? 0)
                        ];
                    }
                    $jobStats[$jobName]['hours'] += $jobHours;
                }
            }
        }

        ksort($trendData);
        arsort($stateStats);
        
        $travelStats = ['total_miles'=>0, 'per_diem_days'=>0, 'overnight_stays'=>0, 'total_expenses'=>0.0];
        if ($canViewTravel) {
            $uniqueRows = [];
            foreach($results as $r) {
                if(!in_array($r['timesheet_id'], $uniqueRows)) {
                    $uniqueRows[] = $r['timesheet_id'];
                    $travelStats['total_miles'] += (int)($r['travel_miles'] ?? 0);
                    if(($r['travel_per_diem'] ?? 0) == 1) $travelStats['per_diem_days']++;
                    if(($r['travel_overnight'] ?? 0) == 1) $travelStats['overnight_stays']++;
                    $travelStats['total_expenses'] += (float)($r['travel_extra_expenses'] ?? 0);
                }
            }
            $travelStats['total_expenses'] = round($travelStats['total_expenses'], 2);
        }

        return new DataResponse([
            'total_hours' => round($totalHours, 2),
            'stats' => [
                'regular_hours' => round($totalHours - $ptoHours, 2),
                'pto_hours' => round($ptoHours, 2),
                'overtime_hours' => 0 
            ],
            'trend' => ['labels' => array_keys($trendData), 'values' => array_values($trendData)],
            'jobs' => array_values($jobStats),
            'travel' => $travelStats,
            'states' => $stateStats,
            'counties' => $countyStats
        ]);
    }
}