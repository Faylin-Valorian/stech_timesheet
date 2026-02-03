<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUserSession;
use OCA\StechTimesheet\Service\TimesheetService;

class TimesheetController extends Controller {
    private $userSession;
    private $service;

    public function __construct(IRequest $request, IUserSession $userSession, TimesheetService $service) {
        parent::__construct('stech_timesheet', $request);
        $this->userSession = $userSession;
        $this->service = $service;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getTimesheets(string $start, string $end): DataResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new DataResponse([], 403);
        }

        $events = $this->service->getCalendarEvents($user->getUID(), $start, $end);
        return new DataResponse($events);
    }
}