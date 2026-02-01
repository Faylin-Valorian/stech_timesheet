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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getStats(string $period, string $target_uid): DataResponse {
        $currentUser = $this->userSession->getUser()->getUID();
        $isAdmin = $this->groupManager->isAdmin($currentUser);
        $uid = $currentUser;

        // Admin override logic
        if ($isAdmin && $target_uid !== 'self') {
            if ($target_uid === 'all') {
                $uid = null; // Fetch for all users
            } else {
                $uid = $target_uid; // Fetch for specific user
            }
        }

        // Calculate Date Range
        $endDate = new \DateTime();
        $startDate = new \DateTime();
        $startDate->modify('-' . (int)$period . ' days');

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
        $processedTimesheets = []; // Track unique timesheets to avoid double counting totals on joined rows

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $hours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            
            // 1. Basic Totals (Handle duplicate rows due to Joins)
            if (!in_array($tid, $processedTimesheets)) {
                $totalHours += $hours;
                
                $isPto = (strpos($row['additional_comments'] ?? '', '[PTO]') !== false);
                if ($isPto) {
                    $ptoHours += $hours;
                }

                // Trend Data
                if (!isset($trendData[$date])) $trendData[$date] = 0;
                $trendData[$date] += $hours;
                
                $processedTimesheets[] = $tid;
            }

            // 2. Job Statistics (Weighted by percent)
            if (!empty($row['activity_description'])) {
                $jobName = $row['activity_description'];
                $percent = (float)$row['activity_percent'];
                $jobHours = $hours * ($percent / 100);

                if (!isset($jobStats[$jobName])) $jobStats[$jobName] = 0;
                $jobStats[$jobName] += $jobHours;
            }
        }

        // Sort Trend by Date
        ksort($trendData);

        // Sort Jobs by Hours (Desc)
        arsort($jobStats);
        
        // Format Jobs for Chart
        $formattedJobs = [];
        foreach ($jobStats as $name => $h) {
            $formattedJobs[] = ['name' => $name, 'hours' => round($h, 2)];
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
            'jobs' => $formattedJobs
        ]);
    }
}