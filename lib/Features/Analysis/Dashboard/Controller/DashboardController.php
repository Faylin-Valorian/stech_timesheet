<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\Dashboard\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUserSession;
use OCP\IUserManager;
use OCA\StechTimesheet\Features\Analysis\Dashboard\Service\DashboardService;
use OCA\StechTimesheet\Db\TimesheetMapper; // Core dependency for filters

class DashboardController extends Controller {
    private $service;
    private $tsMapper;
    private $userSession;
    private $userManager;

    public function __construct(IRequest $request, 
                                DashboardService $service, 
                                TimesheetMapper $tsMapper, 
                                IUserSession $userSession, 
                                IUserManager $userManager) {
        parent::__construct('stech_timesheet', $request);
        $this->service = $service;
        $this->tsMapper = $tsMapper;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
    }

    /**
     * Returns the dropdown options for Users, Jobs, and States
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getFilters(): DataResponse {
        $currentUser = $this->userSession->getUser();
        $uid = $currentUser->getUID();
        $users = [];

        // Check if user has permission to view others
        if ($this->service->checkAccess('analysis_view_others')) {
            $users[] = ['uid' => 'all', 'displayname' => 'Everyone'];
            $allUsers = $this->userManager->search('');
            foreach($allUsers as $u) {
                $users[] = ['uid' => $u->getUID(), 'displayname' => $u->getDisplayName()];
            }
        } else {
            $users[] = ['uid' => $uid, 'displayname' => $currentUser->getDisplayName()];
        }

        return new DataResponse([
            'users' => $users,
            'jobs' => $this->tsMapper->getActiveJobs(),
            'states' => $this->tsMapper->getEnabledStates()
        ]);
    }
}