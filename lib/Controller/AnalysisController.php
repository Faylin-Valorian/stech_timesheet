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
     * Helper to check access rules
     */
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
    public function getStats(string $period, string $target_user = 'self'): DataResponse {
        $currentUser = $this->userSession->getUser()->getUID();
        
        // [SECURITY] Basic Access Check
        if (!$this->checkAccess($currentUser, 'analysis_tab')) {
            return new DataResponse(['error' => 'Access Denied'], 403);
        }

        $isAdmin = $this->groupManager->isAdmin($currentUser);
        $uid = $currentUser;

        // [SECURITY] "View Others" Logic
        if ($target_user !== 'self') {
            if ($isAdmin || $this->checkAccess($currentUser, 'analysis_view_others')) {
                if ($target_user === 'all') {
                    $uid = null; // Fetch for all users
                } else {
                    $uid = $target_user; // Fetch for specific user
                }
            } else {
                $uid = $currentUser; // Force self if unauthorized
            }
        }

        // --- Date Logic ---
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
             // Logic for last pay period (approx 2 weeks back from last cycle)
             $startDate->modify('-28 days');
             $endDate->modify('-14 days');
        } else {
            // Default: "this_pay_period" (Last 14 days)
            $startDate->modify('-14 days'); 
        }

        // --- Main Query ---
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

        // --- Data Aggregation ---
        $totalHours = 0;
        $ptoHours = 0;
        $trendData = []; 
        $jobStats = []; 
        $stateStats = [];
        $countyStats = [];
        $processedTimesheets = []; // Track unique IDs

        $canSeeJobs = $this->checkAccess($currentUser, 'analysis_job_breakdown');

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $hours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            
            // Process Unique Timesheets (Trends, Totals, Location)
            if (!in_array($tid, $processedTimesheets)) {
                $totalHours += $hours;
                
                $isPto = (strpos($row['additional_comments'] ?? '', '[PTO]') !== false);
                if ($isPto) {
                    $ptoHours += $hours;
                }

                // Trend Data (Date -> Hours)
                if (!isset($trendData[$date])) $trendData[$date] = 0;
                $trendData[$date] += $hours;

                // State Activity (Count of entries)
                $st = $row['travel_state'] ?? 'Unknown';
                if ($st && $st !== 'Unknown') {
                    if (!isset($stateStats[$st])) $stateStats[$st] = 0;
                    $stateStats[$st]++;
                }

                // County Activity (Count of entries)
                $ct = $row['travel_county'] ?? 'Unknown';
                if ($ct && $ct !== 'Unknown') {
                    // Combine with state to ensure uniqueness (e.g., "Orange (CA)" vs "Orange (FL)")
                    $label = $ct . ($st ? " ($st)" : "");
                    if (!isset($countyStats[$label])) $countyStats[$label] = 0;
                    $countyStats[$label]++;
                }
                
                $processedTimesheets[] = $tid;
            }

            // Job Stats (Weighted by percent) - Used for Breakdown AND Profitability
            if ($canSeeJobs && !empty($row['activity_description'])) {
                $jobName = $row['activity_description'];
                $percent = (float)$row['activity_percent'];
                $jobHours = $hours * ($percent / 100);

                if (!isset($jobStats[$jobName])) $jobStats[$jobName] = 0;
                $jobStats[$jobName] += $jobHours;
            }
        }

        // Sorting
        ksort($trendData);
        arsort($jobStats);
        arsort($stateStats);
        arsort($countyStats);
        
        // Format Jobs
        $formattedJobs = [];
        if ($canSeeJobs) {
            foreach ($jobStats as $name => $h) {
                $formattedJobs[] = ['name' => $name, 'hours' => round($h, 2)];
            }
        }
        
        // Travel Summary Calculation (Re-loop unique rows to avoid JOIN duplication)
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
                'total_expenses' => round($totalExpenses, 2)
            ],
            'states' => $stateStats,
            'counties' => $countyStats
        ]);
    }
}