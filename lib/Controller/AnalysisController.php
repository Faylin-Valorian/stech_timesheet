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

    /**
     * Helper to check access rules (Centralized logic)
     */
    private function checkAccess($uid, $ruleKey): bool {
        if (!$uid) return false;

        // 1. Nextcloud Admins ALWAYS have access
        if ($this->groupManager->isAdmin($uid)) {
            return true;
        }

        // 2. Check Custom Rules from Database
        try {
            $qb = $this->db->getQueryBuilder();
            $result = $qb->select('allowed_groups')
                         ->from('stech_access_rules')
                         ->where($qb->expr()->eq('rule_key', $qb->createNamedParameter($ruleKey)))
                         ->executeQuery()
                         ->fetch();

            if (!$result) {
                return false; // Closed by default
            }

            $allowedGroups = json_decode($result['allowed_groups'], true);
            if (!is_array($allowedGroups) || empty($allowedGroups)) {
                return false;
            }

            // 3. Check if user is in any of the allowed groups
            $userGroups = $this->groupManager->getUserGroupIds($this->userSession->getUser());
            
            foreach ($userGroups as $gid) {
                if (in_array($gid, $allowedGroups)) {
                    return true;
                }
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
    public function getStats(string $period, string $target_user = 'self'): DataResponse {
        $currentUser = $this->userSession->getUser()->getUID();
        
        // [SECURITY] Check Basic Analysis Access
        if (!$this->checkAccess($currentUser, 'analysis_tab')) {
            return new DataResponse(['error' => 'Access Denied'], 403);
        }

        $isAdmin = $this->groupManager->isAdmin($currentUser);
        
        // [SECURITY] Check "View Others" Access if requesting someone else
        $uid = $currentUser;
        if ($target_user !== 'self') {
            // Check if user has permission to view others
            if ($isAdmin || $this->checkAccess($currentUser, 'analysis_view_others')) {
                if ($target_user === 'all') {
                    $uid = null; // Fetch for all users
                } else {
                    $uid = $target_user; // Fetch for specific user
                }
            } else {
                // If they tried to request someone else but don't have permission, force 'self'
                $uid = $currentUser; 
            }
        }

        // Calculate Date Range
        $endDate = new \DateTime();
        $startDate = new \DateTime();
        
        // Handle Presets vs Custom
        // Note: The JS handles 'custom' by passing dates, but if we use simple presets here:
        if ($period === 'this_pay_period') {
             // Logic for pay period is complex, for now defaulting to 14 days or handling in JS
             // Assuming JS might eventually pass exact dates for everything, but for now we handle days
        }
        
        // Simple day offsets for now, or if JS passes explicit start/end in future
        // For this specific error fix, we stick to the integer logic if passed as string number,
        // OR handle the named presets if the JS sends them.
        
        // If the JS sends "this_month", "last_month" etc, we need to calculate dates here
        // OR the JS sends the raw dates.
        // Let's look at the previous JS: it sends "period=this_pay_period".
        // We need to map these strings to dates.
        
        if ($period === 'this_month') {
            $startDate = new \DateTime('first day of this month');
            $endDate = new \DateTime('last day of this month');
        } elseif ($period === 'last_month') {
            $startDate = new \DateTime('first day of last month');
            $endDate = new \DateTime('last day of last month');
        } elseif ($period === 'ytd') {
            $startDate = new \DateTime('first day of January this year');
        } elseif ($period === 'custom') {
            // These should be passed as separate GET params
            $s = $this->request->getParam('start');
            $e = $this->request->getParam('end');
            if ($s && $e) {
                $startDate = new \DateTime($s);
                $endDate = new \DateTime($e);
            }
        } elseif (is_numeric($period)) {
             // Old fallback
             $startDate->modify('-' . (int)$period . ' days');
        } else {
            // Default: This Pay Period (Logic placeholder - usually 14 days or calculated)
            // For safety, defaulting to 14 days back
            $startDate->modify('-14 days'); 
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('t.*', 'a.activity_description', 'a.activity_percent')
           ->from('stech_timesheets', 't')
           ->leftJoin('t', 'stech_activity', 'a', 't.timesheet_id = a.timesheet_id')
           ->where($qb->expr()->gte('t.timesheet_date', $qb->createNamedParameter($startDate->format('Y-m-d'))))
           ->andWhere($qb->expr()->lte('t.timesheet_date', $qb->createNamedParameter($endDate->format('Y-m-d'))));

        if ($uid) {
            $qb->andWhere($qb->expr()->eq('t.userid', $qb->createNamedParameter($uid)));
        }

        $results = $qb->executeQuery()->fetchAll();

        // Data Aggregation
        $totalHours = 0;
        $ptoHours = 0;
        $trendData = []; 
        $jobStats = []; 
        $processedTimesheets = []; 

        // [SECURITY] Check Job Breakdown Access
        $canSeeJobs = $this->checkAccess($currentUser, 'analysis_job_breakdown');

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $hours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            
            if (!in_array($tid, $processedTimesheets)) {
                $totalHours += $hours;
                
                $isPto = (strpos($row['additional_comments'] ?? '', '[PTO]') !== false);
                if ($isPto) {
                    $ptoHours += $hours;
                }

                if (!isset($trendData[$date])) $trendData[$date] = 0;
                $trendData[$date] += $hours;
                
                $processedTimesheets[] = $tid;
            }

            if ($canSeeJobs && !empty($row['activity_description'])) {
                $jobName = $row['activity_description'];
                $percent = (float)$row['activity_percent'];
                $jobHours = $hours * ($percent / 100);

                if (!isset($jobStats[$jobName])) $jobStats[$jobName] = 0;
                $jobStats[$jobName] += $jobHours;
            }
        }

        ksort($trendData);
        arsort($jobStats);
        
        $formattedJobs = [];
        if ($canSeeJobs) {
            foreach ($jobStats as $name => $h) {
                $formattedJobs[] = ['name' => $name, 'hours' => round($h, 2)];
            }
        }
        
        // Travel Stats (Mockup/Basic Logic based on columns)
        // Check if columns exist or if we need to aggregate them.
        // Assuming fields: travel_miles, travel_per_diem, etc.
        $totalMiles = 0;
        $perDiemDays = 0;
        $overnightStays = 0;
        $totalExpenses = 0.0;
        $locationStats = [];

        // Re-loop for travel stats (efficient enough for small datasets)
        // Or integrate into above loop. Let's integrate.
        // We need to query travel columns. The SELECT t.* fetches them.
        
        // Reset and loop distinct timesheets for travel
        $processedTravel = [];
        foreach ($results as $row) {
             $tid = $row['timesheet_id'];
             if(in_array($tid, $processedTravel)) continue;
             $processedTravel[] = $tid;

             $totalMiles += (int)($row['travel_miles'] ?? 0);
             if(($row['travel_per_diem'] ?? 0) == 1) $perDiemDays++;
             if(($row['travel_overnight'] ?? 0) == 1) $overnightStays++;
             $totalExpenses += (float)($row['travel_extra_expenses'] ?? 0);

             $state = $row['travel_state'] ?? '';
             $county = $row['travel_county'] ?? '';
             if($state && $county) {
                 $key = "$state, $county";
                 if(!isset($locationStats[$key])) $locationStats[$key] = ['state'=>$state, 'county'=>$county, 'visits'=>0];
                 $locationStats[$key]['visits']++;
             }
        }
        
        $formattedLocations = array_values($locationStats);

        return new DataResponse([
            'total_hours' => round($totalHours, 2),
            'days_worked' => count($processedTimesheets),
            'overtime_hours' => ($totalHours > 40) ? round($totalHours - 40, 2) : 0, 
            'stats' => [
                'regular_hours' => round($totalHours - $ptoHours, 2),
                'pto_hours' => round($ptoHours, 2)
            ],
            'trend' => [
                'labels' => array_keys($trendData),
                'values' => array_values($trendData)
            ],
            'jobs' => $formattedJobs,
            'travel' => [
                'total_miles' => $totalMiles,
                'per_diem_days' => $perDiemDays,
                'overnight_stays' => $overnightStays,
                'total_expenses' => round($totalExpenses, 2),
                'locations' => $formattedLocations
            ]
        ]);
    }
}