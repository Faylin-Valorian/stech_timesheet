<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Holiday\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCA\StechTimesheet\Features\Admin\Holiday\Service\HolidayService;
use OCA\StechTimesheet\Service\AnalysisService;

class HolidayController extends Controller {
    private $service;
    private $analysisService;

    public function __construct(IRequest $request, 
                                HolidayService $service,
                                AnalysisService $analysisService) {
        parent::__construct('stech_timesheet', $request);
        $this->service = $service;
        $this->analysisService = $analysisService;
    }

    private function requireAccess(): void {
        if (!$this->analysisService->checkAccess('admin_holidays')) {
            throw new \Exception("Access Denied: Missing permission 'admin_holidays'");
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): DataResponse {
        try {
            $this->requireAccess();
            return new DataResponse($this->service->getAllHolidays());
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
            $this->service->saveHoliday($this->request->getParams());
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
            $this->service->toggleHoliday($id);
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function delete(int $id): DataResponse {
        try {
            $this->requireAccess();
            $this->service->deleteHoliday($id);
            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }
}