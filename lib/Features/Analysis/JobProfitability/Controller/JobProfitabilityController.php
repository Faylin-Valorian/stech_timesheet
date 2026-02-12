<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\JobProfitability\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCA\StechTimesheet\Features\Analysis\Dashboard\Service\DashboardService;
use OCA\StechTimesheet\Features\Analysis\JobProfitability\Service\JobProfitabilityService;

class JobProfitabilityController extends Controller {
    private $dashboardService;
    private $profitService;

    public function __construct(IRequest $request, 
                                DashboardService $dashboardService,
                                JobProfitabilityService $profitService) {
        parent::__construct('stech_timesheet', $request);
        $this->dashboardService = $dashboardService;
        $this->profitService = $profitService;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getData(): DataResponse {
        // STRICTER Permission for Profitability
        // You might want a specific 'analysis_profit' permission in the future.
        // For now, we reuse 'analysis_job_breakdown' OR 'admin_jobs'
        if (!$this->dashboardService->checkAccess('analysis_job_breakdown')) {
            return new DataResponse(['error' => 'Denied'], 403);
        }

        $period = $this->request->getParam('period', 'this_pay_period');
        $targetUser = $this->request->getParam('target_user', 'self');
        
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

        return new DataResponse($this->profitService->process($rows));
    }
}