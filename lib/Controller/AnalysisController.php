<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUserSession;
use OCP\IUserManager;
use OCA\StechTimesheet\Service\AnalysisService;
use OCA\StechTimesheet\Db\TimesheetMapper;
use OCA\StechTimesheet\Db\AnalysisMapper;

class AnalysisController extends Controller {
    private $service;
    private $tsMapper;
    private $anMapper;
    private $userSession;
    private $userManager;

    public function __construct(IRequest $request, AnalysisService $service, TimesheetMapper $tsMapper, AnalysisMapper $anMapper, IUserSession $userSession, IUserManager $userManager) {
        parent::__construct('stech_timesheet', $request);
        $this->service = $service;
        $this->tsMapper = $tsMapper;
        $this->anMapper = $anMapper;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getFilters(): DataResponse {
        $currentUser = $this->userSession->getUser();
        $uid = $currentUser->getUID();
        $users = [];

        // FIX: "Everyone" Option Logic
        if ($this->service->checkAccess('analysis_view_others')) {
            // Add "Everyone" as the first option
            $users[] = ['uid' => 'all', 'displayname' => 'Everyone'];
            
            // Add all specific users (Admin/Manager view)
            $allUsers = $this->userManager->search('');
            foreach($allUsers as $u) {
                $users[] = ['uid' => $u->getUID(), 'displayname' => $u->getDisplayName()];
            }
        } else {
            // Non-admins just see themselves
            $users[] = ['uid' => $uid, 'displayname' => $currentUser->getDisplayName()];
        }

        return new DataResponse([
            'users' => $users,
            'jobs' => $this->tsMapper->getActiveJobs(),
            'states' => $this->tsMapper->getEnabledStates()
        ]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getStats(string $period, string $target_user = 'self'): DataResponse {
        if (!$this->service->checkAccess('analysis_tab')) {
            return new DataResponse(['error' => 'Denied'], 403);
        }
        
        $uid = $this->userSession->getUser()->getUID();
        
        // FIX: Target Selection Logic
        // 1. If 'self', always use current UID.
        // 2. If 'all', check permissions -> if valid, use NULL (mapper fetches all).
        // 3. If specific user, check permissions -> if valid, use that UID.
        // 4. Fallback -> current UID.
        
        $target = $uid;

        if ($this->service->checkAccess('analysis_view_others')) {
            if ($target_user === 'all') {
                $target = null; // null tells Mapper to fetch EVERYONE
            } elseif ($target_user !== 'self') {
                $target = $target_user;
            }
        }

        list($start, $end) = $this->service->getPayrollDateRange($period);
        
        // Fetch raw data from Mapper
        $results = $this->anMapper->getFullReportingData($start, $end, $target);

        $perms = [
            'travel' => $this->service->checkAccess('analysis_travel'),
            'jobs' => $this->service->checkAccess('analysis_job_breakdown'),
            'location' => $this->service->checkAccess('analysis_location')
        ];

        return new DataResponse($this->service->aggregateData($results, $perms));
    }
}