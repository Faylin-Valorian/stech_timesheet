<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Timesheet\Calendar\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCA\StechTimesheet\Features\Timesheet\Calendar\Service\CalendarService;

class CalendarController extends Controller {
    private $service;
    private $userSession;
    private $groupManager;

    public function __construct(IRequest $request, 
                                CalendarService $service,
                                IUserSession $userSession,
                                IGroupManager $groupManager) {
        parent::__construct('stech_timesheet', $request);
        $this->service = $service;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
    }

    private function getEffectiveUserId(): string {
        $currentUser = $this->userSession->getUser();
        if (!$currentUser) return ''; 
        
        $currentUid = $currentUser->getUID();
        $targetUid = $this->request->getParam('target_user');

        if ($targetUid && $targetUid !== $currentUid) {
            if ($this->groupManager->isAdmin($currentUid)) {
                return $targetUid;
            }
        }
        return $currentUid;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getEvents(): DataResponse {
        $uid = $this->getEffectiveUserId(); 
        $start = $this->request->getParam('start');
        $end = $this->request->getParam('end');
        $archive = (int)$this->request->getParam('archive', 0);
        
        return new DataResponse($this->service->getFormattedEvents($uid, $start, $end, $archive));
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getHolidays(): DataResponse {
        $start = $this->request->getParam('start');
        $end = $this->request->getParam('end');
        return new DataResponse($this->service->getHolidays($start, $end));
    }
}