<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Location\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCA\StechTimesheet\Features\Admin\Location\Service\LocationService;
use OCA\StechTimesheet\Service\AnalysisService;

class LocationController extends Controller {
    private $service;
    private $analysisService;

    public function __construct(IRequest $request, 
                                LocationService $service,
                                AnalysisService $analysisService) {
        parent::__construct('stech_timesheet', $request);
        $this->service = $service;
        $this->analysisService = $analysisService;
    }

    private function requireAccess(): void {
        if (!$this->analysisService->checkAccess('admin_locations')) {
            throw new \Exception("Access Denied: Missing permission 'admin_locations'");
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getStates(): DataResponse {
        try {
            $this->requireAccess();
            return new DataResponse($this->service->getStatesList());
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getCounties(string $abbr): DataResponse {
        try {
            $this->requireAccess();
            return new DataResponse($this->service->getCountiesList($abbr));
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function toggleState(int $id): DataResponse {
        try {
            $this->requireAccess();
            return new DataResponse(['new_state' => $this->service->toggleStateStatus($id)]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function toggleCounty(int $id): DataResponse {
        try {
            $this->requireAccess();
            return new DataResponse(['new_state' => $this->service->toggleCountyStatus($id)]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 403);
        }
    }
}