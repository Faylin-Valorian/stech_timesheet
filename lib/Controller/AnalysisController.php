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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getStats(string $period, string $target_user = 'self'): DataResponse {
        $currentUser = $this->userSession->getUser()->getUID();
        
        if (!$this->checkAccess($currentUser, 'analysis_tab')) {
            return new DataResponse(['error' => 'Access Denied'], 403);
        }

        $isAdmin = $this->groupManager->isAdmin($currentUser);
        $uid = $currentUser;

        if ($target_user !== 'self') {
            if ($isAdmin || $this->checkAccess($currentUser, 'analysis_view_others')) {
                if ($target_user === 'all') {
                    $uid = null; 
                } else {
                    $uid = $target_user; 
                }
            } else {
                $uid = $currentUser; 
            }
        }

        // Date Logic
        $endDate = new \DateTime();
        $startDate = new \DateTime();
        
        if ($period === 'this_month') {
            $startDate = new \DateTime('first day of this month');
            $endDate = new \DateTime('last day of this month');
        } elseif ($period === 'last_month') {
            $startDate = new \DateTime('first day of last month');
            $endDate = new \DateTime('last day of last month');
        } elseif ($period === 'ytd') {
            $startDate = new \DateTime('first day of January this year');
        } elseif ($period === 'custom') {
            $s = $this->request->getParam('start');
            $e = $this->request->getParam('end');
            if ($s && $e) {
                $startDate = new \DateTime($s);
                $endDate = new \DateTime($e);
            }
        } elseif ($period === 'last_pay_period') {
             $startDate->modify('-28 days');
             $endDate->modify('-14 days');
        } else {
            $startDate->modify('-14 days'); 
        }

        // QUERY: Ensure we get FINANCIALS from stech_jobs
        $qb = $this->db->getQueryBuilder();
        $qb->select('t.*', 'a.activity_description', 'a.activity_percent', 
                    'j.job_id', 'j.job_name', 'j.job_revenue', 'j.job_expense_budget', 'j.job_hourly_cost')
           ->from('stech_timesheets', 't')
           ->leftJoin('t', 'stech_activity', 'a', 't.timesheet_id = a.timesheet_id')
           // Crucial Join for Gauge Data
           ->leftJoin('a', 'stech_jobs', 'j', 'a.activity_description = j.job_name') 
           ->where($qb->expr()->gte('t.timesheet_date', $qb->createNamedParameter($startDate->format('Y-m-d'))))
           ->andWhere($qb->expr()->lte('t.timesheet_date', $qb->createNamedParameter($endDate->format('Y-m-d'))));

        if ($uid !== null) {
            $qb->andWhere($qb->expr()->eq('t.userid', $qb->createNamedParameter($uid)));
        }

        $results = $qb->executeQuery()->fetchAll();

        // Aggregation
        $totalHours = 0;
        $ptoHours = 0;
        $trendData = []; 
        $jobStats = []; 
        $stateStats = []; 
        $countyStats = []; 
        $processedTimesheets = []; 

        $canSeeJobs = $this->checkAccess($currentUser, 'analysis_job_breakdown');

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $hours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            
            if (!in_array($tid, $processedTimesheets)) {
                $totalHours += $hours;
                
                $isPto = (strpos($row['additional_comments'] ?? '', '[PTO]') !== false);
                if ($isPto) $ptoHours += $hours;

                if (!isset($trendData[$date])) $trendData[$date] = 0;
                $trendData[$date] += $hours;

                $st = $row['travel_state'] ?? '';
                if ($st) {
                    if (!isset($stateStats[$st])) $stateStats[$st] = 0;
                    $stateStats[$st]++;
                }

                $ct = $row['travel_county'] ?? '';
                if ($ct && $st) {
                    $key = $st . '|' . $ct;
                    if (!isset($countyStats[$key])) $countyStats[$key] = 0;
                    $countyStats[$key]++;
                }
                
                $processedTimesheets[] = $tid;
            }

            // Job Stats - Accumulate Hours & Store Fixed Financial Data
            if ($canSeeJobs && !empty($row['activity_description'])) {
                $jobName = $row['activity_description'];
                $percent = (float)$row['activity_percent'];
                $jobHours = $hours * ($percent / 100);

                if (!isset($jobStats[$jobName])) {
                    $jobStats[$jobName] = [
                        'name' => $jobName, 
                        'hours' => 0,
                        // Parse Financials (Float)
                        'revenue' => (float)($row['job_revenue'] ?? 0),
                        'budget' => (float)($row['job_expense_budget'] ?? 0),
                        'hourly_cost' => (float)($row['job_hourly_cost'] ?? 0)
                    ];
                }
                $jobStats[$jobName]['hours'] += $jobHours;
            }
        }

        ksort($trendData);
        arsort($stateStats);
        
        $totalMiles = 0;
        $perDiemDays = 0;
        $overnightStays = 0;
        $totalExpenses = 0.0;
        
        $uniqueRows = [];
        foreach($results as $r) {
            if(!in_array($r['timesheet_id'], $uniqueRows)) {
                $uniqueRows[] = $r['timesheet_id'];
                $totalMiles += (int)($r['travel_miles'] ?? 0);
                if(($r['travel_per_diem'] ?? 0) == 1) $perDiemDays++;
                if(($r['travel_overnight'] ?? 0) == 1) $overnightStays++;
                $totalExpenses += (float)($r['travel_extra_expenses'] ?? 0);
            }
        }

        return new DataResponse([
            'total_hours' => round($totalHours, 2),
            'stats' => [
                'regular_hours' => round($totalHours - $ptoHours, 2),
                'pto_hours' => round($ptoHours, 2)
            ],
            'trend' => [
                'labels' => array_keys($trendData),
                'values' => array_values($trendData)
            ],
            'jobs' => array_values($jobStats),
            'travel' => [
                'total_miles' => $totalMiles,
                'per_diem_days' => $perDiemDays,
                'overnight_stays' => $overnightStays,
                'total_expenses' => round($totalExpenses, 2)
            ],
            'states' => $stateStats,
            'counties' => $countyStats
        ]);
    }
}