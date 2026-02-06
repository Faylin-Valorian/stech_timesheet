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

        // Security Check: Can they view Analysis?
        $canViewAnalysis = $this->analysisService->checkAccess('analysis_tab');
        
        // Security Check: Can they view Admin? (Admins OR explicit permission)
        $canViewAdmin = $isAdmin || $this->analysisService->checkAccess('admin_panel');

        $params = [
            'user_id' => $uid,
            'is_admin' => $isAdmin,
            'can_view_analysis' => $canViewAnalysis,
            'can_view_admin' => $canViewAdmin,
            'target_user' => $this->request->getParam('target_user', '') // Support impersonation
        ];

        return new TemplateResponse('stech_timesheet', 'main', $params);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function analysis_page(): TemplateResponse {
        // Enforce Security on the Page Load too
        if (!$this->analysisService->checkAccess('analysis_tab')) {
            return new TemplateResponse('stech_timesheet', 'error', ['msg' => 'Access Denied'], 403);
        }
        
        // FEATURE FLAGS for the Analysis View
        $canViewOthers = $this->analysisService->checkAccess('analysis_view_others');
        $canViewTravel = $this->analysisService->checkAccess('analysis_travel');
        $canViewFinancial = $this->analysisService->checkAccess('analysis_financial');
        $canViewLocation = $this->analysisService->checkAccess('analysis_location');
        $canViewJobBreakdown = $this->analysisService->checkAccess('analysis_job_breakdown');
        
        // Financial access implies job breakdown access
        if ($canViewFinancial) {
            $canViewJobBreakdown = true;
        }

        $params = [
            'can_view_others' => (bool)$canViewOthers,
            'can_view_travel_analytics' => (bool)$canViewTravel,
            'can_view_financial_analytics' => (bool)$canViewFinancial,
            'can_view_location_analytics' => (bool)$canViewLocation,
            'can_view_job_breakdown' => (bool)$canViewJobBreakdown
        ];

        return new TemplateResponse('stech_timesheet', 'analysis', $params);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function admin_page(): TemplateResponse {
        $user = $this->userSession->getUser();
        $isAdmin = $user && $this->groupManager->isAdmin($user->getUID());
        
        // Allow if Admin OR has explicit 'admin_panel' permission
        if (!$isAdmin && !$this->analysisService->checkAccess('admin_panel')) {
             return new TemplateResponse('stech_timesheet', 'error', ['msg' => 'Access Denied'], 403);
        }
        return new TemplateResponse('stech_timesheet', 'admin');
    }
}