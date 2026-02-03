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

    public function getFilters(): DataResponse {
        $uid = $this->userSession->getUser()->getUID();
        $users = [];
        if ($this->service->checkAccess('analysis_view_others')) {
            foreach($this->userManager->search('') as $u) {
                $users[] = ['uid' => $u->getUID(), 'displayname' => $u->getDisplayName()];
            }
        } else {
            $users[] = ['uid' => $uid, 'displayname' => $this->userSession->getUser()->getDisplayName()];
        }

        return new DataResponse([
            'users' => $users,
            'jobs' => $this->tsMapper->getActiveJobs(),
            'states' => $this->tsMapper->getEnabledStates()
        ]);
    }

    public function getStats(string $period, string $target_user = 'self'): DataResponse {
        if (!$this->service->checkAccess('analysis_tab')) return new DataResponse(['error' => 'Denied'], 403);
        
        $uid = $this->userSession->getUser()->getUID();
        $target = ($target_user === 'self') ? $uid : (($this->service->checkAccess('analysis_view_others')) ? ($target_user === 'all' ? null : $target_user) : $uid);

        list($start, $end) = $this->service->getPayrollDateRange($period);
        $results = $this->anMapper->getFullReportingData($start, $end, $target);

        $perms = [
            'travel' => $this->service->checkAccess('analysis_travel'),
            'jobs' => $this->service->checkAccess('analysis_job_breakdown'),
            'location' => $this->service->checkAccess('analysis_location')
        ];

        return new DataResponse($this->service->aggregateData($results, $perms));
    }
}