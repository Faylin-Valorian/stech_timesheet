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
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_timesheets')
           ->where($qb->expr()->eq('userid', $qb->createNamedParameter($this->userId)))
           ->andWhere($qb->expr()->gte('timesheet_date', $qb->createNamedParameter($start)))
           ->andWhere($qb->expr()->lte('timesheet_date', $qb->createNamedParameter($end)));
        
        $results = $qb->executeQuery()->fetchAll();
        
        $events = [];
        $today = date('Y-m-d');

        foreach ($results as $row) {
            $isClosed = !empty($row['time_out']);
            $date = $row['timesheet_date'];
            
            // --- COLOR LOGIC ---
            $color = '#ffc107'; // Active (Yellow/Orange)
            $title = 'Active';

            // Check for PTO tag in comments
            $isPto = (strpos($row['additional_comments'] ?? '', '[PTO]') !== false);

            if ($isPto) {
                $color = '#9b59b6'; // Purple for Vacation/PTO
                $title = 'PTO / Vacation';
                $isClosed = true; // Treat PTO as closed
            } elseif ($isClosed) {
                $color = '#28a745'; // Green for Closed
                $title = $row['time_total'] . ' hrs';
            } elseif ($date < $today) {
                $color = '#dc3545'; // Red for Missing Out
                $title = 'Missing Out';
            }

            $events[] = [
                'id' => $row['timesheet_id'],
                'title' => $title,
                'start' => $row['timesheet_date'],
                'color' => $color,
                'extendedProps' => [
                    'isClosed' => $isClosed
                ]
            ];
        }

        return new DataResponse($events);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getTimesheet(int $id): DataResponse {
        // 1. Fetch Main Record
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_timesheets')
           ->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($id)))
           ->andWhere($qb->expr()->eq('userid', $qb->createNamedParameter($this->userId)));
        $timesheet = $qb->executeQuery()->fetch();

        if (!$timesheet) {
            return new DataResponse([], 404);
        }

        // 2. Fetch Activities
        $qbAct = $this->db->getQueryBuilder();
        $qbAct->select('*')
              ->from('stech_activity')
              ->where($qbAct->expr()->eq('timesheet_id', $qbAct->createNamedParameter($id)));
        $activities = $qbAct->executeQuery()->fetchAll();

        // 3. Combine
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
        
        // --- 1. Validation Logic ---
        
        // If this is a NEW entry (no ID provided), check if previous is closed
        if (empty($data['timesheet_id'])) {
            $qbCheck = $this->db->getQueryBuilder();
            $qbCheck->select('*')
                    ->from('stech_timesheets')
                    ->where($qbCheck->expr()->eq('userid', $qbCheck->createNamedParameter($this->userId)))
                    ->andWhere($qbCheck->expr()->eq('timesheet_date', $qbCheck->createNamedParameter($date)))
                    ->orderBy('timesheet_id', 'DESC')
                    ->setMaxResults(1);
            
            $lastEntry = $qbCheck->executeQuery()->fetch();

            if ($lastEntry && empty($lastEntry['time_out'])) {
                // Allow multiple entries ONLY if previous is clocked out
                return new DataResponse(['error' => 'You must clock out of your previous entry for this day before adding a new one.'], 400);
            }
        }

        // Handle Time Strings (Convert empty to NULL to avoid SQL Error 1292)
        $timeIn = empty($data['time_in']) ? null : $data['time_in'];
        $timeOut = empty($data['time_out']) ? null : $data['time_out'];

        $values = [
            'userid' => $this->userId,
            'timesheet_date' => $date,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'time_break' => (int)$data['break_min'],
            'time_total' => (float)$data['total_hours'],
            'travel' => isset($data['has_travel']) ? 1 : 0,
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
            // --- UPDATE EXISTING ---
            $qb->update('stech_timesheets');
            foreach ($values as $col => $val) {
                if ($col === 'userid') continue; // Don't update userid
                $qb->set($col, $qb->createNamedParameter($val));
            }
            $qb->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($data['timesheet_id'])));
            $qb->execute();
            $timesheetId = $data['timesheet_id'];
        } else {
            // --- INSERT NEW ---
            $qb->insert('stech_timesheets');
            foreach ($values as $col => $val) {
                $qb->setValue($col, $qb->createNamedParameter($val));
            }
            $qb->execute();
            $timesheetId = $qb->getLastInsertId();
        }

        // --- Handle Activities (Delete Old, Insert New) ---
        $qbDel = $this->db->getQueryBuilder();
        $qbDel->delete('stech_activity')
              ->where($qbDel->expr()->eq('timesheet_id', $qbDel->createNamedParameter($timesheetId)));
        $qbDel->execute();

        if (isset($data['work_desc']) && is_array($data['work_desc'])) {
            foreach ($data['work_desc'] as $index => $desc) {
                if (empty($desc)) continue;
                $percent = isset($data['work_percent'][$index]) ? (int)$data['work_percent'][$index] : 0;

                $qbAct = $this->db->getQueryBuilder();
                $qbAct->insert('stech_activity')
                      ->values([
                          'timesheet_id' => $qbAct->createNamedParameter($timesheetId),
                          'activity_description' => $qbAct->createNamedParameter($desc),
                          'activity_percent' => $qbAct->createNamedParameter($percent),
                      ]);
                $qbAct->execute();
            }
        }

        return new DataResponse(['status' => 'success']);
    }
}