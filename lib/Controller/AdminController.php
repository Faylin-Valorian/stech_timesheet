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

    /** * @AdminRequired 
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
    public function getSystemGroups(): DataResponse {
        $groups = $this->groupManager->search('');
        $list = [];
        foreach ($groups as $g) { $list[] = $g->getGID(); }
        return new DataResponse($list);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getAccessRules(): DataResponse {
        $rules = [];
        foreach($this->adminMapper->getAccessRules() as $row) { 
            $rules[$row['rule_key']] = json_decode($row['allowed_groups'] ?? '[]', true); 
        }
        return new DataResponse($rules);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function saveAccessRule(): DataResponse {
        $data = $this->request->getParams();
        if (empty($data['rule_key'])) return new DataResponse(['error' => 'Missing key'], 400);
        $jsonGroups = json_encode($data['allowed_groups'] ?? []);
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
        $this->adminMapper->saveSettingValue($data['key'] ?? '', $data['value'] ?? '');
        return new DataResponse(['status' => 'success']);
    }

    // =========================================================================
    // USER MANAGEMENT
    // =========================================================================

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getUsers(): DataResponse { 
        return new DataResponse($this->adminService->getProcessedUserList()); 
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function toggleUserStatus(): DataResponse {
        $uid = $this->request->getParam('uid');
        if (!$uid) return new DataResponse(['error' => 'No UID'], 400);
        $map = $this->adminMapper->getEmployeeStatusMap();
        $new = (($map[$uid] ?? 1) === 1) ? 0 : 1;
        $this->adminMapper->toggleUserStatus($uid, $new);
        if ($new === 0) $this->adminMapper->archiveUserHolidayEntries($uid);
        return new DataResponse(['status' => 'success', 'new_state' => $new]);
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
        if (!empty($data['holiday_id'])) {
            $qb->update('stech_holidays')
               ->set('holiday_name', $qb->createNamedParameter($data['holiday_name']))
               ->set('holiday_start_date', $qb->createNamedParameter($data['holiday_start_date']))
               ->set('holiday_end_date', $qb->createNamedParameter($data['holiday_end_date']))
               ->set('holiday_bg', $qb->createNamedParameter($data['holiday_bg'] ?? ''))
               ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($data['holiday_id'])))
               ->execute();
        } else {
            $qb->insert('stech_holidays')
               ->values([
                   'holiday_name' => $qb->createNamedParameter($data['holiday_name']),
                   'holiday_start_date' => $qb->createNamedParameter($data['holiday_start_date']),
                   'holiday_end_date' => $qb->createNamedParameter($data['holiday_end_date']),
                   'holiday_bg' => $qb->createNamedParameter($data['holiday_bg'] ?? '')
               ])->execute();
        }
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
    public function getStates(): DataResponse { 
        return new DataResponse($this->adminMapper->getStates()); 
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function getCounties(string $stateAbbr): DataResponse {
        return new DataResponse($this->adminMapper->getCountiesByState($stateAbbr));
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function toggleState(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $state = $qb->select('*')->from('stech_states')
                    ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
                    ->executeQuery()->fetch();
        if (!$state) return new DataResponse(['error' => 'Not found'], 404);
        $new = ((int)$state['is_enabled'] === 1) ? 0 : 1;
        $this->adminMapper->toggleStateAndCounties($id, $new, $state['fips_code']);
        return new DataResponse(['status' => 'success']);
    }

        /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function toggleHoliday(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $holiday = $qb->select('holiday_archive')
                    ->from('stech_holidays')
                    ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
                    ->executeQuery()
                    ->fetch();

        if (!$holiday) return new DataResponse(['error' => 'Not found'], 404);

        $newStatus = ((int)$holiday['holiday_archive'] === 1) ? 0 : 1;

        $this->db->getQueryBuilder()
                ->update('stech_holidays')
                ->set('holiday_archive', $qb->createNamedParameter($newStatus))
                ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
                ->execute();

        return new DataResponse(['status' => 'success', 'new_state' => $newStatus]);
    }

    /** * @AdminRequired
     * @NoCSRFRequired
     */
    public function toggleJob(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $job = $qb->select('job_archive')
                ->from('stech_jobs')
                ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))
                ->executeQuery()
                ->fetch();

        if (!$job) return new DataResponse(['error' => 'Not found'], 404);

        $newStatus = ((int)$job['job_archive'] === 1) ? 0 : 1;

        $this->db->getQueryBuilder()
                ->update('stech_jobs')
                ->set('job_archive', $qb->createNamedParameter($newStatus))
                ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))
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
        if (!$file) return new DataResponse(['error' => 'No file'], 400);
        try {
            $this->adminService->saveThumbnail($cardId, $file->getStream());
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) { return new DataResponse(['error' => $e->getMessage()], 500); }
    }
}