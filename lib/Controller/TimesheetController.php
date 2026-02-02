<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IUserSession;

class TimesheetController extends Controller {
    private $db;
    private $userSession;
    private $userId;

    public function __construct(IRequest $request, IDBConnection $db, IUserSession $userSession) {
        parent::__construct('stech_timesheet', $request);
        $this->db = $db;
        $this->userSession = $userSession;
        $this->userId = $userSession->getUser() ? $userSession->getUser()->getUID() : null;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getAttributes(): DataResponse {
        $qbJobs = $this->db->getQueryBuilder();
        $qbJobs->select('*')
               ->from('stech_jobs')
               ->where($qbJobs->expr()->eq('job_archive', $qbJobs->createNamedParameter(0, \PDO::PARAM_INT)));
        $jobs = $qbJobs->executeQuery()->fetchAll();

        $qbStates = $this->db->getQueryBuilder();
        $qbStates->select('*')
                 ->from('stech_states')
                 ->where($qbStates->expr()->eq('is_enabled', $qbStates->createNamedParameter(1, \PDO::PARAM_INT)))
                 ->orderBy('state_name', 'ASC');
        $states = $qbStates->executeQuery()->fetchAll();

        return new DataResponse([
            'jobs' => $jobs,
            'states' => $states
        ]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getCounties(string $stateAbbr): DataResponse {
        $qbState = $this->db->getQueryBuilder();
        $qbState->select('fips_code')
                ->from('stech_states')
                ->where($qbState->expr()->eq('state_abbr', $qbState->createNamedParameter($stateAbbr)));
        $state = $qbState->executeQuery()->fetch();

        if (!$state) {
            return new DataResponse([], 404);
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_counties')
           ->where($qb->expr()->eq('state_fips', $qb->createNamedParameter($state['fips_code'])))
           ->andWhere($qb->expr()->eq('is_enabled', $qb->createNamedParameter(1, \PDO::PARAM_INT)))
           ->orderBy('county_name', 'ASC');
        $counties = $qb->executeQuery()->fetchAll();

        return new DataResponse($counties);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getTimesheets(string $start, string $end): DataResponse {
        $events = [];

        // =====================================================================
        // 1. FETCH SETTINGS & MAPS
        // =====================================================================
        $settings = [];
        try {
            $qbSettings = $this->db->getQueryBuilder();
            $rows = $qbSettings->select('*')->from('stech_admin_settings')->executeQuery()->fetchAll();
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Exception $e) { }

        // Settings for Payroll
        $payStart = $settings['pay_start_date'] ?? '2026-01-07'; 
        $freq = (int)($settings['pay_frequency'] ?? 14);
        $payBg = $settings['pay_bg_style'] ?? ''; // New Setting

        // Settings for Holidays (Map Date -> Background)
        $holidayBgMap = [];
        try {
            $qbH = $this->db->getQueryBuilder();
            $holidays = $qbH->select('holiday_start_date', 'holiday_end_date', 'holiday_bg')
                            ->from('stech_holidays')
                            ->where($qbH->expr()->isNotNull('holiday_bg'))
                            ->andWhere($qbH->expr()->neq('holiday_bg', $qbH->createNamedParameter('')))
                            ->executeQuery()
                            ->fetchAll();

            foreach ($holidays as $h) {
                $hStart = new \DateTime($h['holiday_start_date']);
                $hEnd = new \DateTime($h['holiday_end_date']);
                // Expand range to map every specific date to the background
                while ($hStart <= $hEnd) {
                    $holidayBgMap[$hStart->format('Y-m-d')] = $h['holiday_bg'];
                    $hStart->modify('+1 day');
                }
            }
        } catch (\Exception $e) { }

        // =====================================================================
        // 2. INJECT PAYROLL TABS
        // =====================================================================
        try {
            $refDate = new \DateTime($payStart);
            $viewStart = new \DateTime($start);
            $viewEnd = new \DateTime($end);

            $interval = $refDate->diff($viewStart);
            $daysDiff = (int)$interval->format('%r%a');
            
            if ($daysDiff >= 0) {
                $remainder = $daysDiff % $freq;
                $daysToAdd = ($remainder === 0) ? 0 : ($freq - $remainder);
                $nextPay = clone $viewStart;
                $nextPay->modify("+$daysToAdd days");
            } else {
                $nextPay = clone $refDate;
                while ($nextPay > $viewStart) $nextPay->modify("-$freq days");
                while ($nextPay < $viewStart) $nextPay->modify("+$freq days");
            }

            while ($nextPay <= $viewEnd) {
                $events[] = [
                    'id' => 'paid-' . $nextPay->format('Ymd'),
                    'title' => 'Payroll',
                    'start' => $nextPay->format('Y-m-d'),
                    'color' => '#34495e', // Fallback color
                    'display' => 'block',
                    'extendedProps' => [
                        'isVisual' => true, 
                        'isClosed' => true,
                        'customBg' => $payBg // Pass the custom style
                    ]
                ];
                $nextPay->modify("+$freq days");
            }
        } catch (\Exception $e) { }

        // =====================================================================
        // 3. FETCH REAL TIMESHEETS
        // =====================================================================
        $qbJobs = $this->db->getQueryBuilder();
        $qbJobs->select('job_name', 'is_pto')->from('stech_jobs');
        $allJobs = $qbJobs->executeQuery()->fetchAll();
        $ptoJobMap = [];
        foreach($allJobs as $j) $ptoJobMap[$j['job_name']] = (int)$j['is_pto'];

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_timesheets')
           ->where($qb->expr()->eq('userid', $qb->createNamedParameter($this->userId)))
           ->andWhere($qb->expr()->gte('timesheet_date', $qb->createNamedParameter($start)))
           ->andWhere($qb->expr()->lte('timesheet_date', $qb->createNamedParameter($end)))
           ->andWhere($qb->expr()->eq('archive', $qb->createNamedParameter(0)));
        
        $results = $qb->executeQuery()->fetchAll();
        $timesheetIds = array_column($results, 'timesheet_id');

        $activitiesGrouped = [];
        if (!empty($timesheetIds)) {
            $qbAct = $this->db->getQueryBuilder();
            $qbAct->select('*')
                  ->from('stech_activity')
                  ->where($qbAct->expr()->in('timesheet_id', $qbAct->createNamedParameter($timesheetIds, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT_ARRAY)));
            $acts = $qbAct->executeQuery()->fetchAll();
            foreach($acts as $a) $activitiesGrouped[$a['timesheet_id']][] = $a;
        }
        
        $today = date('Y-m-d');

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $totalHours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            $isClosed = !empty($row['time_out']);
            $comments = $row['additional_comments'] ?? '';
            
            // --- HOLIDAY LOGIC ---
            $isHoliday = (strpos($comments, 'Holiday:') === 0);

            if ($isHoliday) {
                // Check if there is a custom background for this date
                $customBg = $holidayBgMap[$date] ?? '';

                $events[] = [
                    'id' => $tid,
                    'title' => 'Holiday',
                    'start' => $date,
                    'color' => '#e67e22',
                    'extendedProps' => [
                        'isClosed' => true, 
                        'isVisual' => true,
                        'customBg' => $customBg // Pass custom style if exists
                    ]
                ];
                continue; 
            }

            $regHours = 0.0;
            $ptoHours = 0.0;
            $acts = $activitiesGrouped[$tid] ?? [];
            
            if (empty($acts)) {
                $regHours = $totalHours;
            } else {
                foreach ($acts as $act) {
                    $jobName = $act['activity_description'];
                    $percent = (float)$act['activity_percent'];
                    $hours = $totalHours * ($percent / 100);
                    if (isset($ptoJobMap[$jobName]) && $ptoJobMap[$jobName] === 1) {
                        $ptoHours += $hours;
                    } else {
                        $regHours += $hours;
                    }
                }
            }
            
            if (strpos($comments, '[PTO]') !== false) {
                 $ptoHours += $regHours;
                 $regHours = 0;
            }

            if (empty($row['time_in']) && $row['travel_per_diem'] == 1) {
                 $events[] = [
                    'id' => $tid,
                    'title' => 'Per Diem',
                    'start' => $date,
                    'color' => '#17a2b8', 
                    'extendedProps' => ['isClosed' => true]
                ];
            } else {
                if ($regHours > 0.01 || !$isClosed) {
                    $color = $isClosed ? '#28a745' : '#ffc107';
                    if ($date < $today && !$isClosed) {
                        $color = '#dc3545';
                        $title = 'Missing Out';
                    } else {
                        $title = $isClosed ? round($regHours, 2) . ' hrs' : 'Active';
                    }

                    $events[] = [
                        'id' => $tid,
                        'title' => $title,
                        'start' => $date,
                        'color' => $color,
                        'extendedProps' => ['isClosed' => $isClosed]
                    ];
                }
            }

            if ($ptoHours > 0.01) {
                $events[] = [
                    'id' => $tid,
                    'title' => 'Vacation ' . round($ptoHours, 2) . ' hrs',
                    'start' => $date,
                    'color' => '#9b59b6',
                    'extendedProps' => ['isClosed' => true]
                ];
            }
        }

        return new DataResponse($events);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getTimesheet(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $timesheet = $qb->select('*')->from('stech_timesheets')
                        ->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($id)))
                        ->andWhere($qb->expr()->eq('userid', $qb->createNamedParameter($this->userId)))
                        ->executeQuery()->fetch();

        if (!$timesheet) return new DataResponse([], 404);

        $qbAct = $this->db->getQueryBuilder();
        $activities = $qbAct->select('*')->from('stech_activity')
                            ->where($qbAct->expr()->eq('timesheet_id', $qbAct->createNamedParameter($id)))
                            ->executeQuery()->fetchAll();

        $timesheet['activities'] = $activities;
        return new DataResponse($timesheet);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveTimesheet(): DataResponse {
        $data = $this->request->getParams();
        $date = $data['date'];
        $reqPerDiem = isset($data['req_per_diem']);
        
        if (empty($data['time_in']) && !$reqPerDiem) {
            return new DataResponse(['error' => 'You must provide a Start Time, unless requesting Per Diem only.'], 400);
        }

        if (empty($data['timesheet_id'])) {
            $qbCheck = $this->db->getQueryBuilder();
            $lastEntry = $qbCheck->select('*')->from('stech_timesheets')
                                 ->where($qbCheck->expr()->eq('userid', $qbCheck->createNamedParameter($this->userId)))
                                 ->andWhere($qbCheck->expr()->eq('timesheet_date', $qbCheck->createNamedParameter($date)))
                                 ->orderBy('timesheet_id', 'DESC')->setMaxResults(1)->executeQuery()->fetch();
            
            if ($lastEntry && empty($lastEntry['time_out'])) {
                return new DataResponse(['error' => 'You already have an ACTIVE entry for this day. Please click the "Active" tab on the calendar to clock out or edit it.'], 400);
            }
        }

        $timeIn = empty($data['time_in']) ? null : $data['time_in'];
        $timeOut = empty($data['time_out']) ? null : $data['time_out'];
        $hasTravelData = (isset($data['req_per_diem']) || isset($data['road_scanning']) || isset($data['first_last_day']) || isset($data['overnight']) || !empty($data['miles']) || !empty($data['extra_expense']));

        $values = [
            'userid' => $this->userId,
            'timesheet_date' => $date,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'time_break' => (int)$data['break_min'],
            'time_total' => (float)$data['total_hours'],
            'travel' => $hasTravelData ? 1 : 0, 
            'travel_per_diem' => isset($data['req_per_diem']) ? 1 : 0,
            'travel_road_scanning' => isset($data['road_scanning']) ? 1 : 0,
            'travel_first_last_day' => isset($data['first_last_day']) ? 1 : 0,
            'travel_overnight' => isset($data['overnight']) ? 1 : 0,
            'travel_state' => $data['state'],
            'travel_county' => $data['county'],
            'travel_miles' => (int)$data['miles'],
            'travel_extra_expenses' => (float)$data['extra_expense'],
            'additional_comments' => $data['comments'],
            'archive' => 0
        ];

        $qb = $this->db->getQueryBuilder();
        if (!empty($data['timesheet_id'])) {
            $qb->update('stech_timesheets');
            foreach ($values as $col => $val) { if ($col === 'userid') continue; $qb->set($col, $qb->createNamedParameter($val)); }
            $qb->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($data['timesheet_id'])))->execute();
            $timesheetId = $data['timesheet_id'];
        } else {
            $qb->insert('stech_timesheets');
            foreach ($values as $col => $val) $qb->setValue($col, $qb->createNamedParameter($val));
            $qb->execute();
            $timesheetId = $qb->getLastInsertId();
        }

        $qbDel = $this->db->getQueryBuilder();
        $qbDel->delete('stech_activity')
              ->where($qbDel->expr()->eq('timesheet_id', $qbDel->createNamedParameter($timesheetId)))
              ->execute();

        if (isset($data['work_desc']) && is_array($data['work_desc'])) {
            // Using prepared statement for safety and speed
            $prefix = '*PREFIX*';
            $sql = "INSERT INTO `{$prefix}stech_activity` (`timesheet_id`, `activity_description`, `activity_percent`) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($sql);

            foreach ($data['work_desc'] as $index => $desc) {
                if (empty($desc)) continue;
                $percent = isset($data['work_percent'][$index]) ? (int)$data['work_percent'][$index] : 0;
                $stmt->execute([$timesheetId, $desc, $percent]);
            }
        }

        return new DataResponse(['status' => 'success']);
    }
}