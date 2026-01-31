<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\StreamResponse;
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

    /** @NoCSRFRequired @NoAdminRequired */
    public function index(): TemplateResponse {
        if (!$this->isAdmin()) return new TemplateResponse('core', '403', [], '403');
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
        if(!$key) return;
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
        $local = \OC::$server->getAppManager()->getAppPath('stech_timesheet') . '/img/' . $filename;
        if (file_exists($local)) return new StreamResponse(fopen($local, 'rb'));
        try {
            $file = $this->appData->getFolder('thumbnails')->getFile($filename);
            return new FileDisplayResponse($file);
        } catch (\Exception $e) { return new DataResponse(['error' => 'Not found'], 404); }
    }

    /** @NoCSRFRequired */
    public function uploadThumbnail(string $cardId): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $u = $this->request->getUploadedFile('image');
        $s = null;
        if ($u) { if (is_array($u)) $u = $u[0]??null; if($u) $s = $u->getStream(); }
        if (!$s && isset($_FILES['image']) && $_FILES['image']['error']===0) $s = fopen($_FILES['image']['tmp_name'], 'rb');
        if (!$s) return new DataResponse(['error'=>'Upload failed'], 400);

        $name = 'thumb-'.$cardId.'.png';
        $local = \OC::$server->getAppManager()->getAppPath('stech_timesheet').'/img/'.$name;
        
        if (is_writable(dirname($local))) {
            file_put_contents($local, stream_get_contents($s));
        } else {
            try {
                try { $f = $this->appData->getFolder('thumbnails'); } catch (NotFoundException $e) { $f = $this->appData->newFolder('thumbnails'); }
                try { $f->getFile($name)->delete(); } catch(NotFoundException $e) {}
                $f->newFile($name)->putContent($s);
            } catch (\Exception $e) { return new DataResponse(['error'=>$e->getMessage()], 500); }
        }
        $this->saveSettingValue('thumb_path_'.$cardId, $name);
        return new DataResponse(['status'=>'success']);
    }

    // --- USERS & HOLIDAYS ---
    /** @NoCSRFRequired */
    public function getUsers(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $users = $this->userManager->search('');
        $res = []; foreach ($users as $u) $res[] = ['uid' => $u->getUID(), 'displayname' => $u->getDisplayName()];
        return new DataResponse($res);
    }
    /** @NoCSRFRequired */
    public function getHolidays(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        return new DataResponse($this->db->getQueryBuilder()->select('*')->from('stech_holidays')->orderBy('holiday_start_date', 'DESC')->executeQuery()->fetchAll());
    }
    /** @NoCSRFRequired */
    public function saveHoliday(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $d = $this->request->getParams();
        $qb = $this->db->getQueryBuilder();
        if (!empty($d['id'])) {
            $qb->update('stech_holidays')->set('holiday_name', $qb->createNamedParameter($d['name']))->set('holiday_start_date', $qb->createNamedParameter($d['start']))->set('holiday_end_date', $qb->createNamedParameter($d['end']))->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($d['id'])))->execute();
        } else {
            $qb->insert('stech_holidays')->values(['holiday_name' => $qb->createNamedParameter($d['name']), 'holiday_start_date' => $qb->createNamedParameter($d['start']), 'holiday_end_date' => $qb->createNamedParameter($d['end'])])->execute();
        }
        return new DataResponse(['status'=>'success']);
    }
    /** @NoCSRFRequired */
    public function deleteHoliday(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $this->db->getQueryBuilder()->delete('stech_holidays')->where($this->db->getQueryBuilder()->expr()->eq('holiday_id', $this->db->getQueryBuilder()->createNamedParameter($id)))->execute();
        return new DataResponse(['status'=>'success']);
    }

    // --- JOBS (UPDATED) ---
    
    /** @NoCSRFRequired */
    public function getJobs(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        // Returns ALL jobs (Active & Archived)
        $qb = $this->db->getQueryBuilder();
        $jobs = $qb->select('*')->from('stech_jobs')->orderBy('job_name', 'ASC')->executeQuery()->fetchAll();
        return new DataResponse($jobs);
    }

    /** @NoCSRFRequired */
    public function saveJob(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $d = $this->request->getParams();
        $qb = $this->db->getQueryBuilder();
        $qb->insert('stech_jobs')->values(['job_name' => $qb->createNamedParameter($d['name']), 'job_description' => $qb->createNamedParameter($d['description'] ?? ''), 'job_archive' => $qb->createNamedParameter(0)])->execute();
        return new DataResponse(['status'=>'success']);
    }
    /** @NoCSRFRequired */
    public function toggleJob(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        $c = $qb->select('job_archive')->from('stech_jobs')->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))->executeQuery()->fetchOne();
        $n = ((int)$c === 1) ? 0 : 1;
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_jobs')->set('job_archive', $qb->createNamedParameter($n))->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))->execute();
        return new DataResponse(['status'=>'success']);
    }

    // --- LOCATIONS ---
    /** @NoCSRFRequired */
    public function getStates(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        $res = $qb->select('*')->from('stech_states')->orderBy('state_name', 'ASC')->executeQuery()->fetchAll();
        return new DataResponse($res);
    }
    /** @NoCSRFRequired */
    public function getCounties(string $stateAbbr): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qbS = $this->db->getQueryBuilder();
        $st = $qbS->select('fips_code')->from('stech_states')->where($qbS->expr()->eq('state_abbr', $qbS->createNamedParameter($stateAbbr)))->executeQuery()->fetch();
        if(!$st) return new DataResponse([]);
        $qb = $this->db->getQueryBuilder();
        $res = $qb->select('*')->from('stech_counties')->where($qb->expr()->eq('state_fips', $qb->createNamedParameter($st['fips_code'])))->orderBy('county_name', 'ASC')->executeQuery()->fetchAll();
        return new DataResponse($res);
    }
    /** @NoCSRFRequired */
    public function toggleState(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        $c = $qb->select('is_enabled')->from('stech_states')->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->executeQuery()->fetchOne();
        $n = ((int)$c === 1) ? 0 : 1;
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_states')->set('is_enabled', $qb->createNamedParameter($n))->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->execute();
        return new DataResponse(['status'=>'success']);
    }
    /** @NoCSRFRequired */
    public function toggleCounty(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        $c = $qb->select('is_enabled')->from('stech_counties')->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->executeQuery()->fetchOne();
        $n = ((int)$c === 1) ? 0 : 1;
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_counties')->set('is_enabled', $qb->createNamedParameter($n))->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->execute();
        return new DataResponse(['status'=>'success']);
    }
}