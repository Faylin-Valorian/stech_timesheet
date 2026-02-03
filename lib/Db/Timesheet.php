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
    protected $workDescription;
    protected $additionalComments;
    protected $travelPerDiem;
    protected $archive;

    public function __construct() {
        $this->addType('timeTotal', 'float');
        $this->addType('archive', 'integer');
        $this->addType('travelPerDiem', 'integer');
    }
}