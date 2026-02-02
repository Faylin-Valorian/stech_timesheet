<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IGroupManager;

class PageController extends Controller {
    private $userSession;
    private $db;
    private $groupManager;

    public function __construct(IRequest $request, IUserSession $userSession, IDBConnection $db, IGroupManager $groupManager) {
        parent::__construct('stech_timesheet', $request);
        $this->userSession = $userSession;
        $this->db = $db;
        $this->groupManager = $groupManager;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $user = $this->userSession->getUser();
        $uid = $user ? $user->getUID() : null;

        $canViewAdmin = $this->checkAccess($uid, 'admin_panel');
        $canViewAnalysis = $this->checkAccess($uid, 'analysis_tab');
        
        $response = new TemplateResponse('stech_timesheet', 'main');
        $response->setParams([
            'target_user' => $this->request->getParam('target_user', ''),
            'can_view_admin' => $canViewAdmin,
            'can_view_analysis' => $canViewAnalysis
        ]);
        
        return $response;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function analysis(): TemplateResponse {
        $user = $this->userSession->getUser();
        $uid = $user ? $user->getUID() : null;

        // 1. Basic Access Check
        if (!$this->checkAccess($uid, 'analysis_tab')) {
            $response = new TemplateResponse('stech_timesheet', 'error');
            $response->setParams(['msg' => 'You do not have permission to view the Analysis Dashboard.']);
            return $response;
        }

        // 2. Feature Checks (The new Access Control Keys)
        $canViewOthers = $this->checkAccess($uid, 'analysis_view_others');
        $canViewTravel = $this->checkAccess($uid, 'analysis_travel');
        $canViewFinancial = $this->checkAccess($uid, 'analysis_financial'); // Covers Profit & Jobs
        $canViewLocation = $this->checkAccess($uid, 'analysis_location'); // Covers Maps
        
        // Logic: If user has Financial Access, they inherently get Job Breakdown access.
        // Otherwise, check for specific breakdown permission.
        $canViewJobBreakdown = $this->checkAccess($uid, 'analysis_job_breakdown');
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
     * Helper to check access rules
     */
    private function checkAccess($uid, $ruleKey): bool {
        if (!$uid) return false;

        // Admin always has access
        if ($this->groupManager->isAdmin($uid)) {
            return true;
        }

        try {
            $qb = $this->db->getQueryBuilder();
            $result = $qb->select('allowed_groups')
                         ->from('stech_access_rules')
                         ->where($qb->expr()->eq('rule_key', $qb->createNamedParameter($ruleKey)))
                         ->executeQuery()
                         ->fetch();

            if (!$result) return false;

            $allowedGroups = json_decode($result['allowed_groups'], true);
            if (!is_array($allowedGroups) || empty($allowedGroups)) return false;

            $userGroups = $this->groupManager->getUserGroupIds($this->userSession->getUser());
            
            foreach ($userGroups as $gid) {
                if (in_array($gid, $allowedGroups)) {
                    return true;
                }
            }

        } catch (\Exception $e) {
            return false;
        }

        return false;
    }
}