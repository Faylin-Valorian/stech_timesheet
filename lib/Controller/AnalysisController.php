<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCA\StechTimesheet\Service\AnalysisService;
use OCA\StechTimesheet\Db\TimesheetMapper;

class AnalysisController extends Controller {
    private $service;
    private $mapper;

    public function __construct(IRequest $request, AnalysisService $service, TimesheetMapper $mapper) {
        parent::__construct('stech_timesheet', $request);
        $this->service = $service;
        $this->mapper = $mapper;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getStats(string $period, string $target_user = 'self'): DataResponse {
        // 1. Get Dates
        list($startDate, $endDate) = $this->service->getPayrollDateRange($period);
        
        // 2. Get Data
        $results = $this->mapper->getAnalysisResults(
            $startDate->format('Y-m-d'), 
            $endDate->format('Y-m-d'), 
            $target_user === 'self' ? null : $target_user
        );

        // 3. Format and Return
        return new DataResponse($this->service->formatStats($results));
    }
}