<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Files\IAppData;
use OCA\StechTimesheet\Service\AdminService;
use OCA\StechTimesheet\Db\AdminMapper;

class AdminController extends Controller {
    private $db;
    private $adminService;
    private $adminMapper;
    private $groupManager;
    private $appData;

    public function __construct(IRequest $request, 
                                IDBConnection $db, 
                                AdminService $adminService, 
                                AdminMapper $adminMapper, 
                                IGroupManager $groupManager, 
                                IAppData $appData) {
        parent::__construct('stech_timesheet', $request);
        $this->db = $db;
        $this->adminService = $adminService;
        $this->adminMapper = $adminMapper;
        $this->groupManager = $groupManager;
        $this->appData = $appData;
    }

    /** * @NoAdminRequired 
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse { 
        return new TemplateResponse('stech_timesheet', 'admin'); 
    }

    // =========================================================================
    // ACCESS CONTROL & SETTINGS
    // =========================================================================

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getGroups(): DataResponse {
        $groups = $this->groupManager->search('');
        $list = [];
        foreach ($groups as $g) { 
            $list[] = [
                'gid' => $g->getGID(),
                'displayName' => $g->getDisplayName()
            ]; 
        }
        return new DataResponse($list);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getAccess(): DataResponse {
        $rules = [];
        foreach($this->adminMapper->getAccessRules() as $row) { 
            $rules[$row['rule_key']] = json_decode($row['allowed_groups'] ?? '[]', true); 
        }
        return new DataResponse($rules);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function saveAccess(): DataResponse {
        $data = $this->request->getParams();
        if (empty($data['rule_key'])) return new DataResponse(['error' => 'Missing key'], 400);
        
        $groups = $data['allowed_groups'] ?? [];
        if (!is_array($groups)) $groups = [];
        
        $jsonGroups = json_encode($groups);
        $this->adminMapper->saveAccessRule($data['rule_key'], $jsonGroups);
        return new DataResponse(['status' => 'success']);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getSettings(): DataResponse {
        $settings = [];
        foreach($this->adminMapper->getSettings() as $row) { 
            $settings[$row['setting_key']] = $row['setting_value']; 
        }
        return new DataResponse($settings);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function saveSetting(): DataResponse {
        $data = $this->request->getParams();
        $this->adminMapper->saveSetting($data['key'] ?? '', $data['value'] ?? '');
        return new DataResponse(['status' => 'success']);
    }

    // REMOVED: uploadPayrollBg() as requested.

    // =========================================================================
    // USER MANAGEMENT
    // =========================================================================

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getUsers(): DataResponse { 
        return new DataResponse($this->adminService->getAllUsers()); 
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function toggleUser(): DataResponse {
        $uid = $this->request->getParam('uid');
        if (!$uid) return new DataResponse(['error' => 'No UID'], 400);
        $newState = $this->adminService->toggleUserStatus($uid);
        return new DataResponse(['status' => 'success', 'new_state' => $newState]);
    }

    // =========================================================================
    // HOLIDAYS, JOBS, & LOCATIONS
    // =========================================================================

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getHolidays(): DataResponse { 
        return new DataResponse($this->adminMapper->getHolidays()); 
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function saveHoliday(): DataResponse {
            $data = $this->request->getParams();
            $qb = $this->db->getQueryBuilder();
            
            // Removed holiday_bg from both UPDATE and INSERT
            if (!empty($data['id'])) { 
                $qb->update('stech_holidays')
                ->set('holiday_name', $qb->createNamedParameter($data['name']))
                ->set('holiday_start_date', $qb->createNamedParameter($data['start']))
                ->set('holiday_end_date', $qb->createNamedParameter($data['end']))
                ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($data['id'])))
                ->execute();
            } else {
                $qb->insert('stech_holidays')
                ->values([
                    'holiday_name' => $qb->createNamedParameter($data['name']),
                    'holiday_start_date' => $qb->createNamedParameter($data['start']),
                    'holiday_end_date' => $qb->createNamedParameter($data['end'])
                ])->execute();
            }
            return new DataResponse(['status' => 'success']);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function toggleHoliday(int $id): DataResponse {
        $this->adminMapper->toggleHoliday($id);
        return new DataResponse(['status' => 'success']);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function deleteHoliday(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('stech_holidays')
            ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
            ->execute();
        return new DataResponse(['status' => 'success']);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getJobs(): DataResponse { 
        return new DataResponse($this->adminMapper->getJobs()); 
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function saveJob(): DataResponse {
        $data = $this->request->getParams();
        $qb = $this->db->getQueryBuilder();
        if (!empty($data['job_id'])) {
            $qb->update('stech_jobs')
                ->set('job_name', $qb->createNamedParameter($data['job_name']))
                ->set('is_pto', $qb->createNamedParameter($data['is_pto'] ?? 0))
                ->set('job_revenue', $qb->createNamedParameter($data['job_revenue'] ?? 0))
                ->set('job_expense_budget', $qb->createNamedParameter($data['job_expense_budget'] ?? 0))
                ->set('job_hourly_cost', $qb->createNamedParameter($data['job_hourly_cost'] ?? 0))
                ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($data['job_id'])))
                ->execute();
        } else {
            $qb->insert('stech_jobs')
                ->values([
                    'job_name' => $qb->createNamedParameter($data['job_name']),
                    'is_pto' => $qb->createNamedParameter($data['is_pto'] ?? 0),
                    'job_revenue' => $qb->createNamedParameter($data['job_revenue'] ?? 0),
                    'job_expense_budget' => $qb->createNamedParameter($data['job_expense_budget'] ?? 0),
                    'job_hourly_cost' => $qb->createNamedParameter($data['job_hourly_cost'] ?? 0)
                ])->execute();
        }
        return new DataResponse(['status' => 'success']);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function toggleJob(int $id): DataResponse {
        $this->adminMapper->toggleJob($id);
        return new DataResponse(['status' => 'success']);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getStates(): DataResponse { 
        return new DataResponse($this->adminMapper->getStatesAdmin()); 
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getCounties(string $abbr): DataResponse {
        return new DataResponse($this->adminMapper->getCountiesByStateAdmin($abbr));
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function toggleState(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $state = $qb->select('*')->from('stech_states')
                    ->where($qb->expr()->eq('state_id', $qb->createNamedParameter($id)))
                    ->executeQuery()->fetch();
        if (!$state) return new DataResponse(['error' => 'Not found'], 404);
        
        $new = ((int)$state['is_enabled'] === 1) ? 0 : 1;
        $qb->update('stech_states')
           ->set('is_enabled', $qb->createNamedParameter($new))
           ->where($qb->expr()->eq('state_id', $qb->createNamedParameter($id)))
           ->execute();

        return new DataResponse(['status' => 'success']);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function toggleCounty(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $county = $qb->select('is_enabled')
                ->from('stech_counties')
                ->where($qb->expr()->eq('county_id', $qb->createNamedParameter($id)))
                ->executeQuery()
                ->fetch();

        if (!$county) return new DataResponse(['error' => 'Not found'], 404);

        $newStatus = ((int)$county['is_enabled'] === 1) ? 0 : 1;
        $qb->update('stech_counties')
           ->set('is_enabled', $qb->createNamedParameter($newStatus))
           ->where($qb->expr()->eq('county_id', $qb->createNamedParameter($id)))
           ->execute();

        return new DataResponse(['status' => 'success', 'new_state' => $newStatus]);
    }

    // =========================================================================
    // THUMBNAILS & ASSETS
    // =========================================================================

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getThumbnail(string $filename) {
        $local = $this->adminService->getLocalImagePath($filename);
        if ($local) return new StreamResponse(fopen($local, 'rb'));
        try {
            $file = $this->appData->getFolder('thumbnails')->getFile(basename($filename));
            return new FileDisplayResponse($file);
        } catch (\Exception $e) { return new DataResponse(['error' => 'Not found'], 404); }
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function uploadThumbnail(string $cardId): DataResponse {
        $file = $this->request->getUploadedFile('image');
        if (is_array($file)) $file = reset($file);

        if (!$file) return new DataResponse(['error' => 'No file'], 400);
        try {
            $this->adminService->saveThumbnail($cardId, $file->getStream());
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) { return new DataResponse(['error' => $e->getMessage()], 500); }
    }
}