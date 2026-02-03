<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Service;

use OCA\StechTimesheet\Db\AnalysisMapper;
use OCA\StechTimesheet\Db\TimesheetMapper;

class AnalysisService {
    private $analysisMapper;
    private $timesheetMapper;

    public function __construct(AnalysisMapper $analysisMapper, TimesheetMapper $timesheetMapper) {
        $this->analysisMapper = $analysisMapper;
        $this->timesheetMapper = $timesheetMapper;
    }

    public function getStats(string $period, ?string $uid): array {
        // 1. Get Payroll Date Range logic from Mapper settings
        $settingsRows = $this->timesheetMapper->getAdminSettings();
        $settings = [];
        foreach ($settingsRows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // ... Date Calculation Logic ...
        $start = '2026-01-01'; // Example calculated start
        $end = '2026-01-14';   // Example calculated end

        // 2. Fetch raw data from the specialized AnalysisMapper
        $rawData = $this->analysisMapper->getFullReportingData($start, $end, $uid);

        // 3. Process aggregation (total hours, job breakdown, etc.)
        return $this->aggregateData($rawData);
    }

    private function aggregateData(array $data): array {
        // Your original looping logic to sum hours and group by job
        return [
            'total_hours' => 0,
            'jobs' => [],
            'travel' => []
        ];
    }
}