<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCA\StechTimesheet\Service\HolidayService;

class HolidayInsertJob extends TimedJob {
    private $holidayService;
    private $config;

    public function __construct(ITimeFactory $time, HolidayService $holidayService, IConfig $config) {
        parent::__construct($time);
        // Default interval for TimedJob (seconds) - 600s = 10 Minutes
        $this->setInterval(600); 
        $this->holidayService = $holidayService;
        $this->config = $config;
    }

    public function run($argument) {
        // MANUAL FREQUENCY CHECK
        // Get the last time this specific logic ran from the app config
        $lastRun = (int)$this->config->getAppValue('stech_timesheet', 'cron_last_run_holidays', '0');
        $now = time();
        
        // 10 Minute Interval (600 seconds)
        // You can change this variable to 604800 for a weekly check later
        $interval = 600; 

        if (($now - $lastRun) < $interval) {
            return; // Skip execution if the interval hasn't passed
        }

        // Run the actual holiday processing service
        $this->holidayService->processHolidays();

        // Update last run time to now
        $this->config->setAppValue('stech_timesheet', 'cron_last_run_holidays', (string)$now);
    }
}