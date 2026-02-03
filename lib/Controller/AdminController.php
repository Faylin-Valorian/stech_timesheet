<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCA\StechTimesheet\Service\AdminService;
use OCA\StechTimesheet\Db\AdminMapper;

class AdminController extends Controller {
    private $adminService;
    private $adminMapper;

    public function __construct(IRequest $request, AdminService $adminService, AdminMapper $adminMapper) {
        parent::__construct('stech_timesheet', $request);
        $this->adminService = $adminService;
        $this->adminMapper = $adminMapper;
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function index(): TemplateResponse {
        return new TemplateResponse('stech_timesheet', 'admin');
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function getUsers(): DataResponse {
        return new DataResponse($this->adminService->getProcessedUserList());
    }

    /**
     * @NoCSRFRequired
     * @AdminRequired
     */
    public function getSettings(): DataResponse {
        $rows = $this->adminMapper->getSettings();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return new DataResponse($settings);
    }
}