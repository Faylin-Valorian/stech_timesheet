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

class TimesheetController extends Controller {
    private $userSession;
    private $service;
    private $mapper;
    private $db;

    public function __construct(IRequest $request, IUserSession $userSession, TimesheetService $service, TimesheetMapper $mapper, IDBConnection $db) {
        parent::__construct('stech_timesheet', $request);
        $this->userSession = $userSession;
        $this->service = $service;
        $this->mapper = $mapper;
        $this->db = $db;
    }

    public function getAttributes(): DataResponse {
        return new DataResponse(['jobs' => $this->mapper->getActiveJobs(), 'states' => $this->mapper->getEnabledStates()]);
    }

    public function getCounties(string $stateAbbr): DataResponse {
        return new DataResponse($this->mapper->getCountiesByState($stateAbbr));
    }

    public function getTimesheets(string $start, string $end): DataResponse {
        $uid = $this->userSession->getUser()->getUID();
        return new DataResponse($this->service->getCalendarEvents($uid, $start, $end));
    }

    public function getTimesheet(int $id): DataResponse {
        $uid = $this->userSession->getUser()->getUID();
        $qb = $this->db->getQueryBuilder();
        $ts = $qb->select('*')->from('stech_timesheets')->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($id)))->andWhere($qb->expr()->eq('userid', $qb->createNamedParameter($uid)))->executeQuery()->fetch();
        if (!$ts) return new DataResponse([], 404);
        $ts['activities'] = $this->db->getQueryBuilder()->select('*')->from('stech_activity')->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($id)))->executeQuery()->fetchAll();
        return new DataResponse($ts);
    }

    public function saveTimesheet(): DataResponse {
        $data = $this->request->getParams();
        $uid = $this->userSession->getUser()->getUID();
        
        if (empty($data['time_in']) && !isset($data['req_per_diem'])) {
            return new DataResponse(['error' => 'You must provide a Start Time, unless requesting Per Diem only.'], 400);
        }

        $values = [
            'userid' => $uid, 'timesheet_date' => $data['date'], 'time_in' => $data['time_in'] ?: null, 'time_out' => $data['time_out'] ?: null,
            'time_break' => (int)$data['break_min'], 'time_total' => (float)$data['total_hours'], 'additional_comments' => $data['comments'] ?? '',
            'travel' => (isset($data['req_per_diem']) || !empty($data['miles'])) ? 1 : 0,
            'travel_per_diem' => isset($data['req_per_diem']) ? 1 : 0, 'travel_state' => $data['state'], 'travel_county' => $data['county'],
            'travel_miles' => (int)$data['miles'], 'travel_extra_expenses' => (float)$data['extra_expense'], 'archive' => 0
        ];

        $qb = $this->db->getQueryBuilder();
        if (!empty($data['timesheet_id'])) {
            $qb->update('stech_timesheets');
            foreach ($values as $c => $v) { if ($c !== 'userid') $qb->set($c, $qb->createNamedParameter($v)); }
            $qb->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($data['timesheet_id'])))->execute();
            $tid = $data['timesheet_id'];
        } else {
            $qb->insert('stech_timesheets');
            foreach ($values as $c => $v) $qb->setValue($c, $qb->createNamedParameter($v));
            $qb->execute();
            $tid = $qb->getLastInsertId();
        }

        $this->db->getQueryBuilder()->delete('stech_activity')->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($tid)))->execute();

        if (isset($data['work_desc']) && is_array($data['work_desc'])) {
            $sql = "INSERT INTO `*PREFIX*stech_activity` (`timesheet_id`, `activity_description`, `activity_percent`) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            foreach ($data['work_desc'] as $idx => $desc) { if (!empty($desc)) $stmt->execute([$tid, $desc, (int)$data['work_percent'][$idx]]); }
        }
        return new DataResponse(['status' => 'success']);
    }
}