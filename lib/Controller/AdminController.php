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
use OCA\StechTimesheet\Service\AnalysisService; // Need this for permissions
use OCA\StechTimesheet\Db\AdminMapper;

class AdminController extends Controller {
    private $db;
    private $adminService;
    private $adminMapper;
    private $groupManager;
    private $appData;
    private $analysisService; // Injected for checks

    public function __construct(IRequest $request, 
                                IDBConnection $db, 
                                AdminService $adminService, 
                                AdminMapper $adminMapper, 
                                IGroupManager $groupManager, 
                                IAppData $appData,
                                AnalysisService $analysisService) {
        parent::__construct('stech_timesheet', $request);
        $this->db = $db;
        $this->adminService = $adminService;
        $this->adminMapper = $adminMapper;
        $this->groupManager = $groupManager;
        $this->appData = $appData;
        $this->analysisService = $analysisService;
    }

    /** * Helper to enforce Admin Panel access
     */
    private function checkAdminAccess(): void {
        if (!$this->analysisService->checkAccess('admin_panel')) {
            throw new \Exception('Access Denied');
        }
    }

    /** * @NoAdminRequired 
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse { 
        // PageController handles the view logic, this is just a fallback
        return new TemplateResponse('stech_timesheet', 'admin'); 
    }

    // =========================================================================
    // ACCESS CONTROL & SETTINGS
    // =========================================================================

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getGroups(): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }
        
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

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getAccess(): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

        $rules = [];
        foreach($this->adminMapper->getAccessRules() as $row) { 
            $rules[$row['rule_key']] = json_decode($row['allowed_groups'] ?? '[]', true); 
        }
        return new DataResponse($rules);
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveAccess(): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

        $data = $this->request->getParams();
        if (empty($data['rule_key'])) return new DataResponse(['error' => 'Missing key'], 400);
        
        $groups = $data['allowed_groups'] ?? [];
        if (!is_array($groups)) $groups = [];
        
        $jsonGroups = json_encode($groups);
        $this->adminMapper->saveAccessRule($data['rule_key'], $jsonGroups);
        return new DataResponse(['status' => 'success']);
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getSettings(): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

        $settings = [];
        foreach($this->adminMapper->getSettings() as $row) { 
            $settings[$row['setting_key']] = $row['setting_value']; 
        }
        return new DataResponse($settings);
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveSetting(): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

        $params = $this->request->getParams();
        $keys = ['pay_frequency', 'pay_start_date', 'pay_date_1', 'pay_date_2', 'pay_color'];
        
        foreach ($keys as $k) {
            if (isset($params[$k])) {
                $this->adminMapper->saveSetting($k, $params[$k]);
            }
        }
        return new DataResponse(['status' => 'success']);
    }

    // =========================================================================
    // USER MANAGEMENT
    // =========================================================================

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getUsers(): DataResponse { 
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }
        return new DataResponse($this->adminService->getAllUsers()); 
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function toggleUser(): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

        $uid = $this->request->getParam('uid');
        if (!$uid) return new DataResponse(['error' => 'No UID'], 400);
        $newState = $this->adminService->toggleUserStatus($uid);
        return new DataResponse(['status' => 'success', 'new_state' => $newState]);
    }

    // =========================================================================
    // HOLIDAYS, JOBS, & LOCATIONS
    // =========================================================================

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getHolidays(): DataResponse { 
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }
        return new DataResponse($this->adminMapper->getHolidays()); 
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveHoliday(): DataResponse {
            try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

            $data = $this->request->getParams();
            $qb = $this->db->getQueryBuilder();
            
            $bg = $data['bg'] ?? '#e67e22'; 

            if (!empty($data['id'])) { 
                $qb->update('stech_holidays')
                ->set('holiday_name', $qb->createNamedParameter($data['name']))
                ->set('holiday_start_date', $qb->createNamedParameter($data['start']))
                ->set('holiday_end_date', $qb->createNamedParameter($data['end']))
                ->set('holiday_bg', $qb->createNamedParameter($bg))
                ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($data['id'])))
                ->execute();
            } else {
                $qb->insert('stech_holidays')
                ->values([
                    'holiday_name' => $qb->createNamedParameter($data['name']),
                    'holiday_start_date' => $qb->createNamedParameter($data['start']),
                    'holiday_end_date' => $qb->createNamedParameter($data['end']),
                    'holiday_bg' => $qb->createNamedParameter($bg),
                    'holiday_archive' => $qb->createNamedParameter(0)
                ])->execute();
            }
            return new DataResponse(['status' => 'success']);
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function toggleHoliday(int $id): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }
        $this->adminMapper->toggleHoliday($id);
        return new DataResponse(['status' => 'success']);
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function deleteHoliday(int $id): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }
        $qb = $this->db->getQueryBuilder();
        $qb->delete('stech_holidays')
            ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
            ->execute();
        return new DataResponse(['status' => 'success']);
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getJobs(): DataResponse { 
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }
        return new DataResponse($this->adminMapper->getJobs()); 
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveJob(): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

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

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function toggleJob(int $id): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }
        $this->adminMapper->toggleJob($id);
        return new DataResponse(['status' => 'success']);
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getStates(): DataResponse { 
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }
        return new DataResponse($this->adminMapper->getStatesAdmin()); 
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getCounties(string $abbr): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }
        return new DataResponse($this->adminMapper->getCountiesByStateAdmin($abbr));
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function toggleState(int $id): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

        $qb = $this->db->getQueryBuilder();
        $state = $qb->select('*')->from('stech_states')
                    ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
                    ->executeQuery()->fetch();
        if (!$state) return new DataResponse(['error' => 'Not found'], 404);
        
        $new = ((int)$state['is_enabled'] === 1) ? 0 : 1;
        $qb->update('stech_states')
           ->set('is_enabled', $qb->createNamedParameter($new))
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
           ->execute();

        return new DataResponse(['status' => 'success']);
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function toggleCounty(int $id): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

        $qb = $this->db->getQueryBuilder();
        $county = $qb->select('is_enabled')
                ->from('stech_counties')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
                ->executeQuery()
                ->fetch();

        if (!$county) return new DataResponse(['error' => 'Not found'], 404);

        $newStatus = ((int)$county['is_enabled'] === 1) ? 0 : 1;
        $qb->update('stech_counties')
           ->set('is_enabled', $qb->createNamedParameter($newStatus))
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
           ->execute();

        return new DataResponse(['status' => 'success', 'new_state' => $newStatus]);
    }

    // =========================================================================
    // THUMBNAILS & ASSETS
    // =========================================================================

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getThumbnail(string $filename) {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

        $local = $this->adminService->getLocalImagePath($filename);
        if ($local) return new StreamResponse(fopen($local, 'rb'));
        try {
            $file = $this->appData->getFolder('thumbnails')->getFile(basename($filename));
            return new FileDisplayResponse($file);
        } catch (\Exception $e) { return new DataResponse(['error' => 'Not found'], 404); }
    }

    /** * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function uploadThumbnail(string $cardId): DataResponse {
        try { $this->checkAdminAccess(); } catch(\Exception $e) { return new DataResponse([], 403); }

        $file = $this->request->getUploadedFile('image');
        if (is_array($file)) $file = reset($file);

        if (!$file) return new DataResponse(['error' => 'No file'], 400);
        try {
            $this->adminService->saveThumbnail($cardId, $file->getStream());
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) { return new DataResponse(['error' => $e->getMessage()], 500); }
    }
}