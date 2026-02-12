<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IUserSession;
use OCP\IGroupManager;
// IMPORTANT: Import the attributes
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

use OCA\StechTimesheet\Features\Analysis\Dashboard\Service\DashboardService;

class PageController extends Controller {
    private $userSession;
    private $groupManager;
    private $dashboardService;

    public function __construct(IRequest $request, 
                                IUserSession $userSession, 
                                IGroupManager $groupManager,
                                DashboardService $dashboardService) {
        // Ensure this matches your app folder name exactly ('stech_timesheet')
        parent::__construct('stech_timesheet', $request);
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->dashboardService = $dashboardService;
    }

    /**
     * Main Timesheet View
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        $user = $this->userSession->getUser();
        $uid = $user ? $user->getUID() : '';
        $isAdmin = $this->groupManager->isAdmin($uid);

        // Security: Check if they can see the Analysis tab
        $canViewAnalysis = $this->dashboardService->checkAccess('analysis_tab');
        
        return new TemplateResponse('stech_timesheet', 'main', [
            'user_id' => $uid,
            'is_admin' => $isAdmin,
            'can_view_admin' => $isAdmin,
            'can_view_analysis' => $canViewAnalysis,
            'target_user' => $this->request->getParam('target_user')
        ]);
    }

    /**
     * Analysis Dashboard View
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function analysis(): TemplateResponse {
        if (!$this->dashboardService->checkAccess('analysis_tab')) {
            return new TemplateResponse('stech_timesheet', 'error', ['msg' => 'Access Denied'], '403');
        }
        return new TemplateResponse('stech_timesheet', 'analysis');
    }

    /**
     * Admin Settings View
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function admin(): TemplateResponse {
        $user = $this->userSession->getUser();
        if (!$user || !$this->groupManager->isAdmin($user->getUID())) {
             return new TemplateResponse('stech_timesheet', 'error', ['msg' => 'Admin Access Only'], '403');
        }
        return new TemplateResponse('stech_timesheet', 'admin');
    }
}