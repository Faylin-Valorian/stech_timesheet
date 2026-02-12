<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\JobBreakdown\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCA\StechTimesheet\Features\Analysis\Dashboard\Service\DashboardService;
use OCA\StechTimesheet\Features\Analysis\JobBreakdown\Service\JobBreakdownService;

class JobBreakdownController extends Controller {
    private $dashboardService;
    private $breakdownService;

    public function __construct(IRequest $request, 
                                DashboardService $dashboardService,
                                JobBreakdownService $breakdownService) {
        parent::__construct('stech_timesheet', $request);
        $this->dashboardService = $dashboardService;
        $this->breakdownService = $breakdownService;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getData(): DataResponse {
        // Specific Permission for Breakdown
        if (!$this->dashboardService->checkAccess('analysis_job_breakdown')) {
            return new DataResponse(['error' => 'Denied'], 403);
        }

        $period = $this->request->getParam('period', 'this_pay_period');
        $targetUser = $this->request->getParam('target_user', 'self');
        
        // Target User Logic
        if ($targetUser === 'all') {
             if (!$this->dashboardService->checkAccess('analysis_view_others')) return new DataResponse([], 403);
             $targetUser = null;
        } elseif ($targetUser === 'self') {
             $targetUser = \OC::$server->getUserSession()->getUser()->getUID();
        }

        $rows = $this->dashboardService->getRawData(
            $period, 
            $targetUser, 
            $this->request->getParam('start'), 
            $this->request->getParam('end')
        );

        return new DataResponse($this->breakdownService->process($rows));
    }
}