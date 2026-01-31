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

    private function isAdmin(): bool {
        return $this->groupManager->isAdmin($this->userSession->getUser()->getUID());
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     */
    public function index(): TemplateResponse {
        if (!$this->isAdmin()) {
            return new TemplateResponse('core', '403', [], '403');
        }
        return new TemplateResponse('stech_timesheet', 'admin');
    }

    // --- SETTINGS ---
    /** @NoCSRFRequired */
    public function getSettings(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        try {
            $schema = \OC::$server->getDatabaseConnection()->getSchemaManager();
            if (!$schema->tablesExist(['stech_admin_settings'])) return new DataResponse([]);
            $qb = $this->db->getQueryBuilder();
            $rows = $qb->select('*')->from('stech_admin_settings')->executeQuery()->fetchAll();
            $settings = []; foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
            return new DataResponse($settings);
        } catch (\Exception $e) { return new DataResponse([]); }
    }

    /** @NoCSRFRequired */
    public function saveSetting(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $data = $this->request->getParams();
        try {
            $this->saveSettingValue($data['key'] ?? null, $data['value'] ?? '');
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) { return new DataResponse(['error' => $e->getMessage()], 500); }
    }

    private function saveSettingValue($key, $value) {
        if (!$key) return;
        $qb = $this->db->getQueryBuilder();
        $exists = $qb->select('setting_key')->from('stech_admin_settings')->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($key)))->executeQuery()->fetch();
        $qb = $this->db->getQueryBuilder();
        if ($exists) {
            $qb->update('stech_admin_settings')->set('setting_value', $qb->createNamedParameter($value))->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($key)))->execute();
        } else {
            $qb->insert('stech_admin_settings')->values(['setting_key' => $qb->createNamedParameter($key), 'setting_value' => $qb->createNamedParameter($value)])->execute();
        }
    }

    /** @NoAdminRequired @NoCSRFRequired */
    public function getThumbnail(string $filename) {
        $filename = basename($filename);
        $localPath = \OC::$server->getAppManager()->getAppPath('stech_timesheet') . '/img/' . $filename;
        if (file_exists($localPath)) return new StreamResponse(fopen($localPath, 'rb'));
        try {
            $file = $this->appData->getFolder('thumbnails')->getFile($filename);
            return new FileDisplayResponse($file);
        } catch (\Exception $e) { return new DataResponse(['error' => 'Not found'], 404); }
    }

    /** @NoCSRFRequired */
    public function uploadThumbnail(string $cardId): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $uploadedFile = $this->request->getUploadedFile('image');
        $sourceStream = null;
        if ($uploadedFile) {
            if (is_array($uploadedFile)) $uploadedFile = $uploadedFile[0] ?? null;
            if ($uploadedFile) $sourceStream = $uploadedFile->getStream();
        }
        if (!$sourceStream && isset($_FILES['image']) && $_FILES['image']['error'] === 0) $sourceStream = fopen($_FILES['image']['tmp_name'], 'rb');
        if (!$sourceStream) return new DataResponse(['error' => 'Upload failed'], 400);

        $fileName = 'thumb-' . $cardId . '.png';
        $localFile = \OC::$server->getAppManager()->getAppPath('stech_timesheet') . '/img/' . $fileName;
        
        if (is_writable(dirname($localFile))) {
            file_put_contents($localFile, stream_get_contents($sourceStream));
        } else {
            try {
                try { $folder = $this->appData->getFolder('thumbnails'); } catch (NotFoundException $e) { $folder = $this->appData->newFolder('thumbnails'); }
                try { $folder->getFile($fileName)->delete(); } catch(NotFoundException $e) {}
                $folder->newFile($fileName)->putContent($sourceStream);
            } catch (\Exception $e) { return new DataResponse(['error' => $e->getMessage()], 500); }
        }
        $this->saveSettingValue('thumb_path_' . $cardId, $fileName);
        return new DataResponse(['status' => 'success']);
    }

    // --- DATA METHODS ---

    /** @NoCSRFRequired */
    public function getUsers(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $users = $this->userManager->search('');
        $result = []; foreach ($users as $u) $result[] = ['uid' => $u->getUID(), 'displayname' => $u->getDisplayName()];
        return new DataResponse($result);
    }

    /** @NoCSRFRequired */
    public function getHolidays(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        return new DataResponse($this->db->getQueryBuilder()->select('*')->from('stech_holidays')->orderBy('holiday_start_date', 'DESC')->executeQuery()->fetchAll());
    }

    /** @NoCSRFRequired */
    public function saveHoliday(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
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
            $qb->insert('stech_holidays')->values([
                'holiday_name' => $qb->createNamedParameter($data['name']),
                'holiday_start_date' => $qb->createNamedParameter($data['start']),
                'holiday_end_date' => $qb->createNamedParameter($data['end'])
            ])->execute();
        }
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function deleteHoliday(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $this->db->getQueryBuilder()->delete('stech_holidays')->where($this->db->getQueryBuilder()->expr()->eq('holiday_id', $this->db->getQueryBuilder()->createNamedParameter($id)))->execute();
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function saveJob(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $data = $this->request->getParams();
        $qb = $this->db->getQueryBuilder();
        $qb->insert('stech_jobs')->values([
            'job_name' => $qb->createNamedParameter($data['name']),
            'job_description' => $qb->createNamedParameter($data['description'] ?? ''),
            'job_archive' => $qb->createNamedParameter(0)
        ])->execute();
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function toggleJob(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        $curr = $qb->select('job_archive')->from('stech_jobs')->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))->executeQuery()->fetchOne();
        $new = ((int)$curr === 1) ? 0 : 1;
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_jobs')->set('job_archive', $qb->createNamedParameter($new))->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))->execute();
        return new DataResponse(['status' => 'success']);
    }

    // --- LOCATIONS (NEW ADMIN METHODS) ---

    /** @NoCSRFRequired */
    public function getStates(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        // Return ALL states (including disabled)
        $res = $qb->select('*')->from('stech_states')->orderBy('state_name', 'ASC')->executeQuery()->fetchAll();
        return new DataResponse($res);
    }

    /** @NoCSRFRequired */
    public function getCounties(string $stateAbbr): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        
        $qbS = $this->db->getQueryBuilder();
        $state = $qbS->select('fips_code')->from('stech_states')->where($qbS->expr()->eq('state_abbr', $qbS->createNamedParameter($stateAbbr)))->executeQuery()->fetch();
        
        if(!$state) return new DataResponse([]);

        $qb = $this->db->getQueryBuilder();
        // Return ALL counties (including disabled)
        $res = $qb->select('*')->from('stech_counties')
                  ->where($qb->expr()->eq('state_fips', $qb->createNamedParameter($state['fips_code'])))
                  ->orderBy('county_name', 'ASC')->executeQuery()->fetchAll();
        return new DataResponse($res);
    }

    /** @NoCSRFRequired */
    public function toggleState(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        $curr = $qb->select('is_enabled')->from('stech_states')->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->executeQuery()->fetchOne();
        $new = ((int)$curr === 1) ? 0 : 1;
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_states')->set('is_enabled', $qb->createNamedParameter($new))->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->execute();
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function toggleCounty(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        $curr = $qb->select('is_enabled')->from('stech_counties')->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->executeQuery()->fetchOne();
        $new = ((int)$curr === 1) ? 0 : 1;
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_counties')->set('is_enabled', $qb->createNamedParameter($new))->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->execute();
        return new DataResponse(['status' => 'success']);
    }
}