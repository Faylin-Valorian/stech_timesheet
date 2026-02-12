<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\Travel\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCA\StechTimesheet\Features\Analysis\Dashboard\Service\DashboardService;
use OCA\StechTimesheet\Features\Analysis\Travel\Service\TravelService;

class TravelController extends Controller {
    private $dashboardService;
    private $travelService;

    public function __construct(IRequest $request, 
                                DashboardService $dashboardService,
                                TravelService $travelService) {
        parent::__construct('stech_timesheet', $request);
        $this->dashboardService = $dashboardService;
        $this->travelService = $travelService;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getData(): DataResponse {
        // 1. Check Specific Permission
        if (!$this->dashboardService->checkAccess('analysis_travel')) {
            return new DataResponse(['error' => 'Denied'], 403);
        }

        // 2. Get Params
        $period = $this->request->getParam('period', 'this_pay_period');
        $targetUser = $this->request->getParam('target_user', 'self');
        
        if ($targetUser === 'all') {
            if (!$this->dashboardService->checkAccess('analysis_view_others')) {
                return new DataResponse(['error' => 'Denied viewing others'], 403);
            }
            $targetUser = null;
        } elseif ($targetUser === 'self') {
             $targetUser = \OC::$server->getUserSession()->getUser()->getUID();
        }

        // 3. Fetch Raw Data
        $rows = $this->dashboardService->getRawData(
            $period, 
            $targetUser, 
            $this->request->getParam('start'), 
            $this->request->getParam('end')
        );

        // 4. Process
        $data = $this->travelService->process($rows);

        return new DataResponse($data);
    }
}