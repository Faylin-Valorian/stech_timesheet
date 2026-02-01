<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;

class AdminController extends Controller {
    private $db;
    private $userSession;
    private $userManager;
    private $groupManager;
    private $appData;

    public function __construct(IRequest $request, IDBConnection $db, IUserSession $userSession, IUserManager $userManager, IGroupManager $groupManager, IAppData $appData) {
        parent::__construct('stech_timesheet', $request);
        $this->db = $db;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->appData = $appData;
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function index(): TemplateResponse {
        return new TemplateResponse('stech_timesheet', 'admin');
    }

    // =========================================================================
    //  SETTINGS & THUMBNAILS
    // =========================================================================

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function getSettings(): DataResponse {
        try {
            $schema = \OC::$server->getDatabaseConnection()->getSchemaManager();
            if (!$schema->tablesExist(['stech_admin_settings'])) {
                return new DataResponse([]);
            }
            $qb = $this->db->getQueryBuilder();
            $rows = $qb->select('*')
                       ->from('stech_admin_settings')
                       ->executeQuery()
                       ->fetchAll();
            
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return new DataResponse($settings);
        } catch (\Exception $e) {
            return new DataResponse([]);
        }
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function saveSetting(): DataResponse {
        $data = $this->request->getParams();
        try {
            $this->saveSettingValue($data['key'] ?? null, $data['value'] ?? '');
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function saveSettingValue($key, $value) {
        if (!$key) return;
        
        $qb = $this->db->getQueryBuilder();
        $exists = $qb->select('setting_key')
                     ->from('stech_admin_settings')
                     ->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($key)))
                     ->executeQuery()
                     ->fetch();

        $qb = $this->db->getQueryBuilder();
        if ($exists) {
            $qb->update('stech_admin_settings')
               ->set('setting_value', $qb->createNamedParameter($value))
               ->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($key)))
               ->execute();
        } else {
            $qb->insert('stech_admin_settings')
               ->values([
                   'setting_key' => $qb->createNamedParameter($key),
                   'setting_value' => $qb->createNamedParameter($value)
               ])
               ->execute();
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getThumbnail(string $filename) {
        $filename = basename($filename);
        
        // 1. Try App img/ folder
        $appPath = \OC::$server->getAppManager()->getAppPath('stech_timesheet');
        $localPath = $appPath . '/img/' . $filename;

        if (file_exists($localPath)) {
            return new StreamResponse(fopen($localPath, 'rb'));
        }

        // 2. Try AppData folder
        try {
            $folder = $this->appData->getFolder('thumbnails');
            $file = $folder->getFile($filename);
            return new FileDisplayResponse($file);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Not found'], 404);
        }
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function uploadThumbnail(string $cardId): DataResponse {
        $uploadedFile = $this->request->getUploadedFile('image');
        $sourceStream = null;

        if ($uploadedFile) {
            if (is_array($uploadedFile)) $uploadedFile = $uploadedFile[0] ?? null;
            if ($uploadedFile) $sourceStream = $uploadedFile->getStream();
        } 
        
        // Fallback check
        if (!$sourceStream && isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $sourceStream = fopen($_FILES['image']['tmp_name'], 'rb');
        }

        if (!$sourceStream) {
            return new DataResponse(['error' => 'No valid file received'], 400);
        }

        $fileName = 'thumb-' . $cardId . '.png';
        $appPath = \OC::$server->getAppManager()->getAppPath('stech_timesheet');
        $localImgDir = $appPath . '/img/';
        $localFile = $localImgDir . $fileName;
        $savedToLocal = false;

        // Try writing to app directory
        if (is_writable($localImgDir)) {
            $content = stream_get_contents($sourceStream);
            if (file_put_contents($localFile, $content) !== false) {
                $savedToLocal = true;
            }
            rewind($sourceStream); 
        }

        // If app dir failed, write to AppData
        if (!$savedToLocal) {
            try {
                try { 
                    $folder = $this->appData->getFolder('thumbnails'); 
                } catch (NotFoundException $e) { 
                    $folder = $this->appData->newFolder('thumbnails'); 
                }
                
                try { 
                    $folder->getFile($fileName)->delete(); 
                } catch(NotFoundException $e) {}

                $file = $folder->newFile($fileName);
                if (isset($content)) $file->putContent($content);
                else $file->putContent($sourceStream);
                
            } catch (\Exception $e) { 
                return new DataResponse(['error' => 'Storage failed: ' . $e->getMessage()], 500); 
            }
        }

        $this->saveSettingValue('thumb_path_' . $cardId, $fileName);
        return new DataResponse(['status' => 'success']);
    }

    // =========================================================================
    //  USER MANAGEMENT
    // =========================================================================

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function getUsers(): DataResponse {
        // 1. Get All Nextcloud Users
        $ncUsers = $this->userManager->search('');
        
        // 2. Get Local Employee Status from stech_employees
        try {
            $qb = $this->db->getQueryBuilder();
            $employeeRows = $qb->select('*')
                               ->from('stech_employees')
                               ->executeQuery()
                               ->fetchAll();
        } catch (\Exception $e) {
            $employeeRows = [];
        }

        $statusMap = [];
        foreach($employeeRows as $row) {
            $statusMap[$row['uid']] = (int)$row['is_active'];
        }

        $result = []; 
        foreach ($ncUsers as $u) {
            $uid = $u->getUID();
            // Default to 1 (active) if not in database
            $isActive = isset($statusMap[$uid]) ? $statusMap[$uid] : 1;
            
            $result[] = [
                'uid' => $uid, 
                'displayname' => $u->getDisplayName(),
                'email' => $u->getEmailAddress(),
                'is_active' => $isActive
            ];
        }
        
        // Sort: Active first, then Alphabetical by Name
        usort($result, function($a, $b) {
            if ($a['is_active'] !== $b['is_active']) {
                return $b['is_active'] - $a['is_active']; // 1 before 0
            }
            return strcasecmp($a['displayname'], $b['displayname']);
        });

        return new DataResponse($result);
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function toggleUserStatus(): DataResponse {
        $data = $this->request->getParams();
        $uid = $data['uid'] ?? null;
        
        if (!$uid) {
            return new DataResponse(['error' => 'Missing UID'], 400);
        }

        $qb = $this->db->getQueryBuilder();
        $record = $qb->select('*')
                     ->from('stech_employees')
                     ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
                     ->executeQuery()
                     ->fetch();

        $currentStatus = $record ? (int)$record['is_active'] : 1; // Default 1 if no record
        $newStatus = ($currentStatus === 1) ? 0 : 1;
        $now = date('Y-m-d H:i:s');
        $todayDate = date('Y-m-d');

        // 1. Update/Insert Status in stech_employees
        $qb = $this->db->getQueryBuilder();
        if ($record) {
            $qb->update('stech_employees')
               ->set('is_active', $qb->createNamedParameter($newStatus))
               ->set('status_changed_at', $qb->createNamedParameter($now))
               ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
               ->execute();
        } else {
            $qb->insert('stech_employees')
               ->values([
                   'uid' => $qb->createNamedParameter($uid),
                   'is_active' => $qb->createNamedParameter($newStatus),
                   'status_changed_at' => $qb->createNamedParameter($now)
               ])
               ->execute();
        }

        // 2. Handle Holiday Archiving logic
        $archiveVal = ($newStatus === 0) ? 1 : 0; 
        
        // Custom query to handle subquery update
        $prefix = '*PREFIX*'; // Nextcloud replaces this automatically
        $sql = "UPDATE `{$prefix}stech_timesheets` AS t
                SET t.`archive` = :archiveVal 
                WHERE t.`userid` = :uid 
                AND t.`timesheet_date` >= :today
                AND EXISTS (
                    SELECT 1 FROM `{$prefix}stech_holidays` h 
                    WHERE t.`timesheet_date` BETWEEN h.`holiday_start_date` AND h.`holiday_end_date`
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('archiveVal', $archiveVal, \PDO::PARAM_INT);
        $stmt->bindValue('uid', $uid);
        $stmt->bindValue('today', $todayDate);
        $stmt->execute();

        return new DataResponse(['status' => 'success', 'new_state' => $newStatus]);
    }

    // =========================================================================
    //  HOLIDAYS
    // =========================================================================

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function getHolidays(): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('*')
                     ->from('stech_holidays')
                     ->orderBy('holiday_start_date', 'DESC')
                     ->executeQuery()
                     ->fetchAll();
        return new DataResponse($result);
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function saveHoliday(): DataResponse {
        $data = $this->request->getParams();
        $qb = $this->db->getQueryBuilder();

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
                   'holiday_end_date' => $qb->createNamedParameter($data['end']),
                   'holiday_archive' => $qb->createNamedParameter(0)
               ])
               ->execute();
        }
        return new DataResponse(['status' => 'success']);
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function toggleHoliday(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $curr = $qb->select('holiday_archive')
                   ->from('stech_holidays')
                   ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
                   ->executeQuery()
                   ->fetchOne();
        
        $new = ((int)$curr === 1) ? 0 : 1;
        
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_holidays')
           ->set('holiday_archive', $qb->createNamedParameter($new))
           ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
           ->execute();
        return new DataResponse(['status' => 'success']);
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function deleteHoliday(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('stech_holidays')
           ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
           ->execute();
        return new DataResponse(['status' => 'success']);
    }

    // =========================================================================
    //  JOBS
    // =========================================================================

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function getJobs(): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $jobs = $qb->select('*')
                   ->from('stech_jobs')
                   ->orderBy('job_name', 'ASC')
                   ->executeQuery()
                   ->fetchAll();
        return new DataResponse($jobs);
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function saveJob(): DataResponse {
        $data = $this->request->getParams();
        // Capture PTO flag
        $isPto = isset($data['is_pto']) && $data['is_pto'] == 1 ? 1 : 0;
        
        $qb = $this->db->getQueryBuilder();
        
        if (!empty($data['id'])) {
            $qb->update('stech_jobs')
               ->set('job_name', $qb->createNamedParameter($data['name']))
               ->set('job_description', $qb->createNamedParameter($data['description'] ?? ''))
               ->set('is_pto', $qb->createNamedParameter($isPto, \PDO::PARAM_INT))
               ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($data['id'])))
               ->execute();
        } else {
            $qb->insert('stech_jobs')
               ->values([
                   'job_name' => $qb->createNamedParameter($data['name']), 
                   'job_description' => $qb->createNamedParameter($data['description'] ?? ''), 
                   'job_archive' => $qb->createNamedParameter(0),
                   'is_pto' => $qb->createNamedParameter($isPto, \PDO::PARAM_INT)
               ])
               ->execute();
        }
        return new DataResponse(['status' => 'success']);
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function toggleJob(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $current = $qb->select('job_archive')
                      ->from('stech_jobs')
                      ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))
                      ->executeQuery()
                      ->fetchOne();
        
        $newState = ((int)$current === 1) ? 0 : 1;
        
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_jobs')
           ->set('job_archive', $qb->createNamedParameter($newState))
           ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))
           ->execute();
        return new DataResponse(['status' => 'success']);
    }

    // =========================================================================
    //  LOCATIONS
    // =========================================================================

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function getStates(): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $res = $qb->select('*')
                  ->from('stech_states')
                  ->orderBy('state_name', 'ASC')
                  ->executeQuery()
                  ->fetchAll();
        return new DataResponse($res);
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function getCounties(string $stateAbbr): DataResponse {
        $qbS = $this->db->getQueryBuilder();
        // Lookup FIPS code using the abbreviation
        $state = $qbS->select('fips_code')
                     ->from('stech_states')
                     ->where($qbS->expr()->eq('state_abbr', $qbS->createNamedParameter($stateAbbr)))
                     ->executeQuery()
                     ->fetch();
        
        if (!$state) return new DataResponse([]);

        $qb = $this->db->getQueryBuilder();
        $res = $qb->select('*')
                  ->from('stech_counties')
                  ->where($qb->expr()->eq('state_fips', $qb->createNamedParameter($state['fips_code'])))
                  ->orderBy('county_name', 'ASC')
                  ->executeQuery()
                  ->fetchAll();
                  
        return new DataResponse($res);
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function toggleState(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        
        // 1. Get current state status and FIPS code
        $stateRecord = $qb->select('is_enabled', 'fips_code')
                          ->from('stech_states')
                          ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
                          ->executeQuery()
                          ->fetch();
        
        if (!$stateRecord) return new DataResponse(['error' => 'State not found'], 404);

        $newState = ((int)$stateRecord['is_enabled'] === 1) ? 0 : 1;
        $fips = $stateRecord['fips_code'];

        // 2. Update the State
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_states')
           ->set('is_enabled', $qb->createNamedParameter($newState))
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
           ->execute();

        // 3. Cascade Update: Toggle all counties in this state
        $qbC = $this->db->getQueryBuilder();
        $qbC->update('stech_counties')
            ->set('is_enabled', $qbC->createNamedParameter($newState))
            ->where($qbC->expr()->eq('state_fips', $qbC->createNamedParameter($fips)))
            ->execute();

        return new DataResponse(['status' => 'success']);
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function toggleCounty(int $id): DataResponse {
        $qb = $this->db->getQueryBuilder();
        $current = $qb->select('is_enabled')
                      ->from('stech_counties')
                      ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
                      ->executeQuery()
                      ->fetchOne();
        
        $newState = ((int)$current === 1) ? 0 : 1;
        
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_counties')
           ->set('is_enabled', $qb->createNamedParameter($newState))
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
           ->execute();
        return new DataResponse(['status' => 'success']);
    }
}