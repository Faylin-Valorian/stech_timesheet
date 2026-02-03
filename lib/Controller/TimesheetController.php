<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUserSession;
use OCP\IDBConnection;
use OCA\StechTimesheet\Service\TimesheetService;
use OCA\StechTimesheet\Db\TimesheetMapper;

/**
 * TimesheetController
 * Slim modular controller focusing on request handling and record persistence.
 */
class TimesheetController extends Controller {
    private $userSession;
    private $service;
    private $mapper;
    private $db;

    public function __construct(
        IRequest $request, 
        IUserSession $userSession, 
        TimesheetService $service, 
        TimesheetMapper $mapper, 
        IDBConnection $db
    ) {
        parent::__construct('stech_timesheet', $request);
        $this->userSession = $userSession;
        $this->service = $service;
        $this->mapper = $mapper;
        $this->db = $db;
    }

    /**
     * Get Jobs and States for form initialization.
     */
    public function getAttributes(): DataResponse {
        return new DataResponse([
            'jobs' => $this->mapper->getActiveJobs(), 
            'states' => $this->mapper->getEnabledStates()
        ]);
    }

    /**
     * Get Counties for a specific State.
     */
    public function getCounties(string $stateAbbr): DataResponse {
        return new DataResponse($this->mapper->getCountiesByState($stateAbbr));
    }

    /**
     * Fetch calendar events via the Service.
     */
    public function getTimesheets(string $start, string $end): DataResponse {
        $uid = $this->userSession->getUser()->getUID();
        return new DataResponse($this->service->getCalendarEvents($uid, $start, $end));
    }

    /**
     * Retrieve details for a single record including activity rows.
     */
    public function getTimesheet(int $id): DataResponse {
        $uid = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        
        $ts = $qb->select('*')
            ->from('stech_timesheets')
            ->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($id)))
            ->andWhere($qb->expr()->eq('userid', $qb->createNamedParameter($uid)))
            ->executeQuery()
            ->fetch();
            
        if (!$ts) return new DataResponse([], 404);
        
        $ts['activities'] = $this->mapper->getActivitiesByTimesheet($id);
        return new DataResponse($ts);
    }

    /**
     * Save logic restored from original controller with MariaDB syntax fix.
     */
    public function saveTimesheet(): DataResponse {
        $data = $this->request->getParams();
        $uid = $this->userSession->getUser()->getUID();
        
        if (empty($data['time_in']) && !isset($data['req_per_diem'])) {
            return new DataResponse(['error' => 'You must provide a Start Time, unless requesting Per Diem only.'], 400);
        }

        $values = [
            'userid' => $uid,
            'timesheet_date' => $data['date'],
            'time_in' => $data['time_in'] ?: null,
            'time_out' => $data['time_out'] ?: null,
            'time_break' => (int)$data['break_min'],
            'time_total' => (float)$data['total_hours'],
            'additional_comments' => $data['comments'] ?? '',
            'travel' => (isset($data['req_per_diem']) || !empty($data['miles'])) ? 1 : 0,
            'travel_per_diem' => isset($data['req_per_diem']) ? 1 : 0,
            'travel_state' => $data['state'],
            'travel_county' => $data['county'],
            'travel_miles' => (int)$data['miles'],
            'travel_extra_expenses' => (float)$data['extra_expense'],
            'archive' => 0
        ];

        $qb = $this->db->getQueryBuilder();
        if (!empty($data['timesheet_id'])) {
            $qb->update('stech_timesheets');
            foreach ($values as $c => $v) { 
                if ($c !== 'userid') $qb->set($c, $qb->createNamedParameter($v)); 
            }
            $qb->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($data['timesheet_id'])))
               ->executeStatement();
            $tid = (int)$data['timesheet_id'];
        } else {
            $qb->insert('stech_timesheets');
            foreach ($values as $c => $v) $qb->setValue($c, $qb->createNamedParameter($v));
            $qb->executeStatement();
            
            // Standard lastInsertId for MariaDB
            $tid = (int)$this->db->lastInsertId(); 
        }

        // PATCH: Replaced QueryBuilder delete with direct SQL to resolve SQLSTATE[42000]
        $sqlDelete = "DELETE FROM `*PREFIX*stech_activity` WHERE `timesheet_id` = ?";
        $this->db->prepare($sqlDelete)->execute([$tid]);

        if (isset($data['work_desc']) && is_array($data['work_desc'])) {
            $sqlInsert = "INSERT INTO `*PREFIX*stech_activity` (`timesheet_id`, `activity_description`, `activity_percent`) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($sqlInsert);
            foreach ($data['work_desc'] as $idx => $desc) { 
                if (!empty($desc)) {
                    $stmt->execute([$tid, $desc, (int)($data['work_percent'][$idx] ?? 0)]); 
                }
            }
        }

        return new DataResponse(['status' => 'success']);
    }

    /**
     * Archive record instead of hard delete.
     */
    public function deleteTimesheet(int $id): DataResponse {
        $uid = $this->userSession->getUser()->getUID();
        $sql = "UPDATE `*PREFIX*stech_timesheets` SET `archive` = 1 WHERE `timesheet_id` = ? AND `userid` = ?";
        $this->db->prepare($sql)->execute([$id, $uid]);
           
        return new DataResponse(['status' => 'success']);
    }
}