<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCA\StechTimesheet\Service\AnalysisService;

class PageController extends Controller {
    private $userSession;
    private $groupManager;
    private $analysisService;

    public function __construct(IRequest $request, 
                                IUserSession $userSession, 
                                IGroupManager $groupManager,
                                AnalysisService $analysisService) {
        parent::__construct('stech_timesheet', $request);
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->analysisService = $analysisService;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $user = $this->userSession->getUser();
        $uid = $user ? $user->getUID() : '';
        $isAdmin = $user && $this->groupManager->isAdmin($uid);

        // Use Service for consistent checks
        $canViewAnalysis = $this->analysisService->checkAccess('analysis_tab');
        $canViewAdmin = $isAdmin || $this->analysisService->checkAccess('admin_panel');

        $response = new TemplateResponse('stech_timesheet', 'main');
        $response->setParams([
            'user_id' => $uid,
            'is_admin' => $isAdmin,
            'can_view_analysis' => $canViewAnalysis,
            'can_view_admin' => $canViewAdmin,
            'target_user' => $this->request->getParam('target_user', '')
        ]);
        
        return $response;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function analysis_page(): TemplateResponse {
        if (!$this->analysisService->checkAccess('analysis_tab')) {
            return new TemplateResponse('stech_timesheet', 'error', ['msg' => 'Access Denied'], 403);
        }

        $canViewOthers = $this->analysisService->checkAccess('analysis_view_others');
        $canViewTravel = $this->analysisService->checkAccess('analysis_travel');
        $canViewFinancial = $this->analysisService->checkAccess('analysis_financial');
        $canViewLocation = $this->analysisService->checkAccess('analysis_location');
        $canViewJobBreakdown = $this->analysisService->checkAccess('analysis_job_breakdown');
        
        if ($canViewFinancial) {
            $canViewJobBreakdown = true;
        }

        $response = new TemplateResponse('stech_timesheet', 'analysis');
        $response->setParams([
            'can_view_others' => (bool)$canViewOthers,
            'can_view_travel_analytics' => (bool)$canViewTravel,
            'can_view_financial_analytics' => (bool)$canViewFinancial,
            'can_view_location_analytics' => (bool)$canViewLocation,
            'can_view_job_breakdown' => (bool)$canViewJobBreakdown
        ]);
        
        return $response;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function admin_page(): TemplateResponse {
        $user = $this->userSession->getUser();
        $isAdmin = $user && $this->groupManager->isAdmin($user->getUID());
        
        if (!$isAdmin && !$this->analysisService->checkAccess('admin_panel')) {
             return new TemplateResponse('stech_timesheet', 'error', ['msg' => 'Access Denied'], 403);
        }
        return new TemplateResponse('stech_timesheet', 'admin');
    }
}