<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\IUserManager;

class AdminController extends Controller {
    private $db;
    private $userSession;
    private $userManager;
    private $groupManager;

    public function __construct(IRequest $request, IDBConnection $db, IUserSession $userSession, IUserManager $userManager, IGroupManager $groupManager) {
        parent::__construct('stech_timesheet', $request);
        $this->db = $db;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
    }

    private function isAdmin(): bool {
        return $this->groupManager->isAdmin($this->userSession->getUser()->getUID());
    }

    /**
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        if (!$this->isAdmin()) {
            return new TemplateResponse('core', '403', [], '403');
        }
        return new TemplateResponse('stech_timesheet', 'admin');
    }

    /**
     * @NoCSRFRequired
     */
    public function getUsers(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);

        $users = $this->userManager->search('');
        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'uid' => $user->getUID(),
                'displayname' => $user->getDisplayName()
            ];
        }
        return new DataResponse($result);
    }

    /**
     * @NoCSRFRequired
     */
    public function getHolidays(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('stech_holidays')->orderBy('holiday_start_date', 'DESC');
        return new DataResponse($qb->executeQuery()->fetchAll());
    }

    /**
     * @NoCSRFRequired
     */
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
            $qb->insert('stech_holidays')
               ->values([
                   'holiday_name' => $qb->createNamedParameter($data['name']),
                   'holiday_start_date' => $qb->createNamedParameter($data['start']),
                   'holiday_end_date' => $qb->createNamedParameter($data['end']),
               ])
               ->execute();
        }
        return new DataResponse(['status' => 'success']);
    }

    /**
     * @NoCSRFRequired
     */
    public function deleteHoliday(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        $qb->delete('stech_holidays')->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))->execute();
        return new DataResponse(['status' => 'success']);
    }

    /**
     * @NoCSRFRequired
     */
    public function saveJob(): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $data = $this->request->getParams();
        
        $qb = $this->db->getQueryBuilder();
        $qb->insert('stech_jobs')
           ->values([
               'job_name' => $qb->createNamedParameter($data['name']),
               'job_description' => $qb->createNamedParameter($data['description'] ?? ''),
               'job_archive' => $qb->createNamedParameter(0, \PDO::PARAM_INT),
           ])->execute();
        return new DataResponse(['status' => 'success']);
    }

    /**
     * @NoCSRFRequired
     */
    public function toggleJob(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        // Toggle archive status
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_jobs')
           ->set('job_archive', '1 - job_archive') // SQL toggle
           ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))
           ->execute();
        return new DataResponse(['status' => 'success']);
    }

    /**
     * @NoCSRFRequired
     */
    public function toggleState(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_states')
           ->set('is_enabled', '1 - is_enabled')
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
           ->execute();
        return new DataResponse(['status' => 'success']);
    }

    /**
     * @NoCSRFRequired
     */
    public function toggleCounty(int $id): DataResponse {
        if (!$this->isAdmin()) return new DataResponse([], 403);
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_counties')
           ->set('is_enabled', '1 - is_enabled')
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
           ->execute();
        return new DataResponse(['status' => 'success']);
    }
}