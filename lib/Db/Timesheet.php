<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Db;

use OCP\AppFramework\Db\Entity;

class Timesheet extends Entity {
    protected $userid;
    protected $timesheetDate;
    protected $timeIn;
    protected $timeOut;
    protected $timeBreak;
    protected $timeTotal;
    
    // Missing Travel Fields Added:
    protected $travel;
    protected $travelPerDiem;
    protected $travelState;
    protected $travelCounty;
    protected $travelMiles;
    protected $travelExtraExpenses;
    
    protected $additionalComments;
    protected $archive;

    public function __construct() {
        $this->addType('timeTotal', 'float');
        $this->addType('archive', 'integer');
        $this->addType('travel', 'integer');
        $this->addType('travelPerDiem', 'integer');
        $this->addType('travelMiles', 'integer');
        $this->addType('travelExtraExpenses', 'float');
    }
}