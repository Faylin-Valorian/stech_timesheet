<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\User\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCA\StechTimesheet\Features\Admin\User\Service\UserService;
use OCA\StechTimesheet\Service\AnalysisService;

class UserController extends Controller {
    private $service;
    private $analysisService;

    public function __construct(IRequest $request, 
                                UserService $service,
                                AnalysisService $analysisService) {
        parent::__construct('stech_timesheet', $request);
        $this->service = $service;
        $this->analysisService = $analysisService;
    }

    private function requireAccess(string $rule = 'admin_users'): void {
        // Use 'admin_access' for permission settings, 'admin_users' for user list
        if (!$this->analysisService->checkAccess($rule)) {
            throw new \Exception("Access Denied: Missing permission '$rule'");
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getUsers(): DataResponse { 
        try { 
            $this->requireAccess('admin_users'); 
            return new DataResponse($this->service->getAllUsers()); 
        } catch(\Exception $e) { return new DataResponse([], 403); }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function toggleUser(): DataResponse {
        try { 
            $this->requireAccess('admin_users');
            $uid = $this->request->getParam('uid');
            return new DataResponse(['status' => 'success', 'new_state' => $this->service->toggleUserStatus($uid)]);
        } catch(\Exception $e) { return new DataResponse([], 403); }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getGroups(): DataResponse {
        try { 
            $this->requireAccess('admin_access'); 
            return new DataResponse($this->service->getAllGroups()); 
        } catch(\Exception $e) { return new DataResponse([], 403); }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getAccess(): DataResponse {
        try { 
            $this->requireAccess('admin_access'); 
            return new DataResponse($this->service->getAccessRules()); 
        } catch(\Exception $e) { return new DataResponse([], 403); }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveAccess(): DataResponse {
        try { 
            $this->requireAccess('admin_access');
            $data = $this->request->getParams();
            $this->service->saveAccessRule($data['rule_key'], $data['allowed_groups'] ?? []);
            return new DataResponse(['status' => 'success']);
        } catch(\Exception $e) { return new DataResponse([], 403); }
    }
}