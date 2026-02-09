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

        // Permissions for Main Page Elements
        $canViewAnalysis = $this->analysisService->checkAccess('analysis_tab');
        $canViewAdmin = $isAdmin || $this->analysisService->checkAccess('admin_global_access');
        
        // This is the specific variable that controls the "Show Archived" button
        $canToggleArchive = $this->analysisService->checkAccess('view_archive_toggle');

        $response = new TemplateResponse('stech_timesheet', 'main');
        $response->setParams([
            'user_id' => $uid,
            'is_admin' => $isAdmin,
            'can_view_analysis' => $canViewAnalysis,
            'can_view_admin' => $canViewAdmin,
            'can_toggle_archive' => $canToggleArchive, 
            'target_user' => $this->request->getParam('target_user', '')
        ]);
        
        return $response;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function analysisPage(): TemplateResponse {
        if (!$this->analysisService->checkAccess('analysis_tab')) {
            return new TemplateResponse('stech_timesheet', 'error', ['msg' => 'Access Denied'], 403);
        }

        $perms = [
            'can_view_others' => (bool)$this->analysisService->checkAccess('analysis_view_others'),
            'can_view_travel_analytics' => (bool)$this->analysisService->checkAccess('analysis_travel'),
            'can_view_financial_analytics' => (bool)$this->analysisService->checkAccess('analysis_financial'),
            'can_view_location_analytics' => (bool)$this->analysisService->checkAccess('analysis_location'),
            'can_view_job_breakdown' => (bool)($this->analysisService->checkAccess('analysis_financial') || $this->analysisService->checkAccess('analysis_job_breakdown'))
        ];

        $response = new TemplateResponse('stech_timesheet', 'analysis');
        $response->setParams($perms);
        
        return $response;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function adminPage(): TemplateResponse {
        $user = $this->userSession->getUser();
        $isAdmin = $user && $this->groupManager->isAdmin($user->getUID());
        
        if (!$isAdmin && !$this->analysisService->checkAccess('admin_global_access')) {
             return new TemplateResponse('stech_timesheet', 'error', ['msg' => 'Access Denied'], 403);
        }

        $perms = [
            'can_access_users' => $this->analysisService->checkAccess('admin_users'),
            'can_access_payroll' => $this->analysisService->checkAccess('admin_payroll'),
            'can_access_holidays' => $this->analysisService->checkAccess('admin_holidays'),
            'can_access_jobs' => $this->analysisService->checkAccess('admin_jobs'),
            'can_access_locations' => $this->analysisService->checkAccess('admin_locations'),
            'can_access_access' => $this->analysisService->checkAccess('admin_access'),
        ];

        $response = new TemplateResponse('stech_timesheet', 'admin');
        $response->setParams($perms);

        return $response;
    }
}