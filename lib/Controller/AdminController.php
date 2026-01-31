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

    // --- STANDARD ADMIN METHODS ---

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
        $results = $qb->select('*')
                      ->from('stech_holidays')
                      ->orderBy('holiday_start_date', 'DESC')
                      ->executeQuery()
                      ->fetchAll();
        return new DataResponse($results);
    }

    /** @NoCSRFRequired */
    public function saveHoliday(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $data = $this->request->getParams();
        
        // FIX: Use single QB instance for parameters
        $qb = $this->db->getQueryBuilder();
        $qb->insert('stech_holidays')
           ->values([
               'holiday_name' => $qb->createNamedParameter($data['name']),
               'holiday_start_date' => $qb->createNamedParameter($data['start']),
               'holiday_end_date' => $qb->createNamedParameter($data['end'])
           ])
           ->execute();
           
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function deleteHoliday(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        
        $qb = $this->db->getQueryBuilder();
        $qb->delete('stech_holidays')
           ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
           ->execute();
           
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function saveJob(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $data = $this->request->getParams();
        
        // FIX: Use single QB instance
        $qb = $this->db->getQueryBuilder();
        $qb->insert('stech_jobs')
           ->values([
               'job_name' => $qb->createNamedParameter($data['name']),
               'job_description' => $qb->createNamedParameter($data['description'] ?? ''),
               'job_archive' => $qb->createNamedParameter(0)
           ])
           ->execute();
           
        return new DataResponse(['status' => 'success']);
    }

    // --- TOGGLE METHODS (Read -> Flip -> Write) ---

    /** @NoCSRFRequired */
    public function toggleJob(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        
        // 1. Read Current
        $qb = $this->db->getQueryBuilder();
        $current = $qb->select('job_archive')
                      ->from('stech_jobs')
                      ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))
                      ->executeQuery()
                      ->fetchOne();
        
        $newState = ((int)$current === 1) ? 0 : 1;
        
        // 2. Write New
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_jobs')
           ->set('job_archive', $qb->createNamedParameter($newState))
           ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))
           ->execute();
           
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function toggleState(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        
        $qb = $this->db->getQueryBuilder();
        $current = $qb->select('is_enabled')
                      ->from('stech_states')
                      ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
                      ->executeQuery()
                      ->fetchOne();
        
        $newState = ((int)$current === 1) ? 0 : 1;
        
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_states')
           ->set('is_enabled', $qb->createNamedParameter($newState))
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
           ->execute();
           
        return new DataResponse(['status' => 'success']);
    }

    /** @NoCSRFRequired */
    public function toggleCounty(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        
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