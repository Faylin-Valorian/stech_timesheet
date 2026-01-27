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
        // Fetch Jobs
        $qbJobs = $this->db->getQueryBuilder();
        $qbJobs->select('*')
               ->from('stech_jobs')
               ->where($qbJobs->expr()->eq('job_archive', $qbJobs->createNamedParameter(false)));
        $jobs = $qbJobs->executeQuery()->fetchAll();

        // Fetch States
        $qbStates = $this->db->getQueryBuilder();
        $qbStates->select('*')
                 ->from('stech_states') // Updated table name
                 ->where($qbStates->expr()->eq('is_enabled', $qbStates->createNamedParameter(true)))
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
        // Find State FIPS first
        $qbState = $this->db->getQueryBuilder();
        $qbState->select('fips_code')
                ->from('stech_states') // Updated table name
                ->where($qbState->expr()->eq('state_abbr', $qbState->createNamedParameter($stateAbbr)));
        $state = $qbState->executeQuery()->fetch();

        if (!$state) {
            return new DataResponse([], 404);
        }

        // Fetch Counties by FIPS
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_counties') // Updated table name
           ->where($qb->expr()->eq('state_fips', $qb->createNamedParameter($state['fips_code'])))
           ->andWhere($qb->expr()->eq('is_enabled', $qb->createNamedParameter(true)))
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
            
            $color = '';
            $title = '';

            if ($isClosed) {
                $color = '#28a745'; // Green
                $title = $row['time_total'] . ' hrs';
            } elseif ($date < $today) {
                $color = '#dc3545'; // Red
                $title = 'Missing Out';
            } else {
                $color = '#ffc107'; // Yellow/Orange
                $title = 'Active';
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
    public function saveTimesheet(): DataResponse {
        $data = $this->request->getParams();
        $date = $data['date'];
        
        $qbCheck = $this->db->getQueryBuilder();
        $qbCheck->select('*')
                ->from('stech_timesheets')
                ->where($qbCheck->expr()->eq('userid', $qbCheck->createNamedParameter($this->userId)))
                ->andWhere($qbCheck->expr()->eq('timesheet_date', $qbCheck->createNamedParameter($date)))
                ->orderBy('timesheet_id', 'DESC')
                ->setMaxResults(1);
        
        $lastEntry = $qbCheck->executeQuery()->fetch();

        if ($lastEntry && empty($lastEntry['time_out'])) {
            return new DataResponse(['error' => 'You must clock out of your previous entry for this day before adding a new one.'], 400);
        }

        $qb = $this->db->getQueryBuilder();
        $qb->insert('stech_timesheets')
           ->values([
               'userid' => $qb->createNamedParameter($this->userId),
               'timesheet_date' => $qb->createNamedParameter($date),
               'time_in' => $qb->createNamedParameter($data['time_in']),
               'time_out' => $qb->createNamedParameter($data['time_out']),
               'time_break' => $qb->createNamedParameter((int)$data['break_min']),
               'time_total' => $qb->createNamedParameter((float)$data['total_hours']),
               'travel' => $qb->createNamedParameter(isset($data['has_travel']), \PDO::PARAM_BOOL),
               'travel_per_diem' => $qb->createNamedParameter(isset($data['req_per_diem']), \PDO::PARAM_BOOL),
               'travel_road_scanning' => $qb->createNamedParameter(isset($data['road_scanning']), \PDO::PARAM_BOOL),
               'travel_first_last_day' => $qb->createNamedParameter(isset($data['first_last_day']), \PDO::PARAM_BOOL),
               'travel_overnight' => $qb->createNamedParameter(isset($data['overnight']), \PDO::PARAM_BOOL),
               'travel_state' => $qb->createNamedParameter($data['state']),
               'travel_county' => $qb->createNamedParameter($data['county']),
               'travel_miles' => $qb->createNamedParameter((int)$data['miles']),
               'travel_extra_expenses' => $qb->createNamedParameter((float)$data['extra_expense']),
               'additional_comments' => $qb->createNamedParameter($data['comments']),
               'archive' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
           ]);
        
        $qb->execute();
        $timesheetId = $qb->getLastInsertId();

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