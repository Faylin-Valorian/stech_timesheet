<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUserSession;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCA\StechTimesheet\Service\TimesheetService;
use OCA\StechTimesheet\Db\TimesheetMapper;

class TimesheetController extends Controller {
    private $userSession;
    private $service;
    private $mapper;
    private $db;
    private $groupManager;

    public function __construct(IRequest $request, 
                                IUserSession $userSession, 
                                TimesheetService $service, 
                                TimesheetMapper $mapper, 
                                IDBConnection $db,
                                IGroupManager $groupManager) {
        parent::__construct('stech_timesheet', $request);
        $this->userSession = $userSession;
        $this->service = $service;
        $this->mapper = $mapper;
        $this->db = $db;
        $this->groupManager = $groupManager;
    }

    /**
     * Helper: Determines if we should load the logged-in user OR the target user.
     */
    private function getEffectiveUserId(): string {
        $currentUser = $this->userSession->getUser();
        if (!$currentUser) {
            return ''; 
        }
        
        $currentUid = $currentUser->getUID();
        $targetUid = $this->request->getParam('target_user');

        // If target is requested and is different from current user
        if ($targetUid && $targetUid !== $currentUid) {
            // Security Check: Only Admins can swap view
            if ($this->groupManager->isAdmin($currentUid)) {
                return $targetUid;
            }
        }
        
        return $currentUid;
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
        $uid = $this->getEffectiveUserId(); // FIX: Use effective ID
        $archive = (int)$this->request->getParam('archive', 0);
        return new DataResponse($this->service->getCalendarEvents($uid, $start, $end, $archive));
    }

    /** @NoAdminRequired */
    public function getTimesheet(int $id): DataResponse {
        $uid = $this->getEffectiveUserId(); // FIX: Use effective ID
        $ts = $this->mapper->getTimesheetById($id, $uid);
        
        if (!$ts) return new DataResponse([], 404);
        
        $ts['activities'] = $this->mapper->getActivitiesByTimesheet($id);
        
        // Pass admin flag to frontend
        $currentUser = $this->userSession->getUser();
        $isAdmin = $currentUser && $this->groupManager->isAdmin($currentUser->getUID());
        $ts['is_admin'] = $isAdmin;

        return new DataResponse($ts);
    }

    /** @NoAdminRequired */
    public function saveTimesheet(): DataResponse {
        $uid = $this->getEffectiveUserId(); // FIX: Use effective ID (saves to target user's calendar)
        $data = $this->request->getParams();
        $date = $data['date'] ?? null;
        
        if (!$date) {
            return new DataResponse(['error' => 'Date is required.'], 400);
        }
        
        if (empty($data['time_in']) && !isset($data['req_per_diem'])) {
            return new DataResponse(['error' => 'Start Time required unless Per Diem only.'], 400);
        }

        $values = [
            'userid' => $uid, 
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
                
                $tid = (int)$this->db->lastInsertId('*PREFIX*stech_timesheets');
            }

            if ($tid > 0) {
                $this->db->prepare("DELETE FROM `*PREFIX*stech_activity` WHERE `timesheet_id` = ?")->execute([$tid]);
                if (isset($data['work_desc']) && is_array($data['work_desc'])) {
                    $stmt = $this->db->prepare("INSERT INTO `*PREFIX*stech_activity` (`timesheet_id`, `activity_description`, `activity_percent`) VALUES (?, ?, ?)");
                    foreach ($data['work_desc'] as $idx => $desc) { 
                        if (!empty($desc)) {
                            $stmt->execute([$tid, $desc, (int)($data['work_percent'][$idx] ?? 0)]);
                        }
                    }
                }
            } else {
                throw new \Exception("Database failed to return a valid Timesheet ID.");
            }

            return new DataResponse(['status' => 'success', 'id' => $tid]);

        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 500);
        }
    }

    /** @NoAdminRequired */
    public function deleteTimesheet(int $id): DataResponse {
        $uid = $this->getEffectiveUserId(); // FIX: Use effective ID
        $sql = "UPDATE `*PREFIX*stech_timesheets` SET `archive` = 1 WHERE `timesheet_id` = ? AND `userid` = ?";
        $this->db->prepare($sql)->execute([$id, $uid]);
        return new DataResponse(['status' => 'success']);
    }

    /** * @NoAdminRequired */
    public function restoreTimesheet(int $id): DataResponse {
        $uid = $this->getEffectiveUserId(); // FIX: Use effective ID
        $sql = "UPDATE `*PREFIX*stech_timesheets` SET `archive` = 0 WHERE `timesheet_id` = ? AND `userid` = ?";
        $this->db->prepare($sql)->execute([$id, $uid]);
        return new DataResponse(['status' => 'success']);
    }
}