<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Job\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCA\StechTimesheet\Features\Admin\Job\Service\JobService;
use OCA\StechTimesheet\Service\AnalysisService;

class JobController extends Controller {
    private $service;
    private $analysisService;

    public function __construct(IRequest $request, 
                                JobService $service,
                                AnalysisService $analysisService) {
        parent::__construct('stech_timesheet', $request);
        $this->service = $service;
        $this->analysisService = $analysisService;
    }

    private function requireAccess(): void {
        if (!$this->analysisService->checkAccess('admin_jobs')) {
            throw new \Exception("Access Denied: Missing permission 'admin_jobs'");
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): DataResponse {
        try {
            $this->requireAccess();
            return new DataResponse($this->service->getJobList());
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function save(): DataResponse {
        try {
            $this->requireAccess();
            $this->service->saveJob($this->request->getParams());
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function toggle(int $id): DataResponse {
        try {
            $this->requireAccess();
            $this->service->toggleJobStatus($id);
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }
}