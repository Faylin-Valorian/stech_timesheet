<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Payroll\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCA\StechTimesheet\Features\Admin\Payroll\Service\PayrollService;
use OCA\StechTimesheet\Service\AnalysisService;

class PayrollController extends Controller {
    private $service;
    private $analysisService;

    public function __construct(IRequest $request, 
                                PayrollService $service,
                                AnalysisService $analysisService) {
        parent::__construct('stech_timesheet', $request);
        $this->service = $service;
        $this->analysisService = $analysisService;
    }

    private function requireAccess(): void {
        if (!$this->analysisService->checkAccess('admin_payroll')) {
            throw new \Exception("Access Denied: Missing permission 'admin_payroll'");
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getSettings(): DataResponse {
        try {
            $this->requireAccess();
            return new DataResponse($this->service->getSettings());
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveSettings(): DataResponse {
        try {
            $this->requireAccess();
            $this->service->saveSettings($this->request->getParams());
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }
}