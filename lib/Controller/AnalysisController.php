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
        
        // Users (Only if allowed to see others)
        $users = [];
        if ($this->groupManager->isAdmin($currentUser) || $this->checkAccess($currentUser, 'analysis_view_others')) {
            $query = $this->db->getQueryBuilder();
            $query->select('uid', 'displayname')
                  ->from('users')
                  ->where($query->expr()->eq('status', $query->createNamedParameter(1))); // Assuming 'status' column exists or use OCP API
            // Note: Direct DB access to 'users' table might not exist in all NC versions. 
            // Better to use UserManager, but for speed in this context:
            $allUsers = $this->groupManager->isAdmin($currentUser) 
                ? \OC::$server->getUserManager()->search('') 
                : [\OC::$server->getUserManager()->get($currentUser)]; // Fallback
            
            // Actually, let's just use the known 'stech_timesheets' table userids or the existing user endpoint logic
            // Using a simple array for now based on what the AdminController likely provides.
            // We will fetch from the 'users' table if possible, or just return empty and let JS fetch from Admin API if preferred.
            // For now, let's fetch strictly Active Users from our managed app list if available, or just all NC users.
            $foundUsers = \OC::$server->getUserManager()->search('');
            foreach($foundUsers as $u) {
                $users[] = ['uid' => $u->getUID(), 'displayname' => $u->getDisplayName()];
            }
        } else {
            $u = $this->userSession->getUser();
            $users[] = ['uid' => $u->getUID(), 'displayname' => $u->getDisplayName()];
        }

        // Active Jobs
        $qb = $this->db->getQueryBuilder();
        $jobs = $qb->select('job_id', 'job_name')
                   ->from('stech_jobs')
                   ->where($qb->expr()->eq('job_archive', $qb->createNamedParameter(0)))
                   ->executeQuery()
                   ->fetchAll();

        // Active States (for Dropdown)
        $qb = $this->db->getQueryBuilder();
        $states = $qb->select('state_abbr', 'state_name')
                     ->from('stech_states')
                     ->where($qb->expr()->eq('is_enabled', $qb->createNamedParameter(1)))
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
    public function getStats(string $period, string $target_user = 'self', string $job_filter = 'all'): DataResponse {
        $currentUser = $this->userSession->getUser()->getUID();
        
        if (!$this->checkAccess($currentUser, 'analysis_tab')) {
            return new DataResponse(['error' => 'Access Denied'], 403);
        }

        $isAdmin = $this->groupManager->isAdmin($currentUser);
        $uid = $currentUser;

        // 1. User Scope Logic
        if ($target_user !== 'self') {
            if ($isAdmin || $this->checkAccess($currentUser, 'analysis_view_others')) {
                if ($target_user === 'all') {
                    $uid = null; // Query ALL users
                } else {
                    $uid = $target_user; // Query SPECIFIC user
                }
            } else {
                $uid = $currentUser; // Permission denied, force self
            }
        }

        // 2. Date Logic
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
            $startDate->modify('-14 days'); // Default
        }

        // 3. Build Query
        $qb = $this->db->getQueryBuilder();
        $qb->select('t.*', 'a.activity_description', 'a.activity_percent', 'j.job_id', 'j.job_name')
           ->from('stech_timesheets', 't')
           ->leftJoin('t', 'stech_activity', 'a', 't.timesheet_id = a.timesheet_id')
           ->leftJoin('a', 'stech_jobs', 'j', 'a.activity_description = j.job_name') // Join to get Job IDs if needed
           ->where($qb->expr()->gte('t.timesheet_date', $qb->createNamedParameter($startDate->format('Y-m-d'))))
           ->andWhere($qb->expr()->lte('t.timesheet_date', $qb->createNamedParameter($endDate->format('Y-m-d'))));

        // User Filter
        if ($uid !== null) {
            $qb->andWhere($qb->expr()->eq('t.userid', $qb->createNamedParameter($uid)));
        }
        
        // Job Filter (For Profitability Gauge Specifics)
        // If we select a specific job, we still might want "Overview" stats for everything, 
        // so we usually filter in PHP. However, if the user requested a specific job filter:
        if ($job_filter !== 'all') {
            // We'll handle this in the aggregation loop to allow the "Total Hours" card to remain accurate 
            // for the USER, even if the GAUGE is filtered. 
            // OR: If the entire dashboard filters by job, add:
            // $qb->andWhere($qb->expr()->eq('j.job_id', $qb->createNamedParameter($job_filter)));
            // *Decision*: The prompt implies the GAUGE has a dropdown. The dashboard has a user dropdown.
            // I will return ALL data, and let the frontend filter the Gauge, UNLESS the dataset is huge.
            // For now, return all, it's safer for "Top Stats" context.
        }

        $results = $qb->executeQuery()->fetchAll();

        // 4. Aggregation
        $totalHours = 0;
        $ptoHours = 0;
        $trendData = []; 
        $jobStats = []; // For Profitability
        $stateStats = []; // For Map
        $countyStats = []; // For Map
        $processedTimesheets = []; 

        $canSeeJobs = $this->checkAccess($currentUser, 'analysis_job_breakdown');

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $hours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            
            // Unique Timesheet Stats
            if (!in_array($tid, $processedTimesheets)) {
                $totalHours += $hours;
                
                $isPto = (strpos($row['additional_comments'] ?? '', '[PTO]') !== false);
                if ($isPto) $ptoHours += $hours;

                if (!isset($trendData[$date])) $trendData[$date] = 0;
                $trendData[$date] += $hours;

                // Map Data (States)
                $st = $row['travel_state'] ?? '';
                if ($st) {
                    if (!isset($stateStats[$st])) $stateStats[$st] = 0;
                    $stateStats[$st]++; // Count Visits
                }

                // Map Data (Counties)
                $ct = $row['travel_county'] ?? '';
                if ($ct && $st) {
                    $key = $st . '|' . $ct; // Key by State|County
                    if (!isset($countyStats[$key])) $countyStats[$key] = 0;
                    $countyStats[$key]++;
                }
                
                $processedTimesheets[] = $tid;
            }

            // Job Stats
            if ($canSeeJobs && !empty($row['activity_description'])) {
                $jobName = $row['activity_description'];
                $jobId = $row['job_id'] ?? 'unknown'; // Use ID if joined, else name
                $percent = (float)$row['activity_percent'];
                $jobHours = $hours * ($percent / 100);

                // Use ID for precise filtering if available, or Name
                $key = $jobId; 
                if (!isset($jobStats[$key])) {
                    $jobStats[$key] = ['name' => $jobName, 'hours' => 0];
                }
                $jobStats[$key]['hours'] += $jobHours;
            }
        }

        ksort($trendData);
        
        // Travel Summary
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
            'jobs' => array_values($jobStats), // Return array of objects
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