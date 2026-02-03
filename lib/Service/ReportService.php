<?php
namespace OCA\StechTimesheet\Service;

use OCA\StechTimesheet\Db\TimesheetMapper;

class ReportService {
    private $mapper;

    public function __construct(TimesheetMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function generateCompanyWideCSV(\DateTime $start, \DateTime $end): string {
        $data = $this->mapper->getRangeForReporting($start, $end);
        
        $output = "User,Date,Hours,Type\n";
        foreach ($data as $row) {
            $output .= "{$row['userid']},{$row['timesheet_date']},{$row['time_total']},{$row['work_description']}\n";
        }
        
        return $output;
    }
}