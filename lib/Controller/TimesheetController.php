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
    private $userId;

    public function __construct(IRequest $request, IUserSession $userSession, TimesheetService $service, TimesheetMapper $mapper, IDBConnection $db) {
        parent::__construct('stech_timesheet', $request);
        $this->userSession = $userSession;
        $this->service = $service;
        $this->mapper = $mapper;
        $this->db = $db;
        $this->userId = $userSession->getUser() ? $userSession->getUser()->getUID() : null;
    }

    /** @NoAdminRequired */
    public function getAttributes(): DataResponse {
        return new DataResponse(['jobs' => $this->mapper->getActiveJobs(), 'states' => $this->mapper->getEnabledStates()]);
    }

    /** @NoAdminRequired */
    public function getCounties(string $stateAbbr): DataResponse {
        return new DataResponse($this->mapper->getCountiesByState($stateAbbr));
    }

    /** @NoAdminRequired */
    public function getTimesheets(string $start, string $end): DataResponse {
        return new DataResponse($this->service->getCalendarEvents($this->userId, $start, $end));
    }

    /** @NoAdminRequired */
    public function getTimesheet(int $id): DataResponse {
        $ts = $this->mapper->getTimesheetById($id, $this->userId);
        if (!$ts) return new DataResponse([], 404);
        $ts['activities'] = $this->mapper->getActivitiesByTimesheet($id);
        return new DataResponse($ts);
    }

    /** @NoAdminRequired */
    public function saveTimesheet(): DataResponse {
        $data = $this->request->getParams();
        
        // 1. Validation
        $date = $data['date'] ?? null;
        if (!$date) {
            return new DataResponse(['error' => 'Date is required.'], 400);
        }
        
        if (empty($data['time_in']) && !isset($data['req_per_diem'])) {
            return new DataResponse(['error' => 'Start Time required unless Per Diem only.'], 400);
        }

        // 2. Map strict to database columns
        $values = [
            'userid' => $this->userId,
            'timesheet_date' => $date,
            'time_in' => !empty($data['time_in']) ? $data['time_in'] : null,
            'time_out' => !empty($data['time_out']) ? $data['time_out'] : null,
            'time_break' => (int)($data['break_min'] ?? 0),
            'time_total' => (float)($data['total_hours'] ?? 0),
            'additional_comments' => $data['comments'] ?? '',
            'travel' => (isset($data['req_per_diem']) || !empty($data['miles'])) ? 1 : 0,
            'travel_per_diem' => (isset($data['req_per_diem']) && $data['req_per_diem'] == 1) ? 1 : 0,
            'travel_state' => $data['state'] ?? null,
            'travel_county' => $data['county'] ?? null,
            'travel_miles' => (int)($data['miles'] ?? 0),
            'travel_extra_expenses' => (float)($data['extra_expense'] ?? 0),
            'archive' => 0
        ];

        try {
            $qb = $this->db->getQueryBuilder();
            
            // 3. Update or Insert
            if (!empty($data['timesheet_id'])) {
                $tid = (int)$data['timesheet_id'];
                $qb->update('stech_timesheets');
                foreach ($values as $col => $val) { 
                    if ($col !== 'userid') $qb->set($col, $qb->createNamedParameter($val)); 
                }
                $qb->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($tid)))
                   ->executeStatement();
            } else {
                $qb->insert('stech_timesheets');
                foreach ($values as $col => $val) {
                    $qb->setValue($col, $qb->createNamedParameter($val));
                }
                $qb->executeStatement();
                
                // CRITICAL FIX: Get ID from Connection, not QueryBuilder
                $tid = (int)$this->db->lastInsertId('*PREFIX*stech_timesheets');
            }

            // 4. Verify ID
            if ($tid <= 0) {
                throw new \Exception("Database failed to return a valid Timesheet ID.");
            }

            // 5. Activities Sync
            $this->db->prepare("DELETE FROM `*PREFIX*stech_activity` WHERE `timesheet_id` = ?")->execute([$tid]);
            
            if (isset($data['work_desc']) && is_array($data['work_desc'])) {
                $stmt = $this->db->prepare("INSERT INTO `*PREFIX*stech_activity` (`timesheet_id`, `activity_description`, `activity_percent`) VALUES (?, ?, ?)");
                foreach ($data['work_desc'] as $idx => $desc) { 
                    if (!empty($desc)) {
                        $percent = (int)($data['work_percent'][$idx] ?? 0);
                        $stmt->execute([$tid, $desc, $percent]);
                    }
                }
            }
            
            return new DataResponse(['status' => 'success', 'id' => $tid]);

        } catch (\Exception $e) {
            // Returns the ACTUAL error to your browser console
            return new DataResponse(['error' => $e->getMessage()], 500);
        }
    }

    /** @NoAdminRequired */
    public function deleteTimesheet(int $id): DataResponse {
        $sql = "UPDATE `*PREFIX*stech_timesheets` SET `archive` = 1 WHERE `timesheet_id` = ? AND `userid` = ?";
        $this->db->prepare($sql)->execute([$id, $this->userId]);
        return new DataResponse(['status' => 'success']);
    }
}