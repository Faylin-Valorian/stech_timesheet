<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Response;
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

    /** @NoCSRFRequired */
    public function getSettings(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        try {
            $schema = \OC::$server->getDatabaseConnection()->getSchemaManager();
            if (!$schema->tablesExist(['stech_admin_settings'])) {
                return new DataResponse([]);
            }
            $qb = $this->db->getQueryBuilder();
            $rows = $qb->select('*')->from('stech_admin_settings')->executeQuery()->fetchAll();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return new DataResponse($settings);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 500);
        }
    }

    /** @NoCSRFRequired */
    public function saveSetting(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $data = $this->request->getParams();
        $key = $data['key'] ?? null;
        $value = $data['value'] ?? '';
        if (!$key) return new DataResponse(['error' => 'Missing key'], 400);

        try {
            $qb = $this->db->getQueryBuilder();
            $exists = $qb->select('setting_key')->from('stech_admin_settings')
                         ->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($key)))
                         ->executeQuery()->fetch();

            $qb = $this->db->getQueryBuilder();
            if ($exists) {
                $qb->update('stech_admin_settings')->set('setting_value', $qb->createNamedParameter($value))
                   ->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($key)))->execute();
            } else {
                $qb->insert('stech_admin_settings')->values([
                    'setting_key' => $qb->createNamedParameter($key),
                    'setting_value' => $qb->createNamedParameter($value)
                ])->execute();
            }
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getThumbnail(string $filename): Response {
        try {
            $folder = $this->appData->getFolder('thumbnails');
            $file = $folder->getFile($filename);
            return new FileDisplayResponse($file);
        } catch (NotFoundException $e) {
            return new DataResponse(['error' => 'File not found'], 404);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 500);
        }
    }

    /** @NoCSRFRequired */
    public function uploadThumbnail(string $cardId): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        
        $uploadedFile = $this->request->getUploadedFile('image');
        if (is_array($uploadedFile)) {
            $uploadedFile = $uploadedFile[0] ?? null;
        }

        if (!$uploadedFile) return new DataResponse(['error' => 'No file received'], 400);
        
        $fileName = 'thumb-' . $cardId . '.png';
        try {
            try { $folder = $this->appData->getFolder('thumbnails'); } catch (NotFoundException $e) { $folder = $this->appData->newFolder('thumbnails'); }
            
            try { $folder->getFile($fileName)->delete(); } catch(NotFoundException $e) {}

            $file = $folder->newFile($fileName);
            $file->putContent($uploadedFile->getStream());
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) { return new DataResponse(['error' => $e->getMessage()], 500); }
    }

    /** @NoCSRFRequired */
    public function getUsers(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $users = $this->userManager->search('');
        $result = []; 
        foreach ($users as $u) {
            $result[] = ['uid' => $u->getUID(), 'displayname' => $u->getDisplayName()];
        }
        return new DataResponse($result);
    }

    /** @NoCSRFRequired */
    public function getHolidays(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        return new DataResponse($qb->select('*')->from('stech_holidays')->orderBy('holiday_start_date', 'DESC')->executeQuery()->fetchAll());
    }

    /** @NoCSRFRequired */
    public function saveHoliday(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $data = $this->request->getParams();
        $this->db->getQueryBuilder()->insert('stech_holidays')->values([
            'holiday_name' => $this->db->getQueryBuilder()->createNamedParameter($data['name']), 
            'holiday_start_date' => $this->db->getQueryBuilder()->createNamedParameter($data['start']), 
            'holiday_end_date' => $this->db->getQueryBuilder()->createNamedParameter($data['end'])
        ])->execute();
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
        $this->db->getQueryBuilder()->insert('stech_jobs')->values([
            'job_name' => $this->db->getQueryBuilder()->createNamedParameter($data['name']), 
            'job_description' => $this->db->getQueryBuilder()->createNamedParameter($data['description'] ?? ''), 
            'job_archive' => 0
        ])->execute();
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function toggleJob(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $this->db->getQueryBuilder()->update('stech_jobs')->set('job_archive', '1 - job_archive')->where($this->db->getQueryBuilder()->expr()->eq('job_id', $this->db->getQueryBuilder()->createNamedParameter($id)))->execute();
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function toggleState(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $this->db->getQueryBuilder()->update('stech_states')->set('is_enabled', '1 - is_enabled')->where($this->db->getQueryBuilder()->expr()->eq('id', $this->db->getQueryBuilder()->createNamedParameter($id)))->execute();
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function toggleCounty(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $this->db->getQueryBuilder()->update('stech_counties')->set('is_enabled', '1 - is_enabled')->where($this->db->getQueryBuilder()->expr()->eq('id', $this->db->getQueryBuilder()->createNamedParameter($id)))->execute();
        return new DataResponse(['status' => 'success']);
    }
}