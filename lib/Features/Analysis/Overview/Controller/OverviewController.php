<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\Overview\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCA\StechTimesheet\Features\Analysis\Dashboard\Service\DashboardService;
use OCA\StechTimesheet\Features\Analysis\Overview\Service\OverviewService;

class OverviewController extends Controller {
    private $dashboardService;
    private $overviewService;

    public function __construct(IRequest $request, 
                                DashboardService $dashboardService,
                                OverviewService $overviewService) {
        parent::__construct('stech_timesheet', $request);
        $this->dashboardService = $dashboardService;
        $this->overviewService = $overviewService;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getData(): DataResponse {
        // 1. Check Access
        if (!$this->dashboardService->checkAccess('analysis_tab')) {
            return new DataResponse(['error' => 'Denied'], 403);
        }

        // 2. Get Params
        $period = $this->request->getParam('period', 'this_pay_period');
        $targetUser = $this->request->getParam('target_user', 'self');
        
        // Handle Target User Logic
        if ($targetUser === 'all') {
            if (!$this->dashboardService->checkAccess('analysis_view_others')) {
                return new DataResponse(['error' => 'Denied viewing others'], 403);
            }
            $targetUser = null; // null = fetch all
        } elseif ($targetUser === 'self') {
            // DashboardService/Mapper should handle 'self' logic or we pass UID?
            // Usually we pass the current UID. Let's fix that.
            // However, DashboardService::getRawData expects ?string $userId
            // If we pass 'self', the Mapper might not find it. 
            // We should resolve the UID here or let DashboardService handle it.
            // Looking at previous DashboardService, it takes ?string $targetUser directly to mapper.
            // So we need to resolve it here.
            
            // Correction: In original code, 'self' became $uid.
             $targetUser = \OC::$server->getUserSession()->getUser()->getUID();
        }

        // 3. Fetch Raw Data
        $customStart = $this->request->getParam('start');
        $customEnd = $this->request->getParam('end');
        
        $rows = $this->dashboardService->getRawData($period, $targetUser, $customStart, $customEnd);

        // 4. Process
        $data = $this->overviewService->process($rows);

        return new DataResponse($data);
    }
}