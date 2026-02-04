<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Db;

use OCP\AppFramework\Db\Entity;

class Timesheet extends Entity {
    protected $userid;
    protected $timesheetDate; // Maps to timesheet_date
    protected $timeIn;        // Maps to time_in
    protected $timeOut;       // Maps to time_out
    protected $timeBreak;     // Maps to time_break
    protected $timeTotal;     // Maps to time_total
    protected $travel;        // Maps to travel
    protected $travelPerDiem; // Maps to travel_per_diem
    protected $travelState;   // Maps to travel_state
    protected $travelCounty;  // Maps to travel_county
    protected $travelMiles;   // Maps to travel_miles
    protected $travelExtraExpenses; // Maps to travel_extra_expenses
    protected $additionalComments;  // Maps to additional_comments
    protected $archive;

    public function __construct() {
        // Explicitly defining types for correct database casting
        $this->addType('timeTotal', 'float');
        $this->addType('archive', 'integer');
        $this->addType('travel', 'integer');
        $this->addType('travelPerDiem', 'integer');
        $this->addType('travelMiles', 'integer');
        $this->addType('travelExtraExpenses', 'float');
    }
}